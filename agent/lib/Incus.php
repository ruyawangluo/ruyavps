<?php
namespace Agent;

/**
 * Incus 命令行驱动封装。
 * 所有操作通过 `incus` CLI 完成，返回 [exitCode, stdout, stderr]。
 */
class Incus
{
    private string $bin;
    private string $storagePool;
    private string $bridge;
    /** @var array<string, array{0:float,1:float}> 上次采样: 实例名 => [cpu 纳秒, 时间] */
    private array $lastCpu = [];

    public function __construct(string $bin, string $storagePool, string $bridge)
    {
        $this->bin = $bin;
        $this->storagePool = $storagePool;
        $this->bridge = $bridge;
    }

    /**
     * 执行 incus 命令。
     * @param array $args 参数数组（会被逐个转义）
     * @return array{code:int, out:string, err:string}
     */
    public function run(array $args, ?string $stdin = null): array
    {
        $cmd = escapeshellcmd($this->bin);
        foreach ($args as $a) {
            $cmd .= ' ' . escapeshellarg((string)$a);
        }
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            return ['code' => -1, 'out' => '', 'err' => '无法启动进程'];
        }
        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        return ['code' => $code, 'out' => (string)$out, 'err' => (string)$err];
    }

    /** 执行并断言成功，失败抛出异常。 */
    public function must(array $args, ?string $stdin = null): string
    {
        $r = $this->run($args, $stdin);
        if ($r['code'] !== 0) {
            throw new \RuntimeException(
                'incus ' . implode(' ', array_map('strval', $args)) . ' 失败: ' . trim($r['err'] ?: $r['out'])
            );
        }
        return $r['out'];
    }

    public function version(): string
    {
        $r = $this->run(['version']);
        if ($r['code'] !== 0) {
            return '';
        }
        // 输出形如: Client version: 7.4 / Server version: 7.4
        if (preg_match('/Server version:\s*([0-9.]+)/', $r['out'], $m)) {
            return $m[1];
        }
        return trim($r['out']);
    }

    public function exists(string $name): bool
    {
        $r = $this->run(['info', $name]);
        return $r['code'] === 0;
    }

    /**
     * 创建并启动实例。
     * @param array $p {
     *   name, source, variant(container|vm), cpu, memory_mb, disk_gb,
     *   bridge, ip, gateway, storage_pool, root_password, os_user, hostname, cloud_init
     * }
     * @return array{ip:string}
     */
    public function create(array $p): array
    {
        $name = (string)($p['name'] ?? $p['incus_name'] ?? '');
        if ($name === '') {
            throw new \RuntimeException('缺少实例名 name/incus_name');
        }
        $isVm = ($p['variant'] ?? 'container') === 'vm';
        $pool = $p['storage_pool'] ?: $this->storagePool;

        if ($this->exists($name)) {
            throw new \RuntimeException("实例 {$name} 已存在");
        }

        $initArgs = ['init', $p['source'], $name, '--storage', $pool];
        if ($isVm) {
            $initArgs[] = '--vm';
        }
        $this->must($initArgs);

        // 资源限制
        $this->must(['config', 'set', $name, 'limits.cpu', (string)max(1, (int)$p['cpu'])]);
        $this->must(['config', 'set', $name, 'limits.memory', max(128, (int)$p['memory_mb']) . 'MB']);
        $this->must(['config', 'set', $name, 'limits.disk.priority', '5']);

        // 网络：网桥绑定
        if (!empty($p['bridge'])) {
            $this->run(['config', 'device', 'override', $name, 'eth0', 'network', $p['bridge']]);
        }

        // 主机名
        if (!empty($p['hostname'])) {
            $this->run(['config', 'set', $name, 'cloud-init.hostname', $p['hostname']]);
        }

        // cloud-init：密码 + 静态网络
        $cloudInit = !empty($p['cloud_init']);
        if ($cloudInit) {
            $this->applyCloudInit($name, $p, $isVm);
        }

        // 资源/安全限制
        if (isset($p['disk_read_mbs'])) {
            $this->applyIoLimit($name, (int)$p['disk_read_mbs'], (int)($p['disk_write_mbs'] ?? 0));
        } elseif (!empty($p['io_limit'])) {
            $this->applyIoLimitStr($name, (string)$p['io_limit']);
        }
        if (!empty($p['cpu_allowance'])) {
            $this->run(['config', 'set', $name, 'limits.cpu.allowance', (int)$p['cpu_allowance'] . '%']);
        }
        if (!empty($p['processes'])) {
            $this->run(['config', 'set', $name, 'limits.processes', (string)(int)$p['processes']]);
        }
        if (isset($p['nesting'])) {
            $this->run(['config', 'set', $name, 'security.nesting', !empty($p['nesting']) ? 'true' : 'false']);
        }
        if (isset($p['privileged'])) {
            $this->run(['config', 'set', $name, 'security.privileged', !empty($p['privileged']) ? 'true' : 'false']);
        }
        if (isset($p['swap'])) {
            $this->run(['config', 'set', $name, 'limits.memory.swap', !empty($p['swap']) ? 'true' : 'false']);
        }
        // 带宽：原生无实例级限速，先作元数据记录（可由宿主机 tc 脚本读取）
        if (isset($p['inbound_mbps'])) {
            $this->run(['config', 'set', $name, 'user.inbound_mbps', (string)(int)$p['inbound_mbps']]);
        }
        if (isset($p['outbound_mbps'])) {
            $this->run(['config', 'set', $name, 'user.outbound_mbps', (string)(int)$p['outbound_mbps']]);
        }
        // 自定义 incus 配置（key=value）
        if (!empty($p['config']) && is_array($p['config'])) {
            foreach ($p['config'] as $k => $v) {
                if ($k === '') {
                    continue;
                }
                $this->run(['config', 'set', $name, (string)$k, (string)$v]);
            }
        }

        // 启动
        $this->must(['start', $name]);

        // 非 cloud-init 场景：等待启动后注入密码 & 静态 IP
        $ip = '';
        if (!$cloudInit) {
            $this->waitReady($name, $isVm);
            if (!empty($p['root_password'])) {
                $this->setPassword($name, $p['os_user'] ?: 'root', $p['root_password'], $isVm);
            }
            if (!empty($p['ip'])) {
                $this->runStaticIpNonCloudInit($name, $p, $isVm);
            }
        }

        $ip = $ip ?: $this->firstIpv4($name);
        return ['ip' => $ip];
    }

    private function applyCloudInit(string $name, array $p, bool $isVm): void
    {
        $user = $p['os_user'] ?: 'root';
        $userData = "#cloud-config\n";
        if (!empty($p['root_password'])) {
            $userData .= "ssh_pwauth: true\n";
            $userData .= "chpasswd:\n  expire: false\n  list: |\n    {$user}:{$p['root_password']}\n";
            $userData .= "users:\n  - name: {$user}\n    lock_passwd: false\n    shell: /bin/bash\n";
            if ($user !== 'root') {
                $userData .= "    sudo: ALL=(ALL) NOPASSWD:ALL\n";
            }
        }
        $this->must(['config', 'set', $name, 'cloud-init.user-data', $userData]);

        if (!empty($p['ip'])) {
            $gw = $p['gateway'] ?: $this->guessGateway($p['ip']);
            $net = "#cloud-config\nversion: 2\nethernets:\n  eth0:\n    dhcp4: false\n"
                 . "    addresses:\n      - {$p['ip']}/24\n"
                 . ($gw ? "    routes:\n      - to: default\n        via: {$gw}\n" : '')
                 . ($gw ? "    nameservers:\n      addresses: [{$gw}, 8.8.8.8]\n" : '');
            $this->must(['config', 'set', $name, 'cloud-init.network-config', $net]);
        }
    }

    /** 非 cloud-init 镜像：用 exec 尝试设置静态 IP（best-effort，支持 netplan 与 sysconfig）。 */
    private function runStaticIpNonCloudInit(string $name, array $p, bool $isVm): void
    {
        if ($isVm) {
            return; // 虚拟机需镜像内 agent，暂不处理
        }
        $gw = $p['gateway'] ?: $this->guessGateway($p['ip']);
        $script = 'if [ -d /etc/netplan ]; then '
            . 'cat > /etc/netplan/60-static.yaml <<EOF' . "\n"
            . "network:\n  version: 2\n  ethernets:\n    eth0:\n      dhcp4: false\n"
            . "      addresses: [{$p['ip']}/24]\n"
            . ($gw ? "      routes:\n        - to: default\n          via: {$gw}\n      nameservers:\n        addresses: [{$gw}]\n" : '')
            . "EOF\n"
            . 'netplan apply 2>/dev/null || true; fi';
        $this->run(['exec', $name, '--', 'sh', '-c', $script]);
    }

    private function setPassword(string $name, string $user, string $password, bool $isVm): void
    {
        $this->run(['exec', $name, '--', 'sh', '-c', "echo " . escapeshellarg("{$user}:{$password}") . " | chpasswd"]);
    }

    public function start(string $name): void   { $this->must(['start', $name]); }
    public function stop(string $name): void    { $this->must(['stop', $name]); }
    public function restart(string $name): void { $this->must(['restart', $name]); }
    public function freeze(string $name): void  { $this->must(['freeze', $name]); }
    public function unfreeze(string $name): void{ $this->must(['unfreeze', $name]); }

    public function delete(string $name): void
    {
        $this->run(['stop', $name, '--force']);
        $this->must(['delete', $name, '--force']);
    }

    public function resetPassword(string $name, string $user, string $password): void
    {
        $this->setPassword($name, $user, $password, false);
    }

    /** 在实例内执行命令，返回合并输出。 */
    public function execCommand(string $name, string $command): string
    {
        $r = $this->run(['exec', $name, '--', 'sh', '-c', $command]);
        $out = trim($r['out'] . ($r['err'] !== '' ? "\n" . $r['err'] : ''));
        if ($r['code'] !== 0) {
            throw new \RuntimeException('命令执行失败(exit ' . $r['code'] . '): ' . $out);
        }
        return $out;
    }

    /** 设置磁盘 IO 限速（MB/s）。 */
    public function applyIoLimit(string $name, int $readMbs, int $writeMbs): void
    {
        if ($readMbs > 0) {
            $this->run(['config', 'set', $name, 'limits.disk.read', $readMbs . 'MB']);
        }
        if ($writeMbs > 0) {
            $this->run(['config', 'set', $name, 'limits.disk.write', $writeMbs . 'MB']);
        }
    }

    /** 兼容字符串形式 "100MB" / "100MB,200MB" / "1000iops"。 */
    public function applyIoLimitStr(string $name, string $ioLimit): void
    {
        $ioLimit = trim($ioLimit);
        if ($ioLimit === '') {
            return;
        }
        $parts = array_map('trim', explode(',', $ioLimit));
        $this->run(['config', 'set', $name, 'limits.disk.read', $parts[0]]);
        $this->run(['config', 'set', $name, 'limits.disk.write', $parts[1] ?? $parts[0]]);
    }

    /** 更新限速（带宽/IO/CPU 上限/进程数）。 */
    public function setLimits(string $name, array $p): void
    {
        if (isset($p['inbound_mbps'])) {
            $this->run(['config', 'set', $name, 'user.inbound_mbps', (string)(int)$p['inbound_mbps']]);
        }
        if (isset($p['outbound_mbps'])) {
            $this->run(['config', 'set', $name, 'user.outbound_mbps', (string)(int)$p['outbound_mbps']]);
        }
        if (isset($p['disk_read_mbs']) || isset($p['disk_write_mbs'])) {
            $this->applyIoLimit($name, (int)($p['disk_read_mbs'] ?? 0), (int)($p['disk_write_mbs'] ?? 0));
        }
        if (isset($p['cpu_allowance'])) {
            $this->run(['config', 'set', $name, 'limits.cpu.allowance', (int)$p['cpu_allowance'] . '%']);
        }
        if (isset($p['processes'])) {
            $this->run(['config', 'set', $name, 'limits.processes', (string)(int)$p['processes']]);
        }
    }

    /** 列出存储池（名称/驱动/状态）。 */
    public function storagePools(): array
    {
        $r = $this->run(['storage', 'list', '--format', 'json']);
        if ($r['code'] !== 0) {
            return [];
        }
        $rows = json_decode($r['out'], true) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'name'        => $row['name'] ?? '',
                'driver'      => $row['driver'] ?? '',
                'status'      => $row['status'] ?? '',
                'description' => $row['description'] ?? '',
            ];
        }
        return $out;
    }

    /** 调整规格。 */
    public function resize(string $name, int $cpu, int $memoryMb, int $diskGb): void
    {
        if ($cpu > 0) {
            $this->must(['config', 'set', $name, 'limits.cpu', (string)$cpu]);
        }
        if ($memoryMb > 0) {
            $this->must(['config', 'set', $name, 'limits.memory', $memoryMb . 'MB']);
        }
        if ($diskGb > 0) {
            $this->must(['config', 'device', 'set', $name, 'root', 'size', $diskGb . 'GB']);
        }
    }

    /** 重建：删除后按新镜像重装，保留名称与配置。 */
    public function rebuild(array $p): array
    {
        $name = $p['name'];
        if ($this->exists($name)) {
            $this->delete($name);
        }
        return $this->create($p);
    }

    // ---- 快照 ----

    public function snapshotCreate(string $name, string $snap): void
    {
        $this->must(['snapshot', $name, 'create', $snap]);
    }

    public function snapshotRestore(string $name, string $snap): void
    {
        $this->must(['snapshot', $name, 'restore', $snap]);
    }

    public function snapshotDelete(string $name, string $snap): void
    {
        $this->must(['snapshot', $name, 'delete', $snap]);
    }

    /** 列出某实例的快照名。 */
    public function snapshotList(string $name): array
    {
        $out = $this->must(['snapshot', $name, 'list', '--format', 'json']);
        $data = json_decode($out, true) ?: [];
        return array_map(fn($s) => $s['name'] ?? '', $data);
    }

    // ---- 查询 ----

    /** 返回 [name => ['status'=>..., 'ip'=>..., 'cpu_pct'=>..., 'mem_mb'=>...]] */
    public function listInstances(): array
    {
        $out = $this->must(['list', '--format', 'json']);
        $rows = json_decode($out, true) ?: [];
        $now = microtime(true);
        $ncpu = max(1, (int)trim((string)@shell_exec('nproc 2>/dev/null')));
        $result = [];
        foreach ($rows as $r) {
            $name = $r['name'] ?? '';
            if ($name === '') {
                continue;
            }
            $cpuPct = 0.0;
            $memMb = 0;
            $state = $r['state'] ?? null;
            if (is_array($state)) {
                $cpuNs = (float)($state['cpu']['usage'] ?? 0);
                if ($cpuNs > 0 && isset($this->lastCpu[$name])) {
                    [$prevNs, $prevT] = $this->lastCpu[$name];
                    $dt = $now - $prevT;
                    if ($dt > 0 && $cpuNs >= $prevNs) {
                        $cpuPct = min(100.0, round((($cpuNs - $prevNs) / 1e9) / ($dt * $ncpu) * 100, 2));
                    }
                }
                if ($cpuNs > 0) {
                    $this->lastCpu[$name] = [$cpuNs, $now];
                }
                $memMb = (int)round(((float)($state['memory']['usage'] ?? 0)) / 1048576);
            }
            $result[$name] = [
                'name'    => $name,
                'status'  => $r['status'] ?? '',
                'ip'      => $this->extractIp($r),
                'cpu_pct' => $cpuPct,
                'mem_mb'  => $memMb,
            ];
        }
        return $result;
    }

    private function extractIp(array $r): string
    {
        $network = $r['state']['network'] ?? [];
        foreach ($network as $iface) {
            foreach (($iface['addresses'] ?? []) as $addr) {
                if (($addr['family'] ?? '') === 'inet' && ($addr['scope'] ?? '') === 'global') {
                    return $addr['address'] ?? '';
                }
            }
        }
        return '';
    }

    public function firstIpv4(string $name): string
    {
        $r = $this->run(['list', $name, '--format', 'json']);
        if ($r['code'] !== 0) {
            return '';
        }
        $rows = json_decode($r['out'], true) ?: [];
        return $rows ? $this->extractIp($rows[0]) : '';
    }

    /** 收集主机资源信息。 */
    public function hostResources(): array
    {
        $cpuTotal = (int)trim((string)@shell_exec('nproc 2>/dev/null')) ?: 1;
        $load = sys_getloadavg();
        $cpuUsed = $load ? (int)round(min($load[0], $cpuTotal)) : 0;

        $memTotal = 0; $memAvail = 0;
        $meminfo = @file_get_contents('/proc/meminfo');
        if ($meminfo && preg_match('/MemTotal:\s+(\d+) kB/', $meminfo, $m1)) {
            $memTotal = (int)round((int)$m1[1] / 1024);
        }
        if ($meminfo && preg_match('/MemAvailable:\s+(\d+) kB/', $meminfo, $m2)) {
            $memAvail = (int)round((int)$m2[1] / 1024);
        }

        $diskTotal = 0; $diskUsed = 0;
        $df = @shell_exec('df -BG --output=size,used / 2>/dev/null');
        if ($df && preg_match('/(\d+)G\s+(\d+)G/', $df, $m3)) {
            $diskTotal = (int)$m3[1];
            $diskUsed = (int)$m3[2];
        }

        return [
            'cpu_total'    => $cpuTotal,
            'cpu_used'     => $cpuUsed,
            'mem_total_mb' => $memTotal,
            'mem_used_mb'  => max(0, $memTotal - $memAvail),
            'disk_total_gb'=> $diskTotal,
            'disk_used_gb' => $diskUsed,
        ];
    }

    private function waitReady(string $name, bool $isVm, int $tries = 20): void
    {
        for ($i = 0; $i < $tries; $i++) {
            $r = $this->run(['exec', $name, '--', 'true']);
            if ($r['code'] === 0) {
                return;
            }
            usleep(500000);
        }
    }

    private function guessGateway(string $ip): string
    {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.1';
        }
        return '';
    }
}

<?php
namespace App\Controllers;

use App\Core\Config;
use App\Core\Http;
use App\Core\Tar;

/**
 * 被控端远程部署分发：
 *   GET /deploy/agent.sh   返回安装脚本（内嵌本站地址）
 *   GET /deploy/agent.tar  返回被控端代码包（tar）
 *
 * 这两个端点无需鉴权：被控机在安装时尚未注册，且包内不含任何密钥。
 */
class DeployController
{
    /** 被控端源码目录：部署环境读 agent-src，开发环境回退到仓库 agent/ */
    private function srcDir(): string
    {
        $deployed = APP_ROOT . '/agent-src';
        if (is_dir($deployed)) {
            return $deployed;
        }
        return dirname(APP_ROOT) . '/agent';
    }

    private function templateFile(): string
    {
        $deployed = APP_ROOT . '/agent-install.sh';
        if (is_file($deployed)) {
            return $deployed;
        }
        return dirname(APP_ROOT) . '/install/agent-install.sh';
    }

    /** 本站地址（用于注入安装脚本与生成一键命令）。 */
    public static function masterUrl(): string
    {
        $configured = trim((string)Config::get('site_url', ''));
        if ($configured !== '' && !str_contains($configured, 'your-master')) {
            return rtrim($configured, '/');
        }
        // 回退：按当前请求推断
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }

    /** 生成被控端一键安装命令。 */
    public static function installCommand(string $apiKey, string $apiSecret): string
    {
        return sprintf(
            'curl -fsSL %s/deploy/agent.sh | sudo bash -s -- %s %s',
            self::masterUrl(),
            $apiKey,
            $apiSecret
        );
    }

    /** 生成节点端安装命令（无需参数）。 */
    public static function nodeInstallCommand(): string
    {
        return sprintf('curl -fsSL %s/deploy/agent.sh | sudo bash', self::masterUrl());
    }

    /** 读取节点端程序版本（来自 agent/lib/Version.php）。 */
    public static function distributedAgentVersion(): string
    {
        $dir = is_dir(APP_ROOT . '/agent-src') ? APP_ROOT . '/agent-src' : dirname(APP_ROOT) . '/agent';
        $file = $dir . '/lib/Version.php';
        if (is_file($file) && preg_match("/VERSION\s*=\s*'([0-9.]+)'/", (string)file_get_contents($file), $m)) {
            return $m[1];
        }
        return '';
    }

    public function agentScript(): void
    {
        $file = $this->templateFile();
        if (!is_file($file)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "# 安装脚本模板缺失: {$file}\n";
            return;
        }
        $script = str_replace('__MASTER_URL__', self::masterUrl(), (string)file_get_contents($file));

        header('Content-Type: text/x-shellscript; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Content-Disposition: inline; filename="agent-install.sh"');
        echo $script;
    }

    public function agentPackage(): void
    {
        $dir = $this->srcDir();
        if (!is_dir($dir)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "# 被控端源码缺失: {$dir}\n";
            return;
        }
        $tar = new Tar();
        // 排除本地配置与日志，避免把凭据打包出去
        $tar->addDirectoryTree($dir, '', ['#^config\.php$#', '#\.log$#', '#/\.#']);
        $data = $tar->finish();

        header('Content-Type: application/x-tar');
        header('Content-Disposition: attachment; filename="incus-agent.tar"');
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: no-cache');
        echo $data;
    }

    /** 分发的节点运维脚本（Incus 安装/存储/镜像）。 */
    private const TOOLS = ['incus-manage', 'incus-storage', 'incus-image'];

    public function tool(string $name): void
    {
        $name = preg_replace('/\.sh$/', '', $name);
        if (!in_array($name, self::TOOLS, true)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo "# 未知脚本\n";
            return;
        }
        $file = APP_ROOT . '/resources/' . $name . '.sh';
        if (!is_file($file)) {
            $file = dirname(APP_ROOT) . '/master/resources/' . $name . '.sh';
        }
        if (!is_file($file)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "# 脚本缺失: {$name}\n";
            return;
        }
        header('Content-Type: text/x-shellscript; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $name . '.sh"');
        header('Cache-Control: no-cache');
        echo (string)file_get_contents($file);
    }

    /** 生成某个节点运维脚本的一键执行命令。 */
    public static function toolCommand(string $name): string
    {
        return sprintf(
            'curl -fsSL %s/deploy/tools/%s.sh -o /tmp/%s.sh && sudo bash /tmp/%s.sh',
            self::masterUrl(),
            $name,
            $name,
            $name
        );
    }
}

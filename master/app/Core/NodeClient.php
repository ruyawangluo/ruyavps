<?php
namespace App\Core;

/**
 * 面板 -> 节点 的 HTTP 客户端（节点本地 API）。
 * 鉴权：请求头 X-Node-Token = 节点密钥。
 */
class NodeClient
{
    private string $base;
    private string $key;
    private int $timeout;

    public function __construct(string $ip, int $port, string $key, int $timeout = 15)
    {
        $this->base = 'http://' . $ip . ':' . $port;
        $this->key = $key;
        $this->timeout = $timeout;
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init($this->base . $path);
        $headers = ['X-Node-Token: ' . $this->key, 'Content-Type: application/json'];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) {
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => '连接失败: ' . $err];
        }
        $decoded = json_decode((string)$resp, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'status' => $status, 'data' => null, 'error' => '响应解析失败: ' . substr((string)$resp, 0, 160)];
        }
        $ok = ($decoded['code'] ?? 1) === 0 || $status < 400;
        return [
            'ok'     => $ok,
            'status' => $status,
            'data'   => $decoded['data'] ?? $decoded,
            'error'  => $ok ? '' : (string)($decoded['message'] ?? '节点返回错误'),
        ];
    }

    public function health(): array
    {
        return $this->request('GET', '/health');
    }

    public function nodeInfo(): array
    {
        return $this->request('GET', '/node');
    }

    public function listInstances(): array
    {
        return $this->request('GET', '/instances');
    }

    public function getInstance(string $incusName): array
    {
        return $this->request('GET', '/instances/' . rawurlencode($incusName));
    }

    public function createInstance(array $payload): array
    {
        return $this->request('POST', '/instances', $payload);
    }

    public function action(string $incusName, array $payload): array
    {
        return $this->request('POST', '/instances/' . rawurlencode($incusName) . '/action', $payload);
    }

    public function deleteInstance(string $incusName): array
    {
        return $this->request('DELETE', '/instances/' . rawurlencode($incusName));
    }

    public function images(): array
    {
        return $this->request('GET', '/images');
    }

    public function storage(): array
    {
        return $this->request('GET', '/storage');
    }
}

<?php
namespace App\Core;

/**
 * 开放 API 鉴权：供上层财务/前台系统调用，使用平台级凭据（settings.openapi_key/secret）。
 * 签名算法与 NodeAuth 一致。
 */
class OpenAuth
{
    private const MAX_SKEW = 300;

    public static function authenticate(): void
    {
        $apiKey    = Http::header('X-Api-Key');
        $timestamp = Http::header('X-Timestamp');
        $signature = Http::header('X-Signature');

        $expectedKey = (string)Setting::get('openapi_key', '');
        $secret      = (string)Setting::get('openapi_secret', '');
        if ($expectedKey === '' || $secret === '') {
            throw new \RuntimeException('开放 API 未启用（请先在系统设置生成凭据）', 403);
        }
        if ($apiKey === '' || $timestamp === '' || $signature === '') {
            throw new \RuntimeException('缺少鉴权头', 401);
        }
        if (!hash_equals($expectedKey, $apiKey)) {
            throw new \RuntimeException('API Key 无效', 401);
        }
        if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > self::MAX_SKEW) {
            throw new \RuntimeException('时间戳无效或已过期', 401);
        }
        $payload = $apiKey . "\n" . $timestamp . "\n" . hash('sha256', Http::rawBody());
        $expect  = hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($expect, strtolower($signature))) {
            throw new \RuntimeException('签名校验失败', 401);
        }
    }

    /** 供对方系统生成签名头（文档/自测用）。 */
    public static function sign(string $apiKey, string $secret, string $body, ?int $ts = null): array
    {
        $t = $ts ?? time();
        return [
            'X-Api-Key'   => $apiKey,
            'X-Timestamp' => (string)$t,
            'X-Signature' => hash_hmac('sha256', $apiKey . "\n" . $t . "\n" . hash('sha256', $body), $secret),
            'Content-Type'=> 'application/json',
        ];
    }
}

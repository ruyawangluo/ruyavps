<?php
namespace App\Core;

class Http
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }
        return '/' . trim(rawurldecode($uri), '/');
    }

    public static function input(string $key, $default = null)
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }
        if (array_key_exists($key, $_GET)) {
            return $_GET[$key];
        }
        $json = self::json();
        if (is_array($json) && array_key_exists($key, $json)) {
            return $json[$key];
        }
        return $default;
    }

    public static function allInput(): array
    {
        $data = array_merge($_GET, $_POST);
        $json = self::json();
        if (is_array($json)) {
            $data = array_merge($data, $json);
        }
        return $data;
    }

    public static function json(): ?array
    {
        static $cached = false;
        static $data = null;
        if ($cached) {
            return $data;
        }
        $cached = true;
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return $data = null;
        }
        $decoded = json_decode($raw, true);
        return $data = is_array($decoded) ? $decoded : null;
    }

    public static function rawBody(): string
    {
        return (string)file_get_contents('php://input');
    }

    public static function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? '';
    }

    public static function ip(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = explode(',', $_SERVER[$k])[0];
                return trim($ip);
            }
        }
        return '';
    }

    public static function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

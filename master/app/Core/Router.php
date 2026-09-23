<?php
namespace App\Core;

/**
 * 极简路由器，支持 {param} 占位符，例如 /admin/nodes/{id}/edit
 */
class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:callable|array}> */
    private array $routes = [];

    public function add(string $method, string $pattern, $handler): void
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . rtrim($regex, '/') . '/?$#';
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $pattern,
            'regex'   => $regex,
            'handler' => $handler,
        ];
    }

    public function get(string $p, $h): void   { $this->add('GET', $p, $h); }
    public function post(string $p, $h): void  { $this->add('POST', $p, $h); }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            $path = '/';
        }
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->invoke($route['handler'], array_values($params));
                return;
            }
        }
        http_response_code(404);
        if (str_starts_with($path, '/api/')) {
            Http::jsonResponse(['code' => 404, 'message' => 'Not Found'], 404);
        }
        echo '404 Not Found';
    }

    private function invoke($handler, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
            return;
        }
        if (is_array($handler) && count($handler) === 2) {
            [$class, $action] = $handler;
            $obj = new $class();
            $obj->{$action}(...$params);
            return;
        }
        throw new \RuntimeException('无效的路由处理器');
    }
}

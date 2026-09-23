<?php
namespace App\Controllers;

use App\Core\View;

abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'layout'): void
    {
        View::render($template, $data, $layout);
    }

    protected function redirect(string $url): void
    {
        \App\Core\Http::redirect($url);
    }

    protected function json(array $data, int $status = 200): void
    {
        \App\Core\Http::jsonResponse($data, $status);
    }

    protected function abort404(string $message = '页面不存在'): void
    {
        http_response_code(404);
        View::render('error', ['code' => 404, 'message' => $message], 'layout');
        exit;
    }
}

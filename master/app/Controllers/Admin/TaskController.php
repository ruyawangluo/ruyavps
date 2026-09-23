<?php
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\Http;
use App\Services\InstanceService;

class TaskController extends Controller
{
    public function index(): void
    {
        $admin = Auth::requireAdmin();
        $page = max(1, (int)Http::input('page', 1));
        $size = 30;
        $offset = ($page - 1) * $size;
        $total = (int)Db::value('SELECT COUNT(*) FROM tasks');
        $this->view('admin/tasks/index', [
            'title' => '任务队列',
            'admin' => $admin,
            'tasks' => Db::all(
                "SELECT t.*, i.name AS instance_name, n.name AS node_name
                   FROM tasks t
                   LEFT JOIN instances i ON i.id = t.instance_id
                   LEFT JOIN nodes n ON n.id = t.node_id
                  ORDER BY t.id DESC LIMIT $size OFFSET $offset"
            ),
            'page'  => $page,
            'total' => $total,
            'size'  => $size,
        ], 'admin/layout');
    }

    public function retry(string $id): void
    {
        $admin = Auth::requireAdmin();
        Csrf::check();
        $task = Db::one('SELECT * FROM tasks WHERE id = ?', [(int)$id]);
        if ($task) {
            $payload = $task['payload'] ? json_decode($task['payload'], true) : [];
            \App\Services\TaskService::enqueue((int)$task['node_id'], (int)$task['instance_id'], $task['type'], $payload ?: []);
            \App\Core\Log::write('admin', (int)$admin['id'], 'task.retry', (string)$task['id']);
            flash('success', '已重新投递任务');
        }
        $this->redirect('/admin/tasks');
    }
}

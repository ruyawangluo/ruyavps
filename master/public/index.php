<?php
/**
 * 面板端入口（单一入口）。Web 根目录指向 master/public。
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Http;
use App\Core\Router;
use App\Controllers\DeployController;
use App\Controllers\OpenApiController;
use App\Controllers\InstanceApiController;
use App\Controllers\Admin\AuthController as AdminAuth;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\NodeController;
use App\Controllers\Admin\ImageController;
use App\Controllers\Admin\StorageController;
use App\Controllers\Admin\InstanceController as AdminInstance;
use App\Controllers\Admin\UserController as AdminUser;
use App\Controllers\Admin\LogController;
use App\Controllers\Admin\SettingController;
use App\Controllers\Admin\AboutController;
use App\Controllers\Client\AuthController as ClientAuth;
use App\Controllers\Client\DashboardController as ClientDashboard;
use App\Controllers\Client\InstanceController as ClientInstance;

$router = new Router();

// ---- 分发：节点端安装脚本 / 程序包 / 节点运维工具（无需鉴权） ----
$router->get('/deploy/agent.sh',  [DeployController::class, 'agentScript']);
$router->get('/deploy/agent.tar', [DeployController::class, 'agentPackage']);
$router->get('/deploy/tools/{name}', [DeployController::class, 'tool']);

// ---- 开放 API（供上层财务/前台） ----
foreach ([
    '/api/v1/open/ping',
    '/api/v1/open/user/create', '/api/v1/open/user/get', '/api/v1/open/user/list',
    '/api/v1/open/node/list', '/api/v1/open/image/list',
    '/api/v1/open/instance/create', '/api/v1/open/instance/get', '/api/v1/open/instance/list',
    '/api/v1/open/instance/action', '/api/v1/open/instance/delete',
] as $p) {
    $router->add('POST', $p, [OpenApiController::class, 'handle']);
}

// ---- 单机 API（凭每台机器的访问 Key） ----
$router->add('POST', '/api/v1/instance/info',   [InstanceApiController::class, 'handle']);
$router->add('POST', '/api/v1/instance/action', [InstanceApiController::class, 'handle']);

// ---- 首页 ----
$router->get('/', [DashboardController::class, 'home']);

// ---- 管理后台 ----
$router->get('/admin/login',  [AdminAuth::class, 'showLogin']);
$router->post('/admin/login', [AdminAuth::class, 'login']);
$router->get('/admin/logout', [AdminAuth::class, 'logout']);
$router->get('/admin', [DashboardController::class, 'index']);

$router->get('/admin/nodes',              [NodeController::class, 'index']);
$router->post('/admin/nodes',             [NodeController::class, 'store']);
$router->get('/admin/nodes/create',       [NodeController::class, 'create']);
$router->get('/admin/nodes/ssh',          [NodeController::class, 'sshForm']);
$router->post('/admin/nodes/ssh',         [NodeController::class, 'sshInstall']);
$router->get('/admin/nodes/{id}/edit',    [NodeController::class, 'edit']);
$router->post('/admin/nodes/{id}',        [NodeController::class, 'update']);
$router->post('/admin/nodes/{id}/delete', [NodeController::class, 'destroy']);
$router->post('/admin/nodes/{id}/toggle', [NodeController::class, 'toggle']);
$router->post('/admin/nodes/{id}/test',   [NodeController::class, 'test']);

$router->get('/admin/instances',              [AdminInstance::class, 'index']);
$router->get('/admin/instances/create',       [AdminInstance::class, 'create']);
$router->get('/admin/instances/images',       [AdminInstance::class, 'imagesJson']);
$router->post('/admin/instances',             [AdminInstance::class, 'store']);
$router->get('/admin/instances/{id}',         [AdminInstance::class, 'show']);
$router->post('/admin/instances/{id}/action', [AdminInstance::class, 'action']);
$router->post('/admin/instances/{id}/delete', [AdminInstance::class, 'destroy']);

$router->get('/admin/images',  [ImageController::class, 'index']);
$router->get('/admin/storage', [StorageController::class, 'index']);

$router->get('/admin/users',              [AdminUser::class, 'index']);
$router->post('/admin/users',             [AdminUser::class, 'store']);
$router->get('/admin/users/create',       [AdminUser::class, 'create']);
$router->post('/admin/users/{id}/toggle', [AdminUser::class, 'toggle']);
$router->post('/admin/users/{id}/delete', [AdminUser::class, 'destroy']);

$router->get('/admin/logs',      [LogController::class, 'index']);
$router->get('/admin/settings',  [SettingController::class, 'index']);
$router->post('/admin/settings', [SettingController::class, 'update']);
$router->post('/admin/settings/openapi', [SettingController::class, 'regenerateOpenApi']);
$router->get('/admin/about',  [AboutController::class, 'index']);

// ---- 用户面板 ----
$router->get('/user/login',    [ClientAuth::class, 'showLogin']);
$router->post('/user/login',   [ClientAuth::class, 'login']);
$router->get('/user/register', [ClientAuth::class, 'showRegister']);
$router->post('/user/register',[ClientAuth::class, 'register']);
$router->get('/user/logout',   [ClientAuth::class, 'logout']);
$router->get('/user',          [ClientDashboard::class, 'index']);

$router->get('/user/instances',              [ClientInstance::class, 'index']);
$router->get('/user/instances/create',       [ClientInstance::class, 'create']);
$router->get('/user/instances/images',       [ClientInstance::class, 'imagesJson']);
$router->post('/user/instances',             [ClientInstance::class, 'store']);
$router->get('/user/instances/{id}',         [ClientInstance::class, 'show']);
$router->post('/user/instances/{id}/action', [ClientInstance::class, 'action']);
$router->post('/user/instances/{id}/delete', [ClientInstance::class, 'destroy']);

$router->dispatch(Http::method(), Http::path());

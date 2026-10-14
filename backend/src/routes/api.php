<?php

declare(strict_types=1);

/**
 * ルート定義。フロントコントローラー（public/index.php）から読み込む。
 */

use AkitoDev\Controllers\AuthController;
use AkitoDev\Controllers\BuildLogController;
use AkitoDev\Controllers\HealthController;
use AkitoDev\Controllers\ProjectController;
use AkitoDev\Utils\Router;

return static function (Router $router): void {
    $router->get('/api/health', [new HealthController(), 'show']);

    $router->post('/api/login', [new AuthController(), 'login']);
    $router->post('/api/logout', [new AuthController(), 'logout']);
    $router->get('/api/session', [new AuthController(), 'show']);

    $router->get('/api/build-logs', [new BuildLogController(), 'index']);
    $router->get('/api/admin/build-logs', [new BuildLogController(), 'indexAll']);
    $router->post('/api/build-logs', [new BuildLogController(), 'store']);
    $router->put('/api/build-logs/{id}', [new BuildLogController(), 'update']);
    $router->delete('/api/build-logs/{id}', [new BuildLogController(), 'destroy']);

    $router->get('/api/projects', [new ProjectController(), 'index']);
    $router->get('/api/projects/slug/{slug}', [new ProjectController(), 'showBySlug']);
    $router->get('/api/projects/{id}', [new ProjectController(), 'show']);
    $router->post('/api/projects', [new ProjectController(), 'store']);
    $router->put('/api/projects/{id}', [new ProjectController(), 'update']);
    $router->delete('/api/projects/{id}', [new ProjectController(), 'destroy']);
};

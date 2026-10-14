<?php

declare(strict_types=1);

/**
 * API のフロントコントローラー（唯一の入口）。
 * ローカル確認:
 *   php -d extension=pdo_pgsql -S 127.0.0.1:8080 -t backend/public backend/public/index.php
 */

use AkitoDev\Utils\ErrorHandler;
use AkitoDev\Utils\Router;

require __DIR__ . '/../src/bootstrap.php';

$router = new Router();

// ルート定義を読み込む
(require BACKEND_ROOT . '/src/routes/api.php')($router);

try {
    $router->dispatch(
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        $_SERVER['REQUEST_URI'] ?? '/',
    );
} catch (Throwable $exception) {
    ErrorHandler::handle($exception);
}

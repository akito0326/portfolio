<?php

declare(strict_types=1);

namespace AkitoDev\Controllers;

use AkitoDev\Utils\Database;
use AkitoDev\Utils\DatabaseException;
use AkitoDev\Utils\JsonResponse;
use AkitoDev\Utils\Logger;
use PDOException;

/**
 * GET /api/health — DB 接続の確認用。
 */
final class HealthController
{
    /**
     * @param array<string, string> $params
     */
    public function show(array $params): void
    {
        try {
            // 実際に問い合わせて接続を確認する
            Database::connection()->query('SELECT 1');
        } catch (DatabaseException | PDOException $exception) {
            Logger::warning('データベースへ接続できませんでした。', ['exception' => $exception::class]);

            JsonResponse::send([
                'status' => 'error',
                'database' => 'disconnected',
            ], 503);

            return;
        }

        JsonResponse::send([
            'status' => 'ok',
            'database' => 'connected',
        ]);
    }
}

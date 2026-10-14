<?php

declare(strict_types=1);

namespace AkitoDev\Utils;

use PDO;
use PDOException;
use Throwable;

/**
 * PDO(PostgreSQL) 接続を遅延生成して使い回す。
 * 接続情報は config/database.php が .env から組み立てる。
 */
final class Database
{
    private static ?PDO $connection = null;

    /** @var array{dsn: string, username: string, password: string, options: array<int, mixed>}|null */
    private static ?array $config = null;

    private function __construct()
    {
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = self::config();

        try {
            self::$connection = new PDO(
                $config['dsn'],
                $config['username'],
                $config['password'],
                $config['options'],
            );
        } catch (PDOException $exception) {
            throw new DatabaseException('データベースへ接続できませんでした。', 0, $exception);
        }

        return self::$connection;
    }

    /**
     * 実際に問い合わせて接続可否を判定する。
     */
    public static function isConnected(): bool
    {
        try {
            self::connection()->query('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * 保持している接続を破棄する（テスト用）。
     */
    public static function disconnect(): void
    {
        self::$connection = null;
    }

    /**
     * @return array{dsn: string, username: string, password: string, options: array<int, mixed>}
     */
    private static function config(): array
    {
        if (self::$config === null) {
            /** @var array{dsn: string, username: string, password: string, options: array<int, mixed>} $config */
            $config = require BACKEND_ROOT . '/config/database.php';

            self::$config = $config;
        }

        return self::$config;
    }
}

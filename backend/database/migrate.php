<?php

declare(strict_types=1);

/**
 * Migration 実行スクリプト。
 *
 *   php -d extension=pdo_pgsql database/migrate.php            # 未適用を実行
 *   php -d extension=pdo_pgsql database/migrate.php --status   # 状態確認のみ
 *
 * 適用済みの migration は schema_migrations で管理し、未適用のものだけを順に実行する。
 */

use AkitoDev\Utils\Database;
use AkitoDev\Utils\DatabaseException;
use AkitoDev\Utils\Logger;

require __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}

const MIGRATIONS_DIR = BACKEND_ROOT . '/database/migrations';

function output(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

try {
    $pdo = Database::connection();
} catch (DatabaseException $exception) {
    $previous = $exception->getPrevious();

    fwrite(STDERR, '[error] データベースへ接続できませんでした。' . PHP_EOL);
    fwrite(STDERR, '        ' . ($previous instanceof Throwable ? $previous->getMessage() : $exception->getMessage()) . PHP_EOL);
    fwrite(STDERR, '        backend/.env の設定と PostgreSQL の起動状態を確認してください。' . PHP_EOL);
    exit(1);
}

// runner 自身が使う管理テーブル
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version    TEXT        PRIMARY KEY,
        applied_at TIMESTAMPTZ NOT NULL DEFAULT now()
    )'
);

$statement = $pdo->prepare('SELECT version FROM schema_migrations');
$statement->execute();

$applied = [];

foreach ($statement->fetchAll() as $row) {
    $applied[(string) $row['version']] = true;
}

$files = glob(MIGRATIONS_DIR . '/*.sql') ?: [];
sort($files, SORT_STRING);

$statusOnly = in_array('--status', $argv, true);
$pendingCount = 0;
$migratedCount = 0;

foreach ($files as $file) {
    $version = basename($file, '.sql');

    if (isset($applied[$version])) {
        output('[applied]  ' . $version);

        continue;
    }

    if ($statusOnly) {
        output('[pending]  ' . $version);
        $pendingCount++;

        continue;
    }

    $sql = file_get_contents($file);

    if ($sql === false || trim($sql) === '') {
        fwrite(STDERR, '[skipped]  ' . $version . ' (内容が空です)' . PHP_EOL);

        continue;
    }

    $pdo->beginTransaction();

    try {
        // migration は複数文を含むため exec でまとめて実行する
        $pdo->exec($sql);

        $insert = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');
        $insert->execute(['version' => $version]);

        $pdo->commit();

        output('[migrated] ' . $version);
        $migratedCount++;
    } catch (Throwable $exception) {
        $pdo->rollBack();

        Logger::error('migration に失敗しました。', ['version' => $version]);
        fwrite(STDERR, '[failed]   ' . $version . ': ' . $exception->getMessage() . PHP_EOL);

        exit(1);
    }
}

if ($statusOnly) {
    output($pendingCount === 0 ? '未適用の migration はありません。' : $pendingCount . ' 件が未適用です。');

    exit(0);
}

output($migratedCount === 0 ? '適用する migration はありませんでした。' : $migratedCount . ' 件の migration を適用しました。');

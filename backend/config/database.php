<?php

declare(strict_types=1);

/**
 * PDO(PostgreSQL) の接続設定。
 * 認証情報はコードへ直書きせず .env から読み込む。
 *
 * @return array{dsn: string, username: string, password: string, options: array<int, mixed>}
 */

use AkitoDev\Utils\Env;

$sslMode = Env::get('DB_SSLMODE');
$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    Env::get('DB_HOST', '127.0.0.1'),
    Env::get('DB_PORT', '5432'),
    Env::get('DB_DATABASE', 'akito_dev'),
);

if ($sslMode !== '') {
    if (!in_array($sslMode, ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'], true)) {
        throw new InvalidArgumentException('DB_SSLMODE に無効な値が指定されています。');
    }

    $dsn .= ';sslmode=' . $sslMode;
}

return [
    'dsn' => $dsn,
    'username' => Env::get('DB_USERNAME', 'postgres'),
    'password' => Env::get('DB_PASSWORD', ''),
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // プリペアドステートメントをサーバー側で実行する（SQLインジェクション対策）
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];

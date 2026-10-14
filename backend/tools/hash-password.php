<?php

declare(strict_types=1);

/**
 * ADMIN_PASSWORD_HASH を生成する CLI。
 *
 *   php tools/hash-password.php "任意のパスワード"
 *
 * 出力を backend/.env の ADMIN_PASSWORD_HASH へ貼り付ける。
 * ハッシュには "$" が含まれるため、.env では次のどちらかで書く。
 *   1) シングルクォートで囲む   例: ADMIN_PASSWORD_HASH='$2y$10$...'
 *   2) "$" を "$$" にエスケープ  例: ADMIN_PASSWORD_HASH=$$2y$$10$$...
 */

require __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$password = $argv[1] ?? '';

if ($password === '') {
    fwrite(STDERR, '使い方: php tools/hash-password.php "パスワード"' . PHP_EOL);
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

if (!is_string($hash)) {
    fwrite(STDERR, 'ハッシュの生成に失敗しました。' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, $hash . PHP_EOL);
fwrite(STDOUT, PHP_EOL);
fwrite(STDOUT, 'backend/.env へ次のように記述してください:' . PHP_EOL) ;
fwrite(STDOUT, 'ADMIN_PASSWORD_HASH=\'' . $hash . '\'' . PHP_EOL);
fwrite(STDOUT, '（"$" をエスケープする場合は ' . str_replace('$', '$$', $hash) . ' と書けます）' . PHP_EOL);

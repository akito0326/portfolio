<?php

declare(strict_types=1);

/**
 * バックエンド共通の起動処理。
 * 外部ライブラリに依存しないため、Composer ではなく最小限のオートローダーを使う。
 */

use AkitoDev\Utils\Env;

// backend/ の絶対パス
define('BACKEND_ROOT', dirname(__DIR__));

// 名前空間 AkitoDev\ を backend/src/ へマッピングする
spl_autoload_register(static function (string $class): void {
    $prefix = 'AkitoDev\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativePath = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = BACKEND_ROOT . '/src/' . $relativePath . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// .env を読み込む（ファイルが無い場合は実環境の変数のみで動作する）
Env::load(BACKEND_ROOT . '/.env');

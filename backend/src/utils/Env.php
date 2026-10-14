<?php

declare(strict_types=1);

namespace AkitoDev\Utils;

/**
 * .env を読み込むための最小限のローダー（外部ライブラリ非依存）。
 * 実際の環境変数が設定されている場合は、そちらを優先する。
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    private static bool $loaded = false;

    private function __construct()
    {
    }

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        if (!is_readable($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            // 空行とコメント行は無視する
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            self::$values[trim($parts[0])] = self::normalize(trim($parts[1]));
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        $fromEnvironment = getenv($key);

        if (is_string($fromEnvironment) && $fromEnvironment !== '') {
            // 環境変数でも "$$" を "$" へ戻し、.env と同じ扱いにする
            return self::unescapeDollars($fromEnvironment);
        }

        return self::$values[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return self::get($key) !== '';
    }

    /**
     * 値を囲むクォートを外し、"$$" を "$" へ戻す。
     *
     * password_hash() の結果は "$2y$10$..." のように "$" を含む。
     * docker compose やシェルの変数展開を避けるため .env では次のどちらかで書ける。
     *   1) シングルクォートで囲む   例: ADMIN_PASSWORD_HASH='$2y$10$...'
     *   2) "$" を "$$" にエスケープ  例: ADMIN_PASSWORD_HASH=$$2y$$10$$...
     */
    private static function normalize(string $value): string
    {
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        return self::unescapeDollars($value);
    }

    /**
     * "$$" を "$" へ戻す（password_hash の "$2y$10$..." 等を元の値にする）。
     */
    private static function unescapeDollars(string $value): string
    {
        return str_replace('$$', '$', $value);
    }
}

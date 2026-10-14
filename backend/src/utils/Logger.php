<?php

declare(strict_types=1);

namespace AkitoDev\Utils;

/**
 * エラーログ出力。
 * レスポンスへ内部情報を含めない代わりに、ここへ記録する。
 */
final class Logger
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function error(string $message, array $context = []): void
    {
        error_log(self::format('ERROR', $message, $context));
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function warning(string $message, array $context = []): void
    {
        error_log(self::format('WARNING', $message, $context));
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function format(string $level, string $message, array $context): string
    {
        $line = sprintf('[%s] %s %s', date('Y-m-d H:i:s'), $level, $message);

        if ($context !== []) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $line;
    }
}

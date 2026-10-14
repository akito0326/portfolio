<?php

declare(strict_types=1);

namespace AkitoDev\Utils;

/**
 * JSON レスポンスの出力。
 * 成功時はリソースをそのまま返し、失敗時は error オブジェクトを返す。
 */
final class JsonResponse
{
    private const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;

    private function __construct()
    {
    }

    public static function send(mixed $payload, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($payload, self::FLAGS) . PHP_EOL;
    }

    public static function error(string $code, string $message, int $status, ?string $detail = null): void
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($detail !== null && $detail !== '') {
            $error['detail'] = $detail;
        }

        self::send(['error' => $error], $status);
    }
}

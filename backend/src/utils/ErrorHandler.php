<?php

declare(strict_types=1);

namespace AkitoDev\Utils;

use Throwable;

/**
 * 例外を HTTP エラーレスポンスへ変換する。
 * 詳細（DB接続エラーなど）はログへ記録し、APP_ENV=development 以外ではレスポンスへ含めない。
 */
final class ErrorHandler
{
    private function __construct()
    {
    }

    public static function handle(Throwable $exception): void
    {
        if ($exception instanceof HttpException) {
            JsonResponse::error($exception->errorCode(), $exception->getMessage(), $exception->status());

            return;
        }

        if ($exception instanceof DatabaseException) {
            Logger::error($exception->getMessage(), ['exception' => self::describe($exception)]);

            JsonResponse::error(
                'database_unavailable',
                'データベースへ接続できませんでした。',
                503,
                self::detail($exception),
            );

            return;
        }

        Logger::error($exception->getMessage(), ['exception' => self::describe($exception)]);

        JsonResponse::error('internal_error', 'サーバー内部エラーが発生しました。', 500, self::detail($exception));
    }

    private static function detail(Throwable $exception): ?string
    {
        if (Env::get('APP_ENV', 'production') !== 'development') {
            return null;
        }

        return $exception->getMessage() . ' @ ' . self::describe($exception);
    }

    private static function describe(Throwable $exception): string
    {
        return $exception::class . ' in ' . $exception->getFile() . ':' . $exception->getLine();
    }
}

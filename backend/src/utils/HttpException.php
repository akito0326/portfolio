<?php

declare(strict_types=1);

namespace AkitoDev\Utils;

use RuntimeException;

/**
 * HTTP ステータスとエラーコードを持つ例外。
 */
final class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $status,
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}

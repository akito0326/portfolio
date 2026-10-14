<?php

declare(strict_types=1);

namespace AkitoDev\Utils;

use RuntimeException;

/**
 * DB へ接続できない場合に投げる例外。HTTP では 503 として扱う。
 */
final class DatabaseException extends RuntimeException
{
}

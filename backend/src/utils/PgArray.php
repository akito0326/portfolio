<?php

declare(strict_types=1);

namespace AkitoDev\Utils;

/**
 * PHP の配列を PostgreSQL の配列リテラルへ変換する。
 * PDO は配列を直接バインドできないため、text[] へ CAST する際に使う。
 */
final class PgArray
{
    private function __construct()
    {
    }

    /**
     * @param list<string> $values
     */
    public static function toLiteral(array $values): string
    {
        $items = array_map(
            static fn (string $value): string => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"',
            array_values($values),
        );

        return '{' . implode(',', $items) . '}';
    }
}

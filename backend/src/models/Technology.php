<?php

declare(strict_types=1);

namespace AkitoDev\Models;

/**
 * technologies テーブルの1行。
 * category は既存 JSON に情報が無いため現状は常に null。
 */
final class Technology
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $category,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            name: (string) ($row['name'] ?? ''),
            category: isset($row['category']) ? (string) $row['category'] : null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }
}

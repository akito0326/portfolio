<?php

declare(strict_types=1);

namespace AkitoDev\Models;

/**
 * skills テーブルの1行。
 * category に既存 JSON のグループ名（Frontend など）、name に項目名が入る。
 */
final class Skill
{
    public function __construct(
        public readonly int $id,
        public readonly string $category,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $sortOrder,
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
            category: (string) ($row['category'] ?? ''),
            name: (string) ($row['name'] ?? ''),
            description: isset($row['description']) ? (string) $row['description'] : null,
            sortOrder: (int) ($row['sort_order'] ?? 0),
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }
}

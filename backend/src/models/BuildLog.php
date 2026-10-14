<?php

declare(strict_types=1);

namespace AkitoDev\Models;

/**
 * build_logs テーブルの1行。
 *
 * toArray() は既存フロントエンド（src/types/content.ts の BuildLog 型）が使う
 * date / title / tag を含む形を返す。date は logged_at（DATE）を "2026.09" へ整形した
 * 表示用の値。管理画面は logged_at / sort_order / is_public を元データとして使う。
 */
final class BuildLog
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $title,
        public readonly string $tag,
        public readonly string $loggedAt,
        public readonly int $sortOrder,
        public readonly bool $isPublic,
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
            id: isset($row['id']) ? (int) $row['id'] : null,
            title: (string) ($row['title'] ?? ''),
            tag: (string) ($row['tag'] ?? ''),
            loggedAt: (string) ($row['logged_at'] ?? ''),
            sortOrder: (int) ($row['sort_order'] ?? 0),
            isPublic: self::toBool($row['is_public'] ?? true),
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }

    /**
     * フロント互換（date / title / tag）と管理画面用の元データを返す。
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'date' => self::formatDate($this->loggedAt),
            'title' => $this->title,
            'tag' => $this->tag,
            'logged_at' => $this->loggedAt,
            'sort_order' => $this->sortOrder,
            'is_public' => $this->isPublic,
        ];
    }

    /**
     * "2026-09-01" を既存 JSON と同じ "2026.09" 表記へ整形する。
     */
    private static function formatDate(string $value): string
    {
        if (preg_match('/^(\d{4})-(\d{2})/', $value, $matches) !== 1) {
            return $value;
        }

        return $matches[1] . '.' . $matches[2];
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        // 環境によって boolean が 't' / 'f' の文字列で返るため両方を受け付ける
        return in_array($value, [1, '1', 't', 'true'], true);
    }
}

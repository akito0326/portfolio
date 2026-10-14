<?php

declare(strict_types=1);

namespace AkitoDev\Models;

/**
 * projects テーブルの1行。
 *
 * toArray() は既存フロントエンド（src/types/content.ts の Project 型）と同じ形を返す。
 * 次のフェーズで React を API 接続へ切り替える際、レスポンスをそのまま使えるようにするため。
 */
final class Project
{
    /**
     * @param list<string> $features
     * @param list<string> $architecture
     * @param list<string> $stack
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $displayOrder,
        public readonly string $slug,
        public readonly string $title,
        public readonly string $description,
        public readonly string $accent,
        public readonly string $visual,
        public readonly ?string $overview,
        public readonly ?string $problem,
        public readonly ?string $solution,
        public readonly array $features,
        public readonly array $architecture,
        public readonly string $githubUrl,
        public readonly string $demoUrl,
        public readonly array $stack = [],
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    /**
     * DB の行から生成する。
     * features / architecture は PostgreSQL の text[] を to_json() した JSON 文字列を受け取る。
     *
     * @param array<string, mixed> $row
     * @param list<string> $stack
     */
    public static function fromRow(array $row, array $stack = []): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            displayOrder: (int) ($row['display_order'] ?? 0),
            slug: (string) ($row['slug'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            description: (string) ($row['description'] ?? ''),
            accent: (string) ($row['accent'] ?? ''),
            visual: (string) ($row['visual'] ?? ''),
            overview: self::nullableString($row['overview'] ?? null),
            problem: self::nullableString($row['problem'] ?? null),
            solution: self::nullableString($row['solution'] ?? null),
            features: self::stringList($row['features'] ?? null),
            architecture: self::stringList($row['architecture'] ?? null),
            githubUrl: (string) ($row['github_url'] ?? ''),
            demoUrl: (string) ($row['demo_url'] ?? ''),
            stack: $stack,
            createdAt: self::nullableString($row['created_at'] ?? null),
            updatedAt: self::nullableString($row['updated_at'] ?? null),
        );
    }

    /**
     * 既存 Project 型に合わせた形。
     * 任意項目は null のときキーごと省略する（型が string | undefined のため）。
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $project = [
            'id' => $this->id,
            'number' => str_pad((string) $this->displayOrder, 2, '0', STR_PAD_LEFT),
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'stack' => $this->stack,
            'accent' => $this->accent,
            'visual' => $this->visual,
        ];

        $optional = [
            'overview' => $this->overview,
            'problem' => $this->problem,
            'solution' => $this->solution,
        ];

        foreach ($optional as $key => $value) {
            if ($value !== null) {
                $project[$key] = $value;
            }
        }

        $project['features'] = $this->features;
        $project['architecture'] = $this->architecture;
        $project['links'] = [
            'github' => $this->githubUrl,
            'demo' => $this->demoUrl,
        ];
        $project['created_at'] = $this->createdAt;
        $project['updated_at'] = $this->updatedAt;

        return $project;
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $decoded));
    }
}

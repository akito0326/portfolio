<?php

declare(strict_types=1);

namespace AkitoDev\Repositories;

use AkitoDev\Models\Technology;
use AkitoDev\Utils\Database;
use PDO;

/**
 * technologies の読み書き。
 */
final class TechnologyRepository
{
    private ?PDO $pdo = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * 技術名を登録し、name => id の対応表を返す。
     * category は既存データに情報が無いため指定しない（NULL）。
     *
     * @param list<string> $names
     * @return array<string, int>
     */
    public function upsertMany(array $names): array
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO technologies (name)
             VALUES (:name)
             ON CONFLICT (name) DO UPDATE SET name = EXCLUDED.name
             RETURNING id'
        );

        $ids = [];

        foreach (array_unique($names) as $name) {
            $statement->execute(['name' => $name]);
            $ids[$name] = (int) $statement->fetchColumn();
        }

        return $ids;
    }

    /**
     * @return list<Technology>
     */
    public function all(): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT id, name, category, created_at, updated_at FROM technologies ORDER BY name'
        );
        $statement->execute();

        return array_map(
            static fn (array $row): Technology => Technology::fromRow($row),
            $statement->fetchAll(),
        );
    }

    private function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = Database::connection();
        }

        return $this->pdo;
    }
}

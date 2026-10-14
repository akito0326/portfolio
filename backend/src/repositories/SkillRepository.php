<?php

declare(strict_types=1);

namespace AkitoDev\Repositories;

use AkitoDev\Models\Skill;
use AkitoDev\Utils\Database;
use PDO;

/**
 * skills の読み書き。
 */
final class SkillRepository
{
    private ?PDO $pdo = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * (category, name) を一意キーとして登録・更新する。
     * description は既存データに無いため null を渡す。
     *
     * @param list<array{category: string, name: string, description: ?string, sort_order: int}> $skills
     */
    public function upsertMany(array $skills): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO skills (category, name, description, sort_order)
             VALUES (:category, :name, :description, :sort_order)
             ON CONFLICT (category, name) DO UPDATE SET
                 description = EXCLUDED.description,
                 sort_order  = EXCLUDED.sort_order'
        );

        $affected = 0;

        foreach ($skills as $skill) {
            $statement->execute([
                'category' => $skill['category'],
                'name' => $skill['name'],
                'description' => $skill['description'],
                'sort_order' => $skill['sort_order'],
            ]);

            $affected += $statement->rowCount();
        }

        return $affected;
    }

    /**
     * @return list<Skill>
     */
    public function all(): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT id, category, name, description, sort_order, created_at, updated_at
             FROM skills
             ORDER BY sort_order, id'
        );
        $statement->execute();

        return array_map(
            static fn (array $row): Skill => Skill::fromRow($row),
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

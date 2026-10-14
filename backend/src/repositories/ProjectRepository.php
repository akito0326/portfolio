<?php

declare(strict_types=1);

namespace AkitoDev\Repositories;

use AkitoDev\Models\Project;
use AkitoDev\Repositories\TechnologyRepository;
use AkitoDev\Utils\Database;
use AkitoDev\Utils\HttpException;
use AkitoDev\Utils\PgArray;
use PDO;
use PDOException;

/**
 * projects / project_technologies / technologies の読み書き。
 * SQL は文字列連結せず、常に prepared statement を使う。
 */
final class ProjectRepository
{
    /**
     * PostgreSQL の text[] は PDO で配列として取得できないため、SQL 側で JSON 文字列へ変換する。
     */
    private const COLUMNS = <<<'SQL'
        id,
        display_order,
        slug,
        title,
        description,
        accent,
        visual,
        overview,
        problem,
        solution,
        to_json(features)::text     AS features,
        to_json(architecture)::text AS architecture,
        github_url,
        demo_url,
        created_at,
        updated_at
        SQL;

    private ?PDO $pdo = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return list<Project>
     */
    public function all(): array
    {
        $statement = $this->pdo()->prepare('SELECT ' . self::COLUMNS . ' FROM projects ORDER BY display_order');
        $statement->execute();
        $rows = $statement->fetchAll();

        if ($rows === []) {
            return [];
        }

        $stacks = $this->stacksByProject(array_map(static fn (array $row): int => (int) $row['id'], $rows));

        return array_map(
            static fn (array $row): Project => Project::fromRow($row, $stacks[(int) $row['id']] ?? []),
            $rows,
        );
    }

    public function findById(int $id): ?Project
    {
        $statement = $this->pdo()->prepare('SELECT ' . self::COLUMNS . ' FROM projects WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return Project::fromRow($row, $this->stacksByProject([$id])[$id] ?? []);
    }

    public function findBySlug(string $slug): ?Project
    {
        $statement = $this->pdo()->prepare('SELECT ' . self::COLUMNS . ' FROM projects WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        $id = (int) $row['id'];

        return Project::fromRow($row, $this->stacksByProject([$id])[$id] ?? []);
    }

    /**
     * プロジェクトを登録し、projects.id を返す。
     * stack（技術名リスト）がある場合は technologies へ upsert して紐付ける。
     *
     * 一意制約違反は 409 / 404 へ変換する（コントローラーの共通処理に任せない）。
     * 理由: DB の制約名からエラーコードへの対応付けはこの層の知識のため。
     *
     * @param array<string, mixed> $attributes
     * @param list<string> $stack
     */
    public function create(array $attributes, array $stack = []): int
    {
        try {
            $id = $this->insert($attributes);
        } catch (PDOException $exception) {
            throw self::toUniqueViolation($exception);
        }

        $this->syncStackNames($id, $stack);

        return $id;
    }

    /**
     * プロジェクトを更新する。
     *
     * @param array<string, mixed> $attributes
     * @param list<string>|null $stack null のときは技術の紐付けを変更しない
     */
    public function update(int $id, array $attributes, ?array $stack = null): void
    {
        $parameters = [
            'id' => $id,
            'display_order' => $attributes['display_order'],
            'slug' => $attributes['slug'],
            'title' => $attributes['title'],
            'description' => $attributes['description'],
            'accent' => $attributes['accent'],
            'visual' => $attributes['visual'],
            'overview' => $attributes['overview'],
            'problem' => $attributes['problem'],
            'solution' => $attributes['solution'],
            'features' => PgArray::toLiteral($attributes['features']),
            'architecture' => PgArray::toLiteral($attributes['architecture']),
            'github_url' => $attributes['github_url'],
            'demo_url' => $attributes['demo_url'],
        ];

        try {
            $statement = $this->pdo()->prepare(
                'UPDATE projects SET
                    display_order = :display_order, slug = :slug, title = :title,
                    description = :description, accent = :accent, visual = :visual,
                    overview = :overview, problem = :problem, solution = :solution,
                    features = :features, architecture = :architecture,
                    github_url = :github_url, demo_url = :demo_url
                 WHERE id = :id'
            );
            $statement->execute($parameters);
        } catch (PDOException $exception) {
            throw self::toUniqueViolation($exception);
        }

        if ($statement->rowCount() === 0) {
            throw new HttpException(404, 'project_not_found', 'project id=' . $id . ' は存在しません。');
        }

        if ($stack !== null) {
            $this->syncStackNames($id, $stack);
        }
    }

    /**
     * プロジェクトを削除する。
     * project_technologies の削除と本体の削除を1トランザクションで行う。
     */
    public function delete(int $id): void
    {
        $pdo = $this->pdo();

        $pdo->beginTransaction();

        try {
            $deleteRelations = $pdo->prepare('DELETE FROM project_technologies WHERE project_id = :project_id');
            $deleteRelations->execute(['project_id' => $id]);

            $deleteProject = $pdo->prepare('DELETE FROM projects WHERE id = :id');
            $deleteProject->execute(['id' => $id]);

            if ($deleteProject->rowCount() === 0) {
                $pdo->rollBack();

                throw new HttpException(404, 'project_not_found', 'project id=' . $id . ' は存在しません。');
            }

            $pdo->commit();
        } catch (HttpException $exception) {
            throw $exception;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * プロジェクトを登録し、projects.id を返す。
     * updated_at は set_updated_at() トリガーが更新する。
     *
     * @param array<string, mixed> $attributes
     */
    private function insert(array $attributes): int
    {
        $statement = $this->pdo()->prepare(
            <<<'SQL'
            INSERT INTO projects (
                display_order, slug, title, description, accent, visual,
                overview, problem, solution,
                features, architecture, github_url, demo_url
            ) VALUES (
                :display_order, :slug, :title, :description, :accent, :visual,
                :overview, :problem, :solution,
                :features, :architecture, :github_url, :demo_url
            )
            RETURNING id
            SQL
        );

        $statement->execute([
            'display_order' => $attributes['display_order'],
            'slug' => $attributes['slug'],
            'title' => $attributes['title'],
            'description' => $attributes['description'],
            'accent' => $attributes['accent'],
            'visual' => $attributes['visual'],
            'overview' => $attributes['overview'],
            'problem' => $attributes['problem'],
            'solution' => $attributes['solution'],
            'features' => PgArray::toLiteral($attributes['features']),
            'architecture' => PgArray::toLiteral($attributes['architecture']),
            'github_url' => $attributes['github_url'],
            'demo_url' => $attributes['demo_url'],
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * 技術名リストの検証済み配列を technologies へ upsert し、プロジェクトへ紐付ける。
     *
     * @param list<string> $names
     */
    private function syncStackNames(int $projectId, array $names): void
    {
        if ($names === []) {
            $this->syncTechnologies($projectId, []);

            return;
        }

        $ids = (new TechnologyRepository($this->pdo()))->upsertMany($names);

        $technologyIds = [];

        foreach ($names as $name) {
            if (isset($ids[$name])) {
                $technologyIds[] = $ids[$name];
            }
        }

        $this->syncTechnologies($projectId, $technologyIds);
    }

    /**
     * PostgreSQL の一意制約違反（SQLSTATE 23505）を 409 へ変換する。
     */
    private static function toUniqueViolation(PDOException $exception): HttpException
    {
        if ($exception->getCode() === '23505') {
            $message = (string) $exception->getMessage();

            if (str_contains($message, 'projects_slug_key')) {
                return new HttpException(409, 'slug_conflict', 'この slug は既に使われています。');
            }

            if (str_contains($message, 'projects_display_order_key')) {
                return new HttpException(409, 'display_order_conflict', 'この表示順は既に使われています。');
            }

            return new HttpException(409, 'project_conflict', '重複する値があります。');
        }

        throw $exception;
    }

    /**
     * プロジェクトと技術の関連を、指定の並び順で置き換える。
     *
     * @param list<int> $technologyIds
     */
    public function syncTechnologies(int $projectId, array $technologyIds): void
    {
        $pdo = $this->pdo();

        $delete = $pdo->prepare('DELETE FROM project_technologies WHERE project_id = :project_id');
        $delete->execute(['project_id' => $projectId]);

        if ($technologyIds === []) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO project_technologies (project_id, technology_id, sort_order)
             VALUES (:project_id, :technology_id, :sort_order)
             ON CONFLICT (project_id, technology_id) DO UPDATE SET sort_order = EXCLUDED.sort_order'
        );

        foreach (array_values($technologyIds) as $index => $technologyId) {
            $insert->execute([
                'project_id' => $projectId,
                'technology_id' => $technologyId,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * project_id => [技術名, ...] を表示順で取得する。
     *
     * @param list<int> $projectIds
     * @return array<int, list<string>>
     */
    private function stacksByProject(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $placeholders = [];
        $parameters = [];

        foreach (array_values($projectIds) as $index => $projectId) {
            $name = 'project_id_' . $index;
            $placeholders[] = ':' . $name;
            $parameters[$name] = $projectId;
        }

        $statement = $this->pdo()->prepare(
            'SELECT pt.project_id, t.name
             FROM project_technologies pt
             INNER JOIN technologies t ON t.id = pt.technology_id
             WHERE pt.project_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY pt.project_id, pt.sort_order, t.name'
        );
        $statement->execute($parameters);

        $stacks = [];

        foreach ($statement->fetchAll() as $row) {
            $stacks[(int) $row['project_id']][] = (string) $row['name'];
        }

        return $stacks;
    }

    private function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = Database::connection();
        }

        return $this->pdo;
    }
}

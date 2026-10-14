<?php

declare(strict_types=1);

namespace AkitoDev\Repositories;

use AkitoDev\Models\BuildLog;
use AkitoDev\Utils\Database;
use AkitoDev\Utils\HttpException;
use PDO;
use PDOException;

/**
 * build_logs の読み書き。
 * SQL は文字列連結せず、常に prepared statement を使う。
 */
final class BuildLogRepository
{
    private const COLUMNS = 'id, title, tag, logged_at, sort_order, is_public, created_at, updated_at';

    private ?PDO $pdo = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * 公開分のみを表示順で取得する（GET /api/build-logs）。
     *
     * @return list<BuildLog>
     */
    public function allPublic(): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM build_logs
             WHERE is_public = TRUE
             ORDER BY sort_order, logged_at DESC, id'
        );
        $statement->execute();

        return array_map(
            static fn (array $row): BuildLog => BuildLog::fromRow($row),
            $statement->fetchAll(),
        );
    }

    /**
     * 公開・非公開を問わず全件を取得する（管理画面）。
     *
     * @return list<BuildLog>
     */
    public function all(): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM build_logs
             ORDER BY sort_order, logged_at DESC, id'
        );
        $statement->execute();

        return array_map(
            static fn (array $row): BuildLog => BuildLog::fromRow($row),
            $statement->fetchAll(),
        );
    }

    public function findById(int $id): ?BuildLog
    {
        $statement = $this->pdo()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM build_logs WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return BuildLog::fromRow($row);
    }

    /**
     * Build Log を登録し、build_logs.id を返す。
     * (logged_at, title) の重複は 409 build_log_conflict へ変換する。
     *
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): int
    {
        try {
            $statement = $this->pdo()->prepare(
                'INSERT INTO build_logs (title, tag, logged_at, sort_order, is_public)
                 VALUES (:title, :tag, :logged_at, :sort_order, :is_public)
                 RETURNING id'
            );
            $statement->execute([
                'title' => $attributes['title'],
                'tag' => $attributes['tag'],
                'logged_at' => $attributes['logged_at'],
                'sort_order' => $attributes['sort_order'],
                'is_public' => $attributes['is_public'] ? 'true' : 'false',
            ]);

            return (int) $statement->fetchColumn();
        } catch (PDOException $exception) {
            throw self::toUniqueViolation($exception);
        }
    }

    /**
     * Build Log を更新する。
     *
     * @param array<string, mixed> $attributes
     */
    public function update(int $id, array $attributes): void
    {
        try {
            $statement = $this->pdo()->prepare(
                'UPDATE build_logs SET
                    title = :title, tag = :tag, logged_at = :logged_at,
                    sort_order = :sort_order, is_public = :is_public
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'title' => $attributes['title'],
                'tag' => $attributes['tag'],
                'logged_at' => $attributes['logged_at'],
                'sort_order' => $attributes['sort_order'],
                'is_public' => $attributes['is_public'] ? 'true' : 'false',
            ]);
        } catch (PDOException $exception) {
            throw self::toUniqueViolation($exception);
        }

        if ($statement->rowCount() === 0) {
            throw new HttpException(404, 'build_log_not_found', 'build log id=' . $id . ' は存在しません。');
        }
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo()->prepare('DELETE FROM build_logs WHERE id = :id');
        $statement->execute(['id' => $id]);

        if ($statement->rowCount() === 0) {
            throw new HttpException(404, 'build_log_not_found', 'build log id=' . $id . ' は存在しません。');
        }
    }

    /**
     * PostgreSQL の一意制約違反（SQLSTATE 23505）を 409 へ変換する。
     */
    private static function toUniqueViolation(PDOException $exception): HttpException
    {
        if ($exception->getCode() === '23505') {
            return new HttpException(409, 'build_log_conflict', '同じ日付とタイトルの Build Log は既にあります。');
        }

        throw $exception;
    }

    /**
     * (logged_at, title) を一意キーとして登録する（seed 用）。
     * 既に同じ行がある場合は上書きしない（ON CONFLICT DO NOTHING）。
     * 管理画面で編集した内容を seed の再実行が壊さないようにするため。
     *
     * @param list<array{
     *     title: string, tag: string, logged_at: string, sort_order: int, is_public: bool
     * }> $logs
     */
    public function upsertMany(array $logs): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO build_logs (title, tag, logged_at, sort_order, is_public)
             VALUES (:title, :tag, :logged_at, :sort_order, :is_public)
             ON CONFLICT (logged_at, title) DO NOTHING'
        );

        $affected = 0;

        foreach ($logs as $log) {
            $statement->execute([
                'title' => $log['title'],
                'tag' => $log['tag'],
                'logged_at' => $log['logged_at'],
                'sort_order' => $log['sort_order'],
                // PDO は bool の false を '' へ変換してしまうため、
                // PostgreSQL が boolean として解釈できるリテラルへ変換する
                'is_public' => $log['is_public'] ? 'true' : 'false',
            ]);

            $affected += $statement->rowCount();
        }

        return $affected;
    }

    private function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = Database::connection();
        }

        return $this->pdo;
    }
}

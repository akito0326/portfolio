<?php

declare(strict_types=1);

namespace AkitoDev\Controllers;

use AkitoDev\Models\BuildLog;
use AkitoDev\Repositories\BuildLogRepository;
use AkitoDev\Utils\HttpException;
use AkitoDev\Utils\JsonResponse;
use AkitoDev\Utils\Request;
use AkitoDev\Utils\Session;

/**
 * GET /api/build-logs
 *
 * 公開分（is_public = true）のみを表示順（sort_order 昇順）で返す。
 *
 * GET /api/admin/build-logs（ログイン必須）
 * POST /api/build-logs, PUT /api/build-logs/{id}, DELETE /api/build-logs/{id}（ログイン必須）
 */
final class BuildLogController
{
    private BuildLogRepository $buildLogs;

    public function __construct(?BuildLogRepository $buildLogs = null)
    {
        $this->buildLogs = $buildLogs ?? new BuildLogRepository();
    }

    /**
     * @param array<string, string> $params
     */
    public function index(array $params): void
    {
        $logs = array_map(
            static fn (BuildLog $log): array => $log->toArray(),
            $this->buildLogs->allPublic(),
        );

        JsonResponse::send($logs);
    }

    /**
     * 公開・非公開を問わず全件を表示順で返す（管理画面の一覧用）。
     *
     * @param array<string, string> $params
     */
    public function indexAll(array $params): void
    {
        Session::requireAuth();

        $logs = array_map(
            static fn (BuildLog $log): array => $log->toArray(),
            $this->buildLogs->all(),
        );

        JsonResponse::send($logs);
    }

    /**
     * Build Log を登録する。保存後の1件を 201 で返す。
     *
     * @param array<string, string> $params
     */
    public function store(array $params): void
    {
        Session::requireAuth();

        $attributes = self::validateAttributes(Request::json());

        $log = $this->buildLogs->findById($this->buildLogs->create($attributes));

        if ($log === null) {
            throw new HttpException(500, 'build_log_create_failed', 'Build Log の登録に失敗しました。');
        }

        JsonResponse::send($log->toArray(), 201);
    }

    /**
     * Build Log を更新する。保存後の1件を 200 で返す。
     *
     * @param array<string, string> $params
     */
    public function update(array $params): void
    {
        Session::requireAuth();

        $id = self::parseId($params['id'] ?? '');

        $this->buildLogs->update($id, self::validateAttributes(Request::json()));

        $log = $this->buildLogs->findById($id);

        if ($log === null) {
            throw new HttpException(404, 'build_log_not_found', 'build log id=' . $id . ' は存在しません。');
        }

        JsonResponse::send($log->toArray());
    }

    /**
     * Build Log を削除する。
     *
     * @param array<string, string> $params
     */
    public function destroy(array $params): void
    {
        Session::requireAuth();

        $id = self::parseId($params['id'] ?? '');

        $this->buildLogs->delete($id);

        JsonResponse::send(['deleted' => true, 'id' => $id]);
    }

    /**
     * 入力の検証と正規化を行う。
     * sort_order の既定は 0、is_public の既定は true。
     *
     * @param array<string, mixed> $payload
     * @return array{title: string, tag: string, logged_at: string, sort_order: int, is_public: bool}
     */
    private static function validateAttributes(array $payload): array
    {
        $title = is_string($payload['title'] ?? null) ? trim($payload['title']) : '';
        $tag = is_string($payload['tag'] ?? null) ? trim($payload['tag']) : '';
        $loggedAt = is_string($payload['logged_at'] ?? null) ? trim($payload['logged_at']) : '';

        if ($title === '' || mb_strlen($title) > 200) {
            throw new HttpException(400, 'validation_error', 'title は 1〜200 文字で指定してください。');
        }

        if ($tag === '' || mb_strlen($tag) > 50) {
            throw new HttpException(400, 'validation_error', 'tag は 1〜50 文字で指定してください。');
        }

        // "2026-09-01" のような実在する日付だけを受け付ける
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $loggedAt, $matches) !== 1 || checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]) === false) {
            throw new HttpException(400, 'validation_error', 'logged_at は YYYY-MM-DD の日付で指定してください。');
        }

        $sortOrder = $payload['sort_order'] ?? 0;

        if (is_bool($sortOrder)) {
            throw new HttpException(400, 'validation_error', 'sort_order は整数で指定してください。');
        }

        if (is_string($sortOrder) && ctype_digit($sortOrder)) {
            $sortOrder = (int) $sortOrder;
        }

        if (!is_int($sortOrder) || $sortOrder < -9999 || $sortOrder > 9999) {
            throw new HttpException(400, 'validation_error', 'sort_order は -9999〜9999 の整数で指定してください。');
        }

        $isPublic = $payload['is_public'] ?? true;

        if (is_string($isPublic)) {
            $normalized = strtolower(trim($isPublic));

            if (in_array($normalized, ['true', '1', 't', 'yes'], true)) {
                $isPublic = true;
            } elseif (in_array($normalized, ['false', '0', 'f', 'no'], true)) {
                $isPublic = false;
            } else {
                throw new HttpException(400, 'validation_error', 'is_public は true/false で指定してください。');
            }
        }

        if (!is_bool($isPublic)) {
            throw new HttpException(400, 'validation_error', 'is_public は true/false で指定してください。');
        }

        return [
            'title' => $title,
            'tag' => $tag,
            'logged_at' => $loggedAt,
            'sort_order' => $sortOrder,
            'is_public' => $isPublic,
        ];
    }

    private static function parseId(string $rawId): int
    {
        if (ctype_digit($rawId) === false || (int) $rawId < 1) {
            throw new HttpException(400, 'invalid_build_log_id', 'build log id は 1 以上の整数で指定してください。');
        }

        return (int) $rawId;
    }
}
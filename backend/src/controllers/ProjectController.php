<?php

declare(strict_types=1);

namespace AkitoDev\Controllers;

use AkitoDev\Models\Project;
use AkitoDev\Repositories\ProjectRepository;
use AkitoDev\Utils\HttpException;
use AkitoDev\Utils\JsonResponse;
use AkitoDev\Utils\Request;
use AkitoDev\Utils\Session;

/**
 * GET /api/projects, GET /api/projects/{id}, GET /api/projects/slug/{slug}
 * POST /api/projects, PUT /api/projects/{id}, DELETE /api/projects/{id}（ログイン必須）
 */
final class ProjectController
{
    private ProjectRepository $projects;

    public function __construct(?ProjectRepository $projects = null)
    {
        $this->projects = $projects ?? new ProjectRepository();
    }

    /**
     * @param array<string, string> $params
     */
    public function index(array $params): void
    {
        $projects = array_map(
            static fn (Project $project): array => $project->toArray(),
            $this->projects->all(),
        );

        JsonResponse::send($projects);
    }

    /**
     * プロジェクトを登録する。保存後の1件を 201 で返す。
     *
     * @param array<string, string> $params
     */
    public function store(array $params): void
    {
        Session::requireAuth();

        $payload = Request::json();
        $attributes = self::validateAttributes($payload);
        $stack = self::validateStack($payload['stack'] ?? []);

        $project = $this->projects->findById(
            $this->projects->create($attributes, $stack),
        );

        if ($project === null) {
            throw new HttpException(500, 'project_create_failed', 'プロジェクトの登録に失敗しました。');
        }

        JsonResponse::send($project->toArray(), 201);
    }

    /**
     * プロジェクトを更新する。保存後の1件を 200 で返す。
     *
     * @param array<string, string> $params
     */
    public function update(array $params): void
    {
        Session::requireAuth();

        $id = self::parseId($params['id'] ?? '');
        $payload = Request::json();

        $stack = null;

        if (array_key_exists('stack', $payload)) {
            $stack = self::validateStack($payload['stack']);
        }

        $this->projects->update($id, self::validateAttributes($payload), $stack);

        $project = $this->projects->findById($id);

        if ($project === null) {
            throw new HttpException(404, 'project_not_found', 'project id=' . $id . ' は存在しません。');
        }

        JsonResponse::send($project->toArray());
    }

    /**
     * プロジェクトを削除する。
     *
     * @param array<string, string> $params
     */
    public function destroy(array $params): void
    {
        Session::requireAuth();

        $id = self::parseId($params['id'] ?? '');

        $this->projects->delete($id);

        JsonResponse::send(['deleted' => true, 'id' => $id]);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(array $params): void
    {
        $project = $this->projects->findById(self::parseId($params['id'] ?? ''));

        if ($project === null) {
            throw new HttpException(
                404,
                'project_not_found',
                'project id=' . ($params['id'] ?? '') . ' は存在しません。',
            );
        }

        JsonResponse::send($project->toArray());
    }

    /**
     * フロントエンドのルーティング（/works/:slug）に合わせた slug 取得。
     * 未存在の場合は /api/projects/{id} と同じ 404 project_not_found を返す。
     *
     * @param array<string, string> $params
     */
    public function showBySlug(array $params): void
    {
        $slug = trim($params['slug'] ?? '');

        if ($slug === '') {
            throw new HttpException(
                400,
                'invalid_project_slug',
                'project slug を指定してください。',
            );
        }

        $project = $this->projects->findBySlug($slug);

        if ($project === null) {
            throw new HttpException(
                404,
                'project_not_found',
                'project slug=' . $slug . ' は存在しません。',
            );
        }

        JsonResponse::send($project->toArray());
    }

    /**
     * 入力の検証と正規化を行う。
     * features / architecture は改行・カンマ区切りも受け付けない。
     * 厳密な配列（list<string>）だけを受け付ける。
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function validateAttributes(array $payload): array
    {
        $displayOrder = $payload['display_order'] ?? null;

        if (is_string($displayOrder) && ctype_digit($displayOrder)) {
            $displayOrder = (int) $displayOrder;
        }

        if (!is_int($displayOrder) || $displayOrder < 1 || $displayOrder > 9999) {
            throw new HttpException(400, 'validation_error', 'display_order は 1〜9999 の整数で指定してください。');
        }

        $slug = is_string($payload['slug'] ?? null) ? trim($payload['slug']) : '';

        if ($slug === '' || mb_strlen($slug) > 100 || preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) !== 1) {
            throw new HttpException(400, 'validation_error', 'slug は半角英数字とハイフンの 1〜100 文字で指定してください。');
        }

        $textFields = [
            'title' => [1, 200],
            'description' => [1, 1000],
            'accent' => [1, 100],
            'visual' => [1, 100],
        ];

        $attributes = [
            'display_order' => $displayOrder,
            'slug' => $slug,
        ];

        foreach ($textFields as $field => [$min, $max]) {
            $value = is_string($payload[$field] ?? null) ? trim($payload[$field]) : '';
            $length = mb_strlen($value);

            if ($length < $min || $length > $max) {
                throw new HttpException(400, 'validation_error', $field . ' は ' . $min . '〜' . $max . ' 文字で指定してください。');
            }

            $attributes[$field] = $value;
        }

        $optionalFields = ['overview', 'problem', 'solution'];

        foreach ($optionalFields as $field) {
            $raw = $payload[$field] ?? null;

            if ($raw === null || $raw === '') {
                $attributes[$field] = null;

                continue;
            }

            if (!is_string($raw)) {
                throw new HttpException(400, 'validation_error', $field . ' は文字列で指定してください。');
            }

            $value = trim($raw);

            if (mb_strlen($value) > 5000) {
                throw new HttpException(400, 'validation_error', $field . ' は 5000 文字以内で指定してください。');
            }

            $attributes[$field] = $value === '' ? null : $value;
        }

        $attributes['features'] = self::validateStringList($payload['features'] ?? [], 'features');
        $attributes['architecture'] = self::validateStringList($payload['architecture'] ?? [], 'architecture');

        $links = $payload['links'] ?? [];

        if (!is_array($links) || array_is_list($links)) {
            throw new HttpException(400, 'validation_error', 'links はオブジェクトで指定してください。');
        }

        foreach (['github', 'demo'] as $key) {
            $raw = $links[$key] ?? '';
            $value = is_string($raw) ? trim($raw) : '';

            if (mb_strlen($value) > 500) {
                throw new HttpException(400, 'validation_error', 'links.' . $key . ' は 500 文字以内で指定してください。');
            }

            if ($value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false && !str_starts_with($value, '/')) {
                throw new HttpException(400, 'validation_error', 'links.' . $key . ' は URL で指定してください。');
            }

            $attributes[$key === 'github' ? 'github_url' : 'demo_url'] = $value;
        }

        return $attributes;
    }

    /**
     * 一覧系の文字列配列を検証する（features / architecture / stack 用）。
     *
     * @return list<string>
     */
    private static function validateStringList(mixed $value, string $field, int $maxItems = 50, int $maxLength = 200): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new HttpException(400, 'validation_error', $field . ' は文字列の配列で指定してください。');
        }

        if (count($value) > $maxItems) {
            throw new HttpException(400, 'validation_error', $field . ' は ' . $maxItems . ' 件以内で指定してください。');
        }

        $items = [];

        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new HttpException(400, 'validation_error', $field . ' は文字列の配列で指定してください。');
            }

            $trimmed = trim($item);

            if ($trimmed === '') {
                throw new HttpException(400, 'validation_error', $field . ' に空の要素があります。');
            }

            if (mb_strlen($trimmed) > $maxLength) {
                throw new HttpException(400, 'validation_error', $field . ' は 1 件 ' . $maxLength . ' 文字以内で指定してください。');
            }

            $items[] = $trimmed;
        }

        return $items;
    }

    /**
     * stack（技術名リスト）を検証する。
     *
     * @return list<string>
     */
    private static function validateStack(mixed $value): array
    {
        return self::validateStringList($value, 'stack', 30, 100);
    }

    private static function parseId(string $rawId): int
    {
        if (ctype_digit($rawId) === false || (int) $rawId < 1) {
            throw new HttpException(400, 'invalid_project_id', 'project id は 1 以上の整数で指定してください。');
        }

        return (int) $rawId;
    }
}

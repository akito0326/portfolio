<?php

declare(strict_types=1);

/**
 * Seed 実行スクリプト。
 *
 *   php -d extension=pdo_pgsql database/seed.php
 *
 * 既存の src/data/*.json を読み込み、DB へ投入する。
 * 既存 JSON の変更・削除は一切行わない（読み取りのみ）。
 * ON CONFLICT による upsert のため、何度実行しても重複しない。
 */

use AkitoDev\Repositories\BuildLogRepository;
use AkitoDev\Repositories\ProjectRepository;
use AkitoDev\Repositories\SkillRepository;
use AkitoDev\Repositories\TechnologyRepository;
use AkitoDev\Utils\Database;
use AkitoDev\Utils\DatabaseException;
use AkitoDev\Utils\Env;
use AkitoDev\Utils\Logger;

require __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}

function output(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

/**
 * seed のデータ元。既定はポートフォリオの既存データ（../src/data）。
 */
function resolveDataDirectory(): string
{
    $fromEnv = Env::get('SEED_DATA_DIR');

    if ($fromEnv !== '') {
        return rtrim($fromEnv, "/\\");
    }

    return dirname(BACKEND_ROOT) . '/src/data';
}

/**
 * @return list<array<string, mixed>>
 */
function readJsonList(string $path): array
{
    if (!is_readable($path)) {
        throw new RuntimeException('seed 用の JSON が見つかりません: ' . $path);
    }

    $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($decoded) || !array_is_list($decoded)) {
        throw new RuntimeException('seed 用の JSON は配列である必要があります: ' . $path);
    }

    /** @var list<array<string, mixed>> $decoded */
    return $decoded;
}

/**
 * "2026.09" のような年月表記を、月初の日付へ変換する（build_logs.logged_at 用）。
 */
function toLoggedDate(string $value): ?string
{
    if (preg_match('/^(\d{4})\.(\d{2})$/', trim($value), $matches) !== 1) {
        return null;
    }

    return $matches[1] . '-' . $matches[2] . '-01';
}

function toNullableString(mixed $value): ?string
{
    return is_string($value) && $value !== '' ? $value : null;
}

/**
 * @return list<string>
 */
function toStringList(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    $items = [];

    foreach ($value as $item) {
        if (is_string($item) && $item !== '') {
            $items[] = $item;
        }
    }

    return $items;
}

$dataDirectory = resolveDataDirectory();

try {
    $pdo = Database::connection();
} catch (DatabaseException $exception) {
    $previous = $exception->getPrevious();

    fwrite(STDERR, '[error] データベースへ接続できませんでした。' . PHP_EOL);
    fwrite(STDERR, '        ' . ($previous instanceof Throwable ? $previous->getMessage() : $exception->getMessage()) . PHP_EOL);
    fwrite(STDERR, '        backend/.env の設定と PostgreSQL の起動状態を確認してください。' . PHP_EOL);
    exit(1);
}

$projectRepository = new ProjectRepository($pdo);
$technologyRepository = new TechnologyRepository($pdo);
$skillRepository = new SkillRepository($pdo);
$buildLogRepository = new BuildLogRepository($pdo);

try {
    $pdo->beginTransaction();

    // ---- projects / technologies ----
    $projects = readJsonList($dataDirectory . '/projects.json');

    // stack に登場する技術名を先にすべて登録する
    $stackNames = [];

    foreach ($projects as $project) {
        foreach (toStringList($project['stack'] ?? null) as $name) {
            $stackNames[] = $name;
        }
    }

    $technologyIds = $technologyRepository->upsertMany($stackNames);

    foreach ($projects as $index => $project) {
        $links = is_array($project['links'] ?? null) ? $project['links'] : [];

        $projectId = $projectRepository->upsert([
            'display_order' => (int) ($project['number'] ?? $index + 1),
            'slug' => (string) ($project['slug'] ?? ''),
            'title' => (string) ($project['title'] ?? ''),
            'description' => (string) ($project['description'] ?? ''),
            'accent' => (string) ($project['accent'] ?? ''),
            'visual' => (string) ($project['visual'] ?? ''),
            'overview' => toNullableString($project['overview'] ?? null),
            'problem' => toNullableString($project['problem'] ?? null),
            'solution' => toNullableString($project['solution'] ?? null),
            'features' => toStringList($project['features'] ?? null),
            'architecture' => toStringList($project['architecture'] ?? null),
            'github_url' => (string) ($links['github'] ?? ''),
            'demo_url' => (string) ($links['demo'] ?? ''),
        ]);

        $technologyIdsOfProject = [];

        foreach (toStringList($project['stack'] ?? null) as $name) {
            if (isset($technologyIds[$name])) {
                $technologyIdsOfProject[] = $technologyIds[$name];
            }
        }

        // stack の並び順をそのまま保存する
        $projectRepository->syncTechnologies($projectId, $technologyIdsOfProject);
    }

    // ---- skills ----
    $skillRows = [];
    $sortOrder = 0;

    foreach (readJsonList($dataDirectory . '/skills.json') as $group) {
        $category = (string) ($group['title'] ?? '');

        foreach (toStringList($group['items'] ?? null) as $name) {
            $sortOrder++;

            $skillRows[] = [
                'category' => $category,
                'name' => $name,
                // 既存 JSON に説明文が無いため null
                'description' => null,
                'sort_order' => $sortOrder,
            ];
        }
    }

    $skillRepository->upsertMany($skillRows);

    // ---- build_logs ----
    $buildLogRows = [];
    $skippedDates = [];
    $sortOrder = 0;

    foreach (readJsonList($dataDirectory . '/buildLogs.json') as $log) {
        $date = (string) ($log['date'] ?? '');
        $loggedAt = toLoggedDate($date);

        if ($loggedAt === null) {
            $skippedDates[] = $date;

            continue;
        }

        $sortOrder++;

        $buildLogRows[] = [
            'title' => (string) ($log['title'] ?? ''),
            'tag' => (string) ($log['tag'] ?? ''),
            'logged_at' => $loggedAt,
            // 既存 JSON の並び順をそのまま表示順にする
            'sort_order' => $sortOrder,
            // 既存 JSON の項目はすべて公開対象とする
            'is_public' => true,
        ];
    }

    $buildLogRepository->upsertMany($buildLogRows);

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    Logger::error('seed に失敗しました。', ['exception' => $exception::class]);
    fwrite(STDERR, '[error] seed に失敗しました: ' . $exception->getMessage() . PHP_EOL);

    exit(1);
}

output('');
output('seed が完了しました。');
output('  データ元     : ' . $dataDirectory);
output('  projects     : ' . count($projectRepository->all()) . ' 件');
output('  technologies : ' . count($technologyRepository->all()) . ' 件');
output('  skills       : ' . count($skillRepository->all()) . ' 件');
output('  build_logs   : ' . count($buildLogRepository->all()) . ' 件');
output('');

foreach ($projectRepository->all() as $project) {
    output(sprintf(
        '  %02d %s [%s]',
        $project->displayOrder,
        $project->slug,
        implode(', ', $project->stack),
    ));
}

if ($skippedDates !== []) {
    output('');
    output('[warning] date 形式が想定外のため ' . count($skippedDates) . ' 件をスキップしました: ' . implode(', ', $skippedDates));
}

output('');
output('※ upsert のみのため、JSON から削除した項目は DB に残ります（削除は次のフェーズで検討）。');

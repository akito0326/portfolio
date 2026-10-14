<?php

declare(strict_types=1);

/**
 * 依存ライブラリを使わないテストランナー。
 *
 *   php -d extension=pdo_pgsql tests/run.php
 *
 * 1) ユーティリティの確認（DB不要）
 * 2) DB 統合確認（PostgreSQL 必須。未接続なら SKIP）
 * 3) HTTP API 確認（php -S を子プロセスで起動して実際にリクエストする）
 *
 * 失敗が1件でもあれば終了コード 1 を返す。
 */

use AkitoDev\Models\Project;
use AkitoDev\Repositories\BuildLogRepository;
use AkitoDev\Repositories\ProjectRepository;
use AkitoDev\Repositories\SkillRepository;
use AkitoDev\Utils\Auth;
use AkitoDev\Utils\Database;
use AkitoDev\Utils\Env;
use AkitoDev\Utils\HttpException;
use AkitoDev\Utils\PgArray;
use AkitoDev\Utils\Router;
use AkitoDev\Utils\Session;

require __DIR__ . '/../src/bootstrap.php';

const API_HOST = '127.0.0.1';
const API_PORT = 8099;
const BAD_DB_PORT = 8098;

$passed = 0;
$failed = 0;
$skipped = 0;

function section(string $title): void
{
    fwrite(STDOUT, PHP_EOL . '== ' . $title . ' ==' . PHP_EOL);
}

/**
 * $assertion 内で例外が投げられなければ成功。
 */
function check(string $name, callable $assertion): void
{
    global $passed, $failed;

    try {
        $assertion();
        $passed++;
        fwrite(STDOUT, '  [PASS] ' . $name . PHP_EOL);
    } catch (Throwable $exception) {
        $failed++;
        fwrite(STDOUT, '  [FAIL] ' . $name . PHP_EOL);
        fwrite(STDOUT, '         ' . $exception->getMessage() . PHP_EOL);
    }
}

function skip(string $name, string $reason): void
{
    global $skipped;

    $skipped++;
    fwrite(STDOUT, '  [SKIP] ' . $name . ' — ' . $reason . PHP_EOL);
}

function assertSame(mixed $expected, mixed $actual, string $label = 'value'): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            '%s が一致しません expected=%s actual=%s',
            $label,
            json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($actual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));
    }
}

function assertTrue(bool $condition, string $message): void
{
    if ($condition === false) {
        throw new RuntimeException($message);
    }
}

function describe(mixed $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '(encode error)';
}

/**
 * Cookie を保持できる HTTP リクエスト。認証のテストで使う。
 *
 * @param array<string, mixed>|null $json JSON ボディ。null の場合はボディなし。
 * @param array<string, string> $cookies 送信する Cookie。
 * @param array{raw?: string, content_type?: string|null} $options
 *   raw: 生ボディを送る（Content-Type は content_type で指定。未指定なら付けない）。
 * @return array{status: int, body: string, json: mixed, headers: list<string>, set_cookies: array<string, string>}
 */
function httpRequest(string $method, string $url, ?array $json = null, array $cookies = [], array $options = []): array
{
    $headerLines = ["Accept: application/json\r\n"];

    if (array_key_exists('raw', $options)) {
        $body = (string) $options['raw'];

        if (array_key_exists('content_type', $options) && $options['content_type'] !== null) {
            $headerLines[] = 'Content-Type: ' . $options['content_type'] . "\r\n";
        }
    } elseif ($json !== null) {
        $headerLines[] = "Content-Type: application/json\r\n";
        $body = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        $body = '';
    }

    $cookiePairs = [];

    foreach ($cookies as $name => $value) {
        $cookiePairs[] = $name . '=' . $value;
    }

    if ($cookiePairs !== []) {
        $headerLines[] = 'Cookie: ' . implode('; ', $cookiePairs) . "\r\n";
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'ignore_errors' => true,
            'timeout' => 10,
            'header' => implode('', $headerLines),
            'content' => $body,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $status = 0;
    $headers = $http_response_header ?? [];
    $setCookies = [];

    foreach ($headers as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
            $status = (int) $matches[1];
        }

        if (preg_match('#^Set-Cookie:\s*([^=;]+)=([^;]*)#i', $header, $matches) === 1) {
            $name = trim($matches[1]);
            $value = trim($matches[2]);

            // 値が空（期限切れの削除）の場合は保持しない
            if ($value === '') {
                unset($setCookies[$name]);

                continue;
            }

            $setCookies[$name] = $value;
        }
    }

    return [
        'status' => $status,
        'body' => is_string($body) ? $body : '',
        'json' => is_string($body) ? json_decode($body, true) : null,
        'headers' => $headers,
        'set_cookies' => $setCookies,
    ];
}

/**
 * 管理画面テスト用のログインを行い、Cookie 配列を返す。
 *
 * @return array<string, string>
 */
function adminLogin(string $baseUrl, string $username, string $password): array
{
    $login = httpRequest('POST', $baseUrl . '/api/login', [
        'username' => $username,
        'password' => $password,
    ]);

    if ($login['status'] !== 200 || $login['set_cookies'] === []) {
        throw new RuntimeException('テスト用のログインに失敗しました: ' . $login['body']);
    }

    return $login['set_cookies'];
}

/**
 * php -S を子プロセスで起動する。
 *
 * @return resource
 */
function startServer(int $port, ?string $dbPortOverride = null)
{
    $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'akito-dev-api-' . $port . '.log';
    $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'nul' : '/dev/null';

    $environment = getenv();

    if (!is_array($environment)) {
        $environment = [];
    }

    if ($dbPortOverride !== null) {
        $environment['DB_PORT'] = $dbPortOverride;
    }

    $process = proc_open(
        [
            PHP_BINARY,
            '-d', 'extension=pdo_pgsql',
            '-S', API_HOST . ':' . $port,
            '-t', BACKEND_ROOT . '/public',
            BACKEND_ROOT . '/public/index.php',
        ],
        [
            0 => ['file', $nullDevice, 'r'],
            1 => ['file', $logFile, 'a'],
            2 => ['file', $logFile, 'a'],
        ],
        $pipes,
        BACKEND_ROOT,
        $environment,
    );

    if (!is_resource($process)) {
        throw new RuntimeException('php -S を起動できませんでした。');
    }

    return $process;
}

function waitForServer(int $port, int $attempts = 40): bool
{
    for ($attempt = 0; $attempt < $attempts; $attempt++) {
        $socket = @fsockopen(API_HOST, $port, $errorCode, $errorMessage, 0.5);

        if (is_resource($socket)) {
            fclose($socket);
            usleep(200_000);

            return true;
        }

        usleep(250_000);
    }

    return false;
}

/**
 * @param resource $process
 */
function stopServer($process): void
{
    proc_terminate($process);
    proc_close($process);
}

// ---------------------------------------------------------------------------
// 1. ユーティリティ（DB不要）
// ---------------------------------------------------------------------------
section('1. ユーティリティ（DB不要）');

check('Env が .env を読み込んでいる', function (): void {
    assertTrue(Env::get('DB_HOST') !== '', 'DB_HOST が空です');
    assertTrue(Env::has('DB_DATABASE'), 'DB_DATABASE が空です');
});

check('PgArray: 空配列は {} になる', function (): void {
    assertSame('{}', PgArray::toLiteral([]), 'literal');
});

check('PgArray: カンマを含む値を引用する', function (): void {
    assertSame('{"React, Vue","TypeScript"}', PgArray::toLiteral(['React, Vue', 'TypeScript']), 'literal');
});

check('PgArray: 二重引用符をエスケープする', function (): void {
    assertSame('{"a\\"b"}', PgArray::toLiteral(['a"b']), 'literal');
});

check('Router: 未定義パスは 404', function (): void {
    $router = new Router();

    try {
        $router->dispatch('GET', '/api/unknown');
    } catch (HttpException $exception) {
        assertSame(404, $exception->status(), 'status');
        assertSame('route_not_found', $exception->errorCode(), 'code');

        return;
    }

    throw new RuntimeException('HttpException が投げられませんでした');
});

check('Router: メソッド不一致は 405', function (): void {
    $router = new Router();
    $router->get('/api/projects', static function (array $params): void {
    });

    try {
        $router->dispatch('POST', '/api/projects');
    } catch (HttpException $exception) {
        assertSame(405, $exception->status(), 'status');
        assertSame('method_not_allowed', $exception->errorCode(), 'code');

        return;
    }

    throw new RuntimeException('HttpException が投げられませんでした');
});

check('Router: {id} をパラメータとして取り出す', function (): void {
    $router = new Router();
    $captured = null;

    $router->get('/api/projects/{id}', static function (array $params) use (&$captured): void {
        $captured = $params;
    });

    $router->dispatch('GET', '/api/projects/42?tab=detail');

    assertSame(['id' => '42'], $captured, 'params');
});

check('Session: https のときだけ Secure Cookie にする', function (): void {
    assertSame(false, Session::shouldUseSecureCookie([]), 'http');
    assertSame(false, Session::shouldUseSecureCookie(['HTTPS' => 'off']), 'HTTPS=off');
    assertSame(true, Session::shouldUseSecureCookie(['HTTPS' => 'on']), 'HTTPS=on');
    assertSame(true, Session::shouldUseSecureCookie(['HTTP_X_FORWARDED_PROTO' => 'https']), 'X-Forwarded-Proto');
});

check('Auth: password_verify が .env のハッシュで通る（$$ エスケープ・クォート対応）', function (): void {
    $password = 'test-password';
    $hash = password_hash($password, PASSWORD_DEFAULT);

    assertTrue(is_string($hash), 'ハッシュ生成に失敗しました');

    putenv('ADMIN_USERNAME=admin');
    putenv('ADMIN_PASSWORD_HASH=' . $hash);

    assertTrue(Auth::isConfigured(), 'Auth が未設定扱いです');
    assertTrue(Auth::verify('admin', $password), 'ハッシュそのままで検証できません');
    assertTrue(Auth::verify('admin', 'wrong') === false, '誤ったパスワードで成功しました');
    assertTrue(Auth::verify('other', $password) === false, '誤ったユーザー名で成功しました');

    // .env では docker compose / シェルの変数展開を避けるため "$$" と書ける。Env は "$" へ戻す。
    putenv('ADMIN_PASSWORD_HASH=' . str_replace('$', '$$', $hash));

    assertSame($hash, Env::get('ADMIN_PASSWORD_HASH'), '$$ が $ へ戻っていません');
    assertTrue(Auth::verify('admin', $password), '$$ エスケープしたハッシュで検証できません');

    putenv('ADMIN_PASSWORD_HASH');
    putenv('ADMIN_USERNAME');
});

check('.env の ADMIN_PASSWORD_HASH が有効なハッシュとして設定されている', function (): void {
    assertTrue(Env::get('ADMIN_USERNAME') !== '', 'ADMIN_USERNAME が空です');

    $hash = Env::get('ADMIN_PASSWORD_HASH');

    assertTrue($hash !== '', 'ADMIN_PASSWORD_HASH が空です');
    assertTrue(password_get_info($hash)['algoName'] !== 'unknown', 'ハッシュ形式が不正です');
});

check('Project::toArray が既存 Project 型の形を返す', function (): void {
    $project = Project::fromRow([
        'id' => 1,
        'display_order' => 1,
        'slug' => 'akito-crm',
        'title' => 'AKITO CRM',
        'description' => 'description',
        'accent' => 'CRM / BUSINESS',
        'visual' => 'CRM SYSTEM',
        'overview' => 'overview',
        'problem' => null,
        'solution' => 'solution',
        'features' => json_encode(['A', 'B'], JSON_UNESCAPED_UNICODE),
        'architecture' => json_encode([], JSON_UNESCAPED_UNICODE),
        'github_url' => '',
        'demo_url' => 'https://example.com',
        'created_at' => '2026-09-30 05:35:26+09',
        'updated_at' => '2026-09-30 05:35:26+09',
    ], ['React', 'TypeScript']);

    $array = $project->toArray();

    assertSame('01', $array['number'], 'number');
    assertSame('akito-crm', $array['slug'], 'slug');
    assertSame(['React', 'TypeScript'], $array['stack'], 'stack');
    assertSame(['A', 'B'], $array['features'], 'features');
    assertSame([], $array['architecture'], 'architecture');
    assertSame(['github' => '', 'demo' => 'https://example.com'], $array['links'], 'links');
    assertTrue(!array_key_exists('problem', $array), 'problem は null のときキーを省略する必要があります');
    assertTrue(array_key_exists('overview', $array), 'overview が含まれていません');
});

// ---------------------------------------------------------------------------
// 2. DB 統合（PostgreSQL が必要）
// ---------------------------------------------------------------------------
section('2. DB 統合（PostgreSQL）');

$dataDirectory = dirname(BACKEND_ROOT) . '/src/data';
$databaseAvailable = Database::isConnected();

if (!$databaseAvailable) {
    skip('DB 統合テスト', 'PostgreSQL へ接続できません（backend/.env と起動状態を確認してください）');
}

if ($databaseAvailable) {
    $pdo = Database::connection();

    check('migration が 6 件適用済み', function () use ($pdo): void {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations');
        $statement->execute();

        assertSame(6, (int) $statement->fetchColumn(), '適用済み migration 件数');
    });

    check('必須テーブルが存在する', function () use ($pdo): void {
        $statement = $pdo->prepare('SELECT tablename FROM pg_tables WHERE schemaname = :schema');
        $statement->execute(['schema' => 'public']);
        $existing = array_column($statement->fetchAll(), 'tablename');

        foreach (['projects', 'skills', 'technologies', 'project_technologies', 'build_logs'] as $table) {
            assertTrue(in_array($table, $existing, true), $table . ' が存在しません');
        }
    });

    check('projects が既存 JSON と一致する（件数/順序/内容/stack順）', function () use ($dataDirectory): void {
        $expected = json_decode(
            (string) file_get_contents($dataDirectory . '/projects.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $actual = (new ProjectRepository())->all();

        assertSame(count($expected), count($actual), '件数');

        foreach ($expected as $index => $project) {
            $row = $actual[$index];

            assertSame((string) $project['number'], str_pad((string) $row->displayOrder, 2, '0', STR_PAD_LEFT), 'number');
            assertSame($project['slug'], $row->slug, 'slug');
            assertSame($project['title'], $row->title, 'title');
            assertSame($project['description'], $row->description, 'description');
            assertSame($project['accent'], $row->accent, 'accent');
            assertSame($project['visual'], $row->visual, 'visual');
            assertSame($project['overview'], $row->overview, 'overview');
            assertSame($project['problem'], $row->problem, 'problem');
            assertSame($project['solution'], $row->solution, 'solution');
            assertSame($project['features'], $row->features, 'features');
            assertSame($project['architecture'], $row->architecture, 'architecture');
            assertSame($project['stack'], $row->stack, 'stack(並び順)');
            assertSame((string) $project['links']['github'], $row->githubUrl, 'github_url');
            assertSame((string) $project['links']['demo'], $row->demoUrl, 'demo_url');
        }
    });

    check('findById で 1 件取得できる', function (): void {
        $first = (new ProjectRepository())->all()[0];
        $found = (new ProjectRepository())->findById((int) $first->id);

        assertTrue($found !== null, 'findById が null を返しました');
        assertSame($first->slug, $found?->slug, 'slug');
    });

    check('findBySlug が findById と同じ project を返す', function (): void {
        $repository = new ProjectRepository();
        $first = $repository->all()[0];
        $found = $repository->findBySlug($first->slug);

        assertTrue($found !== null, 'findBySlug が null を返しました');
        assertSame($first->id, $found?->id, 'id');
        assertSame($first->displayOrder, $found?->displayOrder, 'displayOrder');
        assertSame($first->stack, $found?->stack, 'stack');
        assertSame($first->features, $found?->features, 'features');
        assertSame($first->toArray(), $found?->toArray(), 'toArray');
    });

    check('存在しない slug は null を返す', function (): void {
        assertTrue(
            (new ProjectRepository())->findBySlug('no-such-project') === null,
            'null ではありません',
        );
    });

    check('skills が既存 JSON と一致する（category/name/並び順）', function () use ($dataDirectory): void {
        $groups = json_decode(
            (string) file_get_contents($dataDirectory . '/skills.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $actual = (new SkillRepository())->all();

        $expected = [];
        $sortOrder = 0;

        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $sortOrder++;
                $expected[] = [
                    'category' => (string) $group['title'],
                    'name' => (string) $item,
                    'sort_order' => $sortOrder,
                ];
            }
        }

        assertSame(count($expected), count($actual), '件数');

        foreach ($expected as $index => $item) {
            assertSame($item['category'], $actual[$index]->category, 'category');
            assertSame($item['name'], $actual[$index]->name, 'name');
            assertSame($item['sort_order'], $actual[$index]->sortOrder, 'sort_order');
            assertTrue(
                $actual[$index]->description === null,
                'description は既存データに無いため null である必要があります',
            );
        }
    });

    check('build_logs の件数が既存 JSON と一致する', function () use ($dataDirectory): void {
        $expected = json_decode(
            (string) file_get_contents($dataDirectory . '/buildLogs.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        assertSame(count($expected), count((new BuildLogRepository())->all()), '件数');
    });

    check('build_logs が表示順で取得でき、toArray がフロント互換の形を返す', function (): void {
        $repository = new BuildLogRepository();
        $logs = $repository->all();

        assertSame(5, count($logs), '件数');
        assertSame(count($logs), count($repository->allPublic()), '公開分の件数');

        $first = $logs[0]->toArray();

        assertSame('2026.09', $first['date'] ?? null, 'date');
        assertSame('AKITO CRM / React + TypeScript UI', $first['title'] ?? null, 'title');
        assertSame('Frontend', $first['tag'] ?? null, 'tag');
        assertSame('2026-09-01', $first['logged_at'] ?? null, 'logged_at');
        assertSame(1, $first['sort_order'] ?? null, 'sort_order');
        assertSame(true, $first['is_public'] ?? null, 'is_public');
    });

    check('build_logs の upsertMany は既存行を上書きしない（ON CONFLICT DO NOTHING）', function (): void {
        $pdo = Database::connection();
        $repository = new BuildLogRepository($pdo);

        $before = $repository->all()[0];

        // 副作用が出ても DB を汚さないようトランザクション内で試す
        $pdo->beginTransaction();

        try {
            $repository->upsertMany([[
                'title' => $before->title,
                'tag' => 'SHOULD_NOT_OVERWRITE',
                'logged_at' => $before->loggedAt,
                'sort_order' => 999,
                'is_public' => false,
            ]]);

            $after = $repository->all()[0];

            assertSame($before->tag, $after->tag, 'tag');
            assertSame($before->sortOrder, $after->sortOrder, 'sort_order');
            assertSame($before->isPublic, $after->isPublic, 'is_public');
        } finally {
            $pdo->rollBack();
        }
    });
}

// ---------------------------------------------------------------------------
// 3. HTTP API（php -S を子プロセスで起動して実際にリクエストする）
// ---------------------------------------------------------------------------
section('3. HTTP API');

if (!$databaseAvailable) {
    skip('HTTP API テスト', 'PostgreSQL へ接続できないため未実施');
}

if ($databaseAvailable) {
    $baseUrl = 'http://' . API_HOST . ':' . API_PORT;
    $server = null;

    // HTTP のログインテストを決定的にするため、サーバーへ引き継ぐテスト用の認証情報を用意する
    $testUsername = 'admin';
    $testPassword = 'test-password';
    putenv('ADMIN_USERNAME=' . $testUsername);
    putenv('ADMIN_PASSWORD_HASH=' . password_hash($testPassword, PASSWORD_DEFAULT));

    try {
        $server = startServer(API_PORT);

        if (!waitForServer(API_PORT)) {
            throw new RuntimeException('php -S が ' . API_PORT . ' で起動しませんでした');
        }

        check('GET /api/session は未ログインなら authenticated=false を返す', function () use ($baseUrl): void {
            $response = httpRequest('GET', $baseUrl . '/api/session');

            assertSame(200, $response['status'], 'status');
            assertSame(false, $response['json']['authenticated'] ?? null, 'authenticated');
            assertSame(null, $response['json']['username'] ?? null, 'username');
        });

        check('POST /api/login は誤った認証情報で 401 を返し、約1秒待つ', function () use ($baseUrl): void {
            $startedAt = microtime(true);
            $response = httpRequest('POST', $baseUrl . '/api/login', [
                'username' => 'admin',
                'password' => 'wrong-password',
            ]);
            $elapsed = microtime(true) - $startedAt;

            assertSame(401, $response['status'], 'status');
            assertSame('invalid_credentials', $response['json']['error']['code'] ?? null, 'error.code');
            assertTrue($elapsed >= 0.9, 'ログイン失敗時の待機が短すぎます: ' . $elapsed . ' 秒');
        });

        check('POST /api/login → GET /api/session → POST /api/logout が動作する', function () use ($baseUrl, $testUsername, $testPassword): void {
            $login = httpRequest('POST', $baseUrl . '/api/login', [
                'username' => $testUsername,
                'password' => $testPassword,
            ]);

            assertSame(200, $login['status'], 'login.status');
            assertSame(true, $login['json']['authenticated'] ?? null, 'login.authenticated');
            assertSame($testUsername, $login['json']['username'] ?? null, 'login.username');
            assertTrue($login['set_cookies'] !== [], 'セッション Cookie が発行されていません');

            $sessionName = (string) array_key_first($login['set_cookies']);
            $sessionCookie = [$sessionName => $login['set_cookies'][$sessionName]];

            $session = httpRequest('GET', $baseUrl . '/api/session', null, $sessionCookie);

            assertSame(200, $session['status'], 'session.status');
            assertSame(true, $session['json']['authenticated'] ?? null, 'session.authenticated');
            assertSame($testUsername, $session['json']['username'] ?? null, 'session.username');

            $logout = httpRequest('POST', $baseUrl . '/api/logout', null, $sessionCookie);

            assertSame(200, $logout['status'], 'logout.status');
            assertSame(false, $logout['json']['authenticated'] ?? null, 'logout.authenticated');

            $expired = false;

            foreach ($logout['headers'] as $header) {
                if (
                    stripos($header, 'Set-Cookie:') === 0
                    && stripos($header, $sessionName . '=') !== false
                    && (stripos($header, '1970') !== false || stripos($header, 'Max-Age=0') !== false)
                ) {
                    $expired = true;
                }
            }

            assertTrue($expired, 'ログアウトで Cookie を削除する Set-Cookie がありません');
        });

        check('GET /api/health が 200 と connected を返す', function () use ($baseUrl): void {
            $response = httpRequest('GET', $baseUrl . '/api/health');

            assertSame(200, $response['status'], 'status');
            assertSame(['status' => 'ok', 'database' => 'connected'], $response['json'], 'body');
        });

        check('GET /api/projects が 3 件返す', function () use ($baseUrl): void {
            $response = httpRequest('GET', $baseUrl . '/api/projects');

            assertSame(200, $response['status'], 'status');
            assertTrue(is_array($response['json']), 'JSON 配列ではありません: ' . $response['body']);
            assertSame(3, count((array) $response['json']), '件数');
        });

        check('POST /api/build-logs は未ログインで 401', function () use ($baseUrl): void {
            $response = httpRequest('POST', $baseUrl . '/api/build-logs', [
                'title' => 'unauthorized',
                'tag' => 'Test',
                'logged_at' => '2026-10-01',
            ]);

            assertSame(401, $response['status'], 'status');
            assertSame('unauthorized', $response['json']['error']['code'] ?? null, 'error.code');
        });

        check('POST /api/build-logs は Content-Type が JSON でなければ 415', function () use ($baseUrl, $testUsername, $testPassword): void {
            $cookies = adminLogin($baseUrl, $testUsername, $testPassword);

            $response = httpRequest(
                'POST',
                $baseUrl . '/api/build-logs',
                null,
                $cookies,
                ['raw' => 'title=test', 'content_type' => 'application/x-www-form-urlencoded'],
            );

            assertSame(415, $response['status'], 'status');
            assertSame('unsupported_media_type', $response['json']['error']['code'] ?? null, 'error.code');

            $missing = httpRequest('POST', $baseUrl . '/api/build-logs', null, $cookies, ['raw' => '{}']);

            assertSame(415, $missing['status'], 'status(Content-Type なし)');
            assertSame('unsupported_media_type', $missing['json']['error']['code'] ?? null, 'error.code');
        });

        check('build_logs の (logged_at, title) 重複は 409 build_log_conflict', function () use ($baseUrl, $testUsername, $testPassword): void {
            $cookies = adminLogin($baseUrl, $testUsername, $testPassword);

            $response = httpRequest('POST', $baseUrl . '/api/build-logs', [
                'title' => 'AKITO CRM / React + TypeScript UI',
                'tag' => 'Frontend',
                'logged_at' => '2026-09-01',
            ], $cookies);

            assertSame(409, $response['status'], 'status');
            assertSame('build_log_conflict', $response['json']['error']['code'] ?? null, 'error.code');
        });

        check('build_logs の不正な入力は 400 validation_error', function () use ($baseUrl, $testUsername, $testPassword): void {
            $cookies = adminLogin($baseUrl, $testUsername, $testPassword);

            $cases = [
                'title 空' => ['title' => '', 'tag' => 'Test', 'logged_at' => '2026-10-01'],
                '存在しない日付' => ['title' => 'x', 'tag' => 'Test', 'logged_at' => '2026-02-30'],
                '日付の形式違い' => ['title' => 'x', 'tag' => 'Test', 'logged_at' => '2026/10/01'],
                'sort_order 範囲外' => ['title' => 'x', 'tag' => 'Test', 'logged_at' => '2026-10-01', 'sort_order' => 10000],
            ];

            foreach ($cases as $label => $payload) {
                $response = httpRequest('POST', $baseUrl . '/api/build-logs', $payload, $cookies);

                assertSame(400, $response['status'], 'status(' . $label . ')');
                assertSame('validation_error', $response['json']['error']['code'] ?? null, 'error.code(' . $label . ')');
            }
        });

        check('build_logs の作成→更新→削除が動作する（201/200 と保存後の1件）', function () use ($baseUrl, $testUsername, $testPassword): void {
            $cookies = adminLogin($baseUrl, $testUsername, $testPassword);
            $createdId = null;

            try {
                $created = httpRequest('POST', $baseUrl . '/api/build-logs', [
                    'title' => 'CRUD TEST ' . time(),
                    'tag' => 'Test',
                    'logged_at' => '2026-10-07',
                    'sort_order' => 900,
                    'is_public' => false,
                ], $cookies);

                assertSame(201, $created['status'], 'create.status');

                $createdBody = (array) $created['json'];

                assertTrue(isset($createdBody['id']), 'create.id');
                assertSame('Test', $createdBody['tag'] ?? null, 'create.tag');
                assertSame(false, $createdBody['is_public'] ?? null, 'create.is_public');

                $createdId = (int) $createdBody['id'];

                $updated = httpRequest('PUT', $baseUrl . '/api/build-logs/' . $createdId, [
                    'title' => $createdBody['title'],
                    'tag' => 'Updated',
                    'logged_at' => '2026-10-07',
                    'sort_order' => 901,
                    'is_public' => true,
                ], $cookies);

                assertSame(200, $updated['status'], 'update.status');
                assertSame('Updated', ((array) $updated['json'])['tag'] ?? null, 'update.tag');

                $deleted = httpRequest('DELETE', $baseUrl . '/api/build-logs/' . $createdId, null, $cookies);

                assertSame(200, $deleted['status'], 'delete.status');
                assertSame(true, ((array) $deleted['json'])['deleted'] ?? null, 'delete.deleted');

                $createdId = null;

                $gone = httpRequest('DELETE', $baseUrl . '/api/build-logs/999999', null, $cookies);

                assertSame(404, $gone['status'], 'delete(存在しない).status');
                assertSame('build_log_not_found', $gone['json']['error']['code'] ?? null, 'error.code');
            } finally {
                if ($createdId !== null) {
                    httpRequest('DELETE', $baseUrl . '/api/build-logs/' . $createdId, null, $cookies);
                }
            }
        });

        check('GET /api/admin/build-logs は未ログインで 401、ログインで全件返す', function () use ($baseUrl, $testUsername, $testPassword): void {
            $denied = httpRequest('GET', $baseUrl . '/api/admin/build-logs');

            assertSame(401, $denied['status'], 'status');
            assertSame('unauthorized', $denied['json']['error']['code'] ?? null, 'error.code');

            $cookies = adminLogin($baseUrl, $testUsername, $testPassword);
            $response = httpRequest('GET', $baseUrl . '/api/admin/build-logs', null, $cookies);

            assertSame(200, $response['status'], 'status');
            assertTrue(is_array($response['json']), 'JSON 配列ではありません: ' . $response['body']);
            assertSame(5, count((array) $response['json']), '件数');
        });

        check('GET /api/build-logs が公開分を表示順で返す', function () use ($baseUrl): void {
            $response = httpRequest('GET', $baseUrl . '/api/build-logs');

            assertSame(200, $response['status'], 'status');
            assertTrue(is_array($response['json']), 'JSON 配列ではありません: ' . $response['body']);
            assertSame(5, count((array) $response['json']), '件数');

            $logs = (array) $response['json'];
            $first = (array) $logs[0];

            assertSame('2026.09', $first['date'] ?? null, 'date');
            assertSame('AKITO CRM / React + TypeScript UI', $first['title'] ?? null, 'title');
            assertSame('Frontend', $first['tag'] ?? null, 'tag');
            assertSame(true, $first['is_public'] ?? null, 'is_public');
        });

        // --- projects の CRUD テスト用の正常系ペイロード ---
        $validProject = [
            'display_order' => 900,
            'slug' => 'test-valid',
            'title' => 'Test Project',
            'description' => 'Test description',
            'accent' => 'TEST',
            'visual' => 'TEST',
            'overview' => 'overview',
            'problem' => null,
            'solution' => null,
            'features' => [],
            'architecture' => [],
            'links' => ['github' => '', 'demo' => ''],
            'stack' => [],
        ];

        check('POST /api/projects は未ログインで 401', function () use ($baseUrl, $validProject): void {
            $response = httpRequest('POST', $baseUrl . '/api/projects', $validProject);

            assertSame(401, $response['status'], 'status');
            assertSame('unauthorized', $response['json']['error']['code'] ?? null, 'error.code');
        });

        check('projects の作成→更新→削除が動作する（201/200 と保存後の1件）', function () use ($baseUrl, $testUsername, $testPassword, $validProject): void {
            $cookies = adminLogin($baseUrl, $testUsername, $testPassword);
            $createdId = null;
            $createdSlug = null;

            try {
                $payload = $validProject;
                $payload['slug'] = 'test-crud-' . time();
                $payload['stack'] = ['PHP', 'PostgreSQL'];

                $created = httpRequest('POST', $baseUrl . '/api/projects', $payload, $cookies);

                assertSame(201, $created['status'], 'create.status');

                $createdBody = (array) $created['json'];

                assertTrue(isset($createdBody['id']), 'create.id');
                assertSame($payload['slug'], $createdBody['slug'] ?? null, 'create.slug');
                assertSame(['PHP', 'PostgreSQL'], $createdBody['stack'] ?? null, 'create.stack');

                $createdId = (int) $createdBody['id'];
                $createdSlug = $payload['slug'];

                $updated = httpRequest('PUT', $baseUrl . '/api/projects/' . $createdId, array_merge($payload, [
                    'title' => 'Updated Title',
                    'stack' => ['PHP'],
                ]), $cookies);

                assertSame(200, $updated['status'], 'update.status');

                $updatedBody = (array) $updated['json'];

                assertSame('Updated Title', $updatedBody['title'] ?? null, 'update.title');
                assertSame(['PHP'], $updatedBody['stack'] ?? null, 'update.stack');

                $deleted = httpRequest('DELETE', $baseUrl . '/api/projects/' . $createdId, null, $cookies);

                assertSame(200, $deleted['status'], 'delete.status');
                assertSame(true, ((array) $deleted['json'])['deleted'] ?? null, 'delete.deleted');

                $createdId = null;
                $createdSlug = null;

                $gone = httpRequest('DELETE', $baseUrl . '/api/projects/999999', null, $cookies);

                assertSame(404, $gone['status'], 'delete(存在しない).status');
                assertSame('project_not_found', $gone['json']['error']['code'] ?? null, 'error.code');
            } finally {
                if ($createdId !== null) {
                    httpRequest('DELETE', $baseUrl . '/api/projects/' . $createdId, null, $cookies);
                } elseif ($createdSlug !== null) {
                    httpRequest('DELETE', $baseUrl . '/api/projects/slug/' . $createdSlug, null, $cookies);
                }
            }
        });

        check('projects の slug 重複は 409 slug_conflict', function () use ($baseUrl, $testUsername, $testPassword, $validProject): void {
            $cookies = adminLogin($baseUrl, $testUsername, $testPassword);
            $createdId = null;

            try {
                $payload = $validProject;
                $payload['slug'] = 'test-conflict';
                $payload['display_order'] = 900;

                $first = httpRequest('POST', $baseUrl . '/api/projects', $payload, $cookies);

                assertSame(201, $first['status'], 'first.status');

                $createdId = (int) ((array) $first['json'])['id'];

                $payload['display_order'] = 901;
                $conflict = httpRequest('POST', $baseUrl . '/api/projects', $payload, $cookies);

                assertSame(409, $conflict['status'], 'status');
                assertSame('slug_conflict', $conflict['json']['error']['code'] ?? null, 'error.code');

                $payload['slug'] = 'test-conflict-2';
                $payload['display_order'] = 1;
                $orderConflict = httpRequest('POST', $baseUrl . '/api/projects', $payload, $cookies);

                assertSame(409, $orderConflict['status'], 'status(display_order)');
                assertSame('display_order_conflict', $orderConflict['json']['error']['code'] ?? null, 'error.code(display_order)');
            } finally {
                if ($createdId !== null) {
                    httpRequest('DELETE', $baseUrl . '/api/projects/' . $createdId, null, $cookies);
                }
            }
        });

        check('projects の不正な stack は 400 validation_error', function () use ($baseUrl, $testUsername, $testPassword, $validProject): void {
            $cookies = adminLogin($baseUrl, $testUsername, $testPassword);

            $cases = [
                '配列でない' => 'PHP',
                '空要素あり' => ['PHP', ''],
                '文字列でない' => ['PHP', 123],
                '31 件' => array_fill(0, 31, 'PHP'),
            ];

            foreach ($cases as $label => $stack) {
                $payload = $validProject;
                $payload['slug'] = 'test-stack-ng';
                $payload['display_order'] = 902;
                $payload['stack'] = $stack;

                $response = httpRequest('POST', $baseUrl . '/api/projects', $payload, $cookies);

                assertSame(400, $response['status'], 'status(' . $label . ')');
                assertSame('validation_error', $response['json']['error']['code'] ?? null, 'error.code(' . $label . ')');
            }
        });

        check('test- 始まりの slug が残っていない', function () use ($baseUrl): void {
            $pdo = Database::connection();
            $statement = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE slug LIKE 'test-%'");
            $statement->execute();

            assertSame(0, (int) $statement->fetchColumn(), '残存 test- データ');

            $relations = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM project_technologies pt
                 LEFT JOIN projects p ON p.id = pt.project_id
                 WHERE p.id IS NULL'
            );
            $relations->execute();

            assertSame(0, (int) $relations->fetchColumn(), '孤児の project_technologies');

            $buildLogs = $pdo->prepare("SELECT COUNT(*) FROM build_logs WHERE title LIKE 'CRUD TEST %'");
            $buildLogs->execute();

            assertSame(0, (int) $buildLogs->fetchColumn(), '残存 CRUD TEST データ');
        });

        check('GET /api/projects/1 が単一 project を返す', function () use ($baseUrl): void {
            $response = httpRequest('GET', $baseUrl . '/api/projects/1');

            assertSame(200, $response['status'], 'status');

            $project = (array) $response['json'];

            assertSame('akito-crm', $project['slug'] ?? null, 'slug');
            assertSame('01', $project['number'] ?? null, 'number');
            assertSame(
                ['React', 'TypeScript', 'API', 'PostgreSQL', 'Docker'],
                $project['stack'] ?? null,
                'stack',
            );
        });

        check('GET /api/projects/slug/akito-crm が単一 project を返す', function () use ($baseUrl): void {
            $response = httpRequest('GET', $baseUrl . '/api/projects/slug/akito-crm');

            assertSame(200, $response['status'], 'status');

            $project = (array) $response['json'];

            assertSame('akito-crm', $project['slug'] ?? null, 'slug');
            assertSame('01', $project['number'] ?? null, 'number');
            assertSame('AKITO CRM', $project['title'] ?? null, 'title');
            assertSame(
                ['React', 'TypeScript', 'API', 'PostgreSQL', 'Docker'],
                $project['stack'] ?? null,
                'stack',
            );
        });

        check('存在しない slug は 404', function () use ($baseUrl): void {
            $response = httpRequest('GET', $baseUrl . '/api/projects/slug/no-such-project');

            assertSame(404, $response['status'], 'status');
            assertSame('project_not_found', $response['json']['error']['code'] ?? null, 'error.code');
        });

        check('存在しない project は 404', function () use ($baseUrl): void {
            $response = httpRequest('GET', $baseUrl . '/api/projects/9999');

            assertSame(404, $response['status'], 'status');
            assertSame('project_not_found', $response['json']['error']['code'] ?? null, 'error.code');
        });

        check('数値でない project id は 400', function () use ($baseUrl): void {
            $response = httpRequest('GET', $baseUrl . '/api/projects/abc');

            assertSame(400, $response['status'], 'status');
            assertSame('invalid_project_id', $response['json']['error']['code'] ?? null, 'error.code');
        });

        check('未定義のエンドポイントは 404', function () use ($baseUrl): void {
            $response = httpRequest('GET', $baseUrl . '/api/unknown');

            assertSame(404, $response['status'], 'status');
            assertSame('route_not_found', $response['json']['error']['code'] ?? null, 'error.code');
        });

        check('未対応メソッドは 405', function () use ($baseUrl): void {
            $response = httpRequest('PATCH', $baseUrl . '/api/projects');

            assertSame(405, $response['status'], 'status');
            assertSame('method_not_allowed', $response['json']['error']['code'] ?? null, 'error.code');
        });
    } finally {
        if (is_resource($server)) {
            stopServer($server);
        }
    }

    // 不正な DB 接続情報（DB_PORT=1）で起動した場合の挙動
    $brokenBaseUrl = 'http://' . API_HOST . ':' . BAD_DB_PORT;
    $brokenServer = null;

    try {
        $brokenServer = startServer(BAD_DB_PORT, '1');

        if (!waitForServer(BAD_DB_PORT)) {
            throw new RuntimeException('php -S が ' . BAD_DB_PORT . ' で起動しませんでした');
        }

        check('DB 接続不可のとき /api/health は 503 を返す', function () use ($brokenBaseUrl): void {
            $response = httpRequest('GET', $brokenBaseUrl . '/api/health');

            assertSame(503, $response['status'], 'status');
            assertSame('error', $response['json']['status'] ?? null, 'status');
            assertSame('disconnected', $response['json']['database'] ?? null, 'database');
        });

        check('DB 接続不可のとき /api/projects は 503 を返す', function () use ($brokenBaseUrl): void {
            $response = httpRequest('GET', $brokenBaseUrl . '/api/projects');

            assertSame(503, $response['status'], 'status');
            assertSame('database_unavailable', $response['json']['error']['code'] ?? null, 'error.code');
        });
    } finally {
        if (is_resource($brokenServer)) {
            stopServer($brokenServer);
        }
    }
}

// ---------------------------------------------------------------------------
section('結果');

fwrite(STDOUT, sprintf('  PASS: %d  FAIL: %d  SKIP: %d%s', $passed, $failed, $skipped, PHP_EOL));

exit($failed === 0 ? 0 : 1);

<?php

declare(strict_types=1);

namespace AkitoDev\Controllers;

use AkitoDev\Utils\Auth;
use AkitoDev\Utils\HttpException;
use AkitoDev\Utils\JsonResponse;
use AkitoDev\Utils\Request;
use AkitoDev\Utils\Session;

/**
 * 管理画面のログイン API。
 *   POST /api/login   ログイン
 *   POST /api/logout  ログアウト（Cookie も削除）
 *   GET  /api/session ログイン状態の確認
 */
final class AuthController
{
    /**
     * ログイン失敗時に待たせる時間（マイクロ秒）。
     * 総当たり攻撃を遅くし、成功／失敗の応答時間差も縮める。
     */
    private const FAILURE_DELAY_MICROSECONDS = 1_000_000;

    /**
     * @param array<string, string> $params
     */
    public function login(array $params): void
    {
        $payload = Request::json();
        $rawUsername = $payload['username'] ?? null;
        $rawPassword = $payload['password'] ?? null;
        $username = is_string($rawUsername) ? trim($rawUsername) : '';
        $password = is_string($rawPassword) ? $rawPassword : '';

        if ($username === '' || $password === '') {
            self::fail();
        }

        if (Auth::verify($username, $password) === false) {
            self::fail();
        }

        Session::login($username);

        JsonResponse::send([
            'authenticated' => true,
            'username' => $username,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function logout(array $params): void
    {
        Session::logout();

        JsonResponse::send(['authenticated' => false]);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(array $params): void
    {
        JsonResponse::send([
            'authenticated' => Session::isAuthenticated(),
            'username' => Session::username(),
        ]);
    }

    /**
     * ログイン失敗時の共通処理。ユーザー名とパスワードのどちらが違うかは教えない。
     */
    private static function fail(): never
    {
        usleep(self::FAILURE_DELAY_MICROSECONDS);

        throw new HttpException(401, 'invalid_credentials', 'ユーザー名またはパスワードが違います。');
    }
}

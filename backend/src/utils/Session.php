<?php

declare(strict_types=1);

namespace AkitoDev\Utils;

/**
 * PHP セッションによる管理画面ログイン状態の管理。
 *
 * 設計理由:
 * - 認証情報の照合は Auth が .env から行い、ここは「ログイン済みか」だけを持つ
 * - Cookie は JS から読めないよう HttpOnly にする
 * - Secure は https のときだけ付ける（ローカルの http 開発では付けると送信されないため）
 */
final class Session
{
    public const USERNAME_KEY = 'admin_username';

    private function __construct()
    {
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            // 同一オリジンの管理画面からのみ使うため Lax（POST は同一オリジンなので送信される）
            'samesite' => 'Lax',
            'secure' => self::shouldUseSecureCookie($_SERVER),
        ]);

        session_start();
    }

    public static function login(string $username): void
    {
        self::start();

        // セッション固定攻撃を防ぐ
        session_regenerate_id(true);

        $_SESSION[self::USERNAME_KEY] = $username;
    }

    public static function logout(): void
    {
        self::start();

        $_SESSION = [];

        if (ini_get('session.use_cookies') !== '0') {
            $params = session_get_cookie_params();

            // ブラウザ側にも期限切れの Cookie を送って確実に削除する
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    public static function isAuthenticated(): bool
    {
        self::start();

        return isset($_SESSION[self::USERNAME_KEY]) && is_string($_SESSION[self::USERNAME_KEY]);
    }

    public static function username(): ?string
    {
        self::start();

        $username = $_SESSION[self::USERNAME_KEY] ?? null;

        return is_string($username) ? $username : null;
    }

    /**
     * 未ログインなら 401 を投げる。保護したい処理の先頭で呼ぶ。
     */
    public static function requireAuth(): void
    {
        if (self::isAuthenticated()) {
            return;
        }

        throw new HttpException(401, 'unauthorized', 'ログインが必要です。');
    }

    /**
     * https のときだけ Secure を付ける。
     * リバースプロキシ配下（X-Forwarded-Proto）も考慮する。
     *
     * @param array<string, mixed> $server
     */
    public static function shouldUseSecureCookie(array $server): bool
    {
        $https = $server['HTTPS'] ?? null;

        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        $forwardedProto = $server['HTTP_X_FORWARDED_PROTO'] ?? null;

        return is_string($forwardedProto) && strtolower($forwardedProto) === 'https';
    }
}

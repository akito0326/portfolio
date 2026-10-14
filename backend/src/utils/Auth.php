<?php

declare(strict_types=1);

namespace AkitoDev\Utils;

/**
 * 管理画面のログイン認証（1ユーザー）。
 *
 * 認証情報はコードへ直書きせず .env から読む:
 *   ADMIN_USERNAME      … 管理者のユーザー名
 *   ADMIN_PASSWORD_HASH … password_hash() で生成したハッシュ
 *
 * ルール:
 * - ユーザー名が一致しなくても password_verify() を必ず実行する
 *   （存在しないユーザー名のときだけ応答が速くなる、という推測を防ぐ）
 */
final class Auth
{
    /**
     * ダミーのハッシュ。ハッシュ未設定時も username 不一致時と同じ計算量にする。
     * （bcrypt / cost 10 の固定値。中身は何でもよい）
     */
    private const DUMMY_HASH = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    private function __construct()
    {
    }

    public static function verify(string $username, string $password): bool
    {
        $expectedUsername = Env::get('ADMIN_USERNAME');
        $hash = Env::get('ADMIN_PASSWORD_HASH');

        if ($expectedUsername === '') {
            return false;
        }

        // username が違っても検証時間を揃えるため、必ず password_verify() を通す
        $hashToCheck = $hash !== '' ? $hash : self::DUMMY_HASH;

        $usernameMatches = hash_equals($expectedUsername, $username);
        $passwordMatches = password_verify($password, $hashToCheck);

        return $hash !== '' && $usernameMatches && $passwordMatches;
    }

    public static function isConfigured(): bool
    {
        return Env::get('ADMIN_USERNAME') !== '' && Env::get('ADMIN_PASSWORD_HASH') !== '';
    }
}

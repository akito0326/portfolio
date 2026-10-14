<?php

declare(strict_types=1);

namespace AkitoDev\Utils;

/**
 * リクエストボディの読み取り。
 *
 * php://input は $_POST と違い PUT / DELETE でも読めるため、
 * 管理画面の API はすべてこのクラス経由で JSON を受け取る。
 */
final class Request
{
    private function __construct()
    {
    }

    /**
     * JSON ボディを連想配列で返す。
     * 空ボディの場合は空配列を返す。
     *
     * Content-Type が application/json でない場合は 415 を投げる。
     * 登録・更新 API は JSON 以外を受け付けないため。
     *
     * @return array<string, mixed>
     */
    public static function json(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (!is_string($contentType) || strcasecmp(trim(explode(';', $contentType)[0]), 'application/json') !== 0) {
            throw new HttpException(
                415,
                'unsupported_media_type',
                'Content-Type は application/json で送ってください。',
            );
        }

        $raw = file_get_contents('php://input');

        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new HttpException(400, 'invalid_json', 'リクエストボディは JSON オブジェクトで送ってください。');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\YouTube;

/**
 * 解析・正規化済みのチャンネル入力を表す値オブジェクト。
 * type: 'channel_id' | 'handle' | 'username'
 */
final class ChannelInput
{
    public function __construct(
        public readonly string $type,
        public readonly string $value,
    ) {}

    /**
     * URL または @handle 文字列から ChannelInput を生成する。
     * 対応できない形式の場合は null を返す。
     *
     * 対応形式:
     *   - https://www.youtube.com/channel/UCxxxx  → channel_id
     *   - https://www.youtube.com/@handle         → handle
     *   - https://www.youtube.com/c/name          → handle
     *   - https://www.youtube.com/user/name       → username
     *   - @handle                                  → handle
     */
    public static function fromUrl(string $url): ?self
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        // @handle 文字列（URL ではない）
        if (str_starts_with($url, '@')) {
            $handle = self::normalizeHandle(substr($url, 1));

            return $handle !== '' ? new self('handle', $handle) : null;
        }

        // URL として解析
        $parsed = parse_url($url);

        if ($parsed === false || empty($parsed['host'])) {
            return null;
        }

        $host = strtolower($parsed['host'] ?? '');

        if (! in_array($host, ['www.youtube.com', 'youtube.com', 'm.youtube.com'], true)) {
            return null;
        }

        $path = rtrim($parsed['path'] ?? '', '/');

        // /channel/UCxxxx
        if (preg_match('#^/channel/([A-Za-z0-9_-]+)$#', $path, $m)) {
            return new self('channel_id', $m[1]);
        }

        // /@handle
        if (preg_match('#^/@([A-Za-z0-9_.-]+)$#', $path, $m)) {
            return new self('handle', self::normalizeHandle($m[1]));
        }

        // /c/name
        if (preg_match('#^/c/([A-Za-z0-9_.-]+)$#', $path, $m)) {
            return new self('handle', self::normalizeHandle($m[1]));
        }

        // /user/name
        if (preg_match('#^/user/([A-Za-z0-9_.-]+)$#', $path, $m)) {
            return new self('username', $m[1]);
        }

        return null;
    }

    /** handle の表記揺れを正規化する（末尾スラッシュ除去） */
    private static function normalizeHandle(string $handle): string
    {
        return rtrim(trim($handle), '/');
    }
}

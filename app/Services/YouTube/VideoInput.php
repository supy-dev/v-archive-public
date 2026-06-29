<?php

declare(strict_types=1);

namespace App\Services\YouTube;

/**
 * YouTube 動画 URL から video ID を抽出した値オブジェクト。
 *
 * 対応形式:
 *   - https://www.youtube.com/watch?v=VIDEO_ID
 *   - https://youtu.be/VIDEO_ID
 *   - https://www.youtube.com/live/VIDEO_ID
 *   - https://www.youtube.com/shorts/VIDEO_ID
 *   - VIDEO_ID（11文字の生ID）
 */
final class VideoInput
{
    public function __construct(
        public readonly string $videoId,
    ) {}

    public static function fromUrl(string $url): ?self
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        // 生の動画ID（11文字 [A-Za-z0-9_-]）
        if (preg_match('#^[A-Za-z0-9_-]{11}$#', $url)) {
            return new self($url);
        }

        $parsed = parse_url($url);

        if ($parsed === false) {
            return null;
        }

        $host = strtolower($parsed['host'] ?? '');
        $path = rtrim($parsed['path'] ?? '', '/');

        // youtu.be/VIDEO_ID
        if ($host === 'youtu.be') {
            $id = ltrim($path, '/');

            return self::validId($id) ? new self($id) : null;
        }

        if (! in_array($host, ['www.youtube.com', 'youtube.com', 'm.youtube.com'], true)) {
            return null;
        }

        // /watch?v=VIDEO_ID
        if ($path === '/watch') {
            parse_str($parsed['query'] ?? '', $query);
            $id = $query['v'] ?? '';

            return self::validId($id) ? new self($id) : null;
        }

        // /live/VIDEO_ID または /shorts/VIDEO_ID
        if (preg_match('#^/(?:live|shorts)/([A-Za-z0-9_-]{11})$#', $path, $m)) {
            return new self($m[1]);
        }

        return null;
    }

    private static function validId(string $id): bool
    {
        return preg_match('#^[A-Za-z0-9_-]{11}$#', $id) === 1;
    }
}

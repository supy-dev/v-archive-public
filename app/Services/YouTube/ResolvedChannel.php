<?php

declare(strict_types=1);

namespace App\Services\YouTube;

/**
 * YouTube API の channels.list レスポンスを正規化した値オブジェクト。
 */
final class ResolvedChannel
{
    public function __construct(
        public readonly string $youtubeChannelId,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $handle,
        public readonly ?string $thumbnailUrl,
        public readonly ?string $uploadsPlaylistId,
        public readonly ?string $publishedAt,
    ) {}

    /**
     * channels.list の items[n] から生成する。
     *
     * @param array<string, mixed> $item
     */
    public static function fromApiResponse(array $item): self
    {
        $snippet        = $item['snippet'] ?? [];
        $contentDetails = $item['contentDetails'] ?? [];

        // handle の @ プレフィックスを除去して保存
        $rawHandle = $snippet['customUrl'] ?? null;
        $handle    = $rawHandle !== null
            ? ltrim((string) $rawHandle, '@')
            : null;

        $thumbnailUrl = $snippet['thumbnails']['medium']['url']
            ?? $snippet['thumbnails']['default']['url']
            ?? null;

        return new self(
            youtubeChannelId:  (string) $item['id'],
            title:             (string) ($snippet['title'] ?? ''),
            description:       isset($snippet['description']) ? (string) $snippet['description'] : null,
            handle:            $handle !== '' ? $handle : null,
            thumbnailUrl:      $thumbnailUrl,
            uploadsPlaylistId: $contentDetails['relatedPlaylists']['uploads'] ?? null,
            publishedAt:       $snippet['publishedAt'] ?? null,
        );
    }
}

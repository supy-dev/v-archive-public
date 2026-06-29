<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use Carbon\Carbon;

/**
 * YouTube Data API の videos.list レスポンスを正規化した Value Object。
 *
 * SyncChannelVideosService に渡す前のデータ変換済み中間表現。
 * description は先頭 500 文字のみ保持（research.md Decision 2）。
 */
final readonly class ResolvedVideo
{
    public function __construct(
        public string  $youtubeVideoId,
        public string  $youtubeChannelId,
        public string  $title,
        public ?string $description,
        public ?string $thumbnailUrl,
        public Carbon  $publishedAt,
        public ?int    $durationSeconds,
        public string  $videoType,
        public string  $liveStatus,
        public ?Carbon $scheduledStartAt,
        public ?Carbon $actualStartAt,
        public ?Carbon $actualEndAt,
        public ?string $privacyStatus,
    ) {}

    /**
     * videos.list の items[] の1要素から ResolvedVideo を生成する。
     *
     * @param array<string, mixed> $item
     */
    public static function fromApiItem(array $item): self
    {
        $snippet        = $item['snippet'] ?? [];
        $contentDetails = $item['contentDetails'] ?? [];
        $status         = $item['status'] ?? [];
        $liveDetails    = $item['liveStreamingDetails'] ?? [];

        // description は先頭 500 文字に切り詰め
        $rawDescription = $snippet['description'] ?? null;
        $description    = $rawDescription !== null
            ? mb_substr($rawDescription, 0, 500)
            : null;

        // サムネイルは高品質なものを優先
        $thumbnails = $snippet['thumbnails'] ?? [];
        $thumbnailUrl = $thumbnails['maxres']['url']
            ?? $thumbnails['high']['url']
            ?? $thumbnails['medium']['url']
            ?? $thumbnails['default']['url']
            ?? null;

        $duration     = $contentDetails['duration'] ?? '';
        $videoType    = YoutubeVideoTypeResolver::resolveVideoType($item);
        $liveStatus   = YoutubeVideoTypeResolver::resolveLiveStatus($item);

        return new self(
            youtubeVideoId:   $item['id'],
            youtubeChannelId: $snippet['channelId'] ?? '',
            title:            $snippet['title'] ?? '',
            description:      $description,
            thumbnailUrl:     $thumbnailUrl,
            publishedAt:      Carbon::parse($snippet['publishedAt']),
            durationSeconds:  IsoDurationParser::toSeconds($duration),
            videoType:        $videoType->value,
            liveStatus:       $liveStatus->value,
            scheduledStartAt: !empty($liveDetails['scheduledStartTime'])
                ? Carbon::parse($liveDetails['scheduledStartTime'])
                : null,
            actualStartAt:    !empty($liveDetails['actualStartTime'])
                ? Carbon::parse($liveDetails['actualStartTime'])
                : null,
            actualEndAt:      !empty($liveDetails['actualEndTime'])
                ? Carbon::parse($liveDetails['actualEndTime'])
                : null,
            privacyStatus:    $status['privacyStatus'] ?? null,
        );
    }
}

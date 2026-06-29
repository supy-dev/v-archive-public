<?php

declare(strict_types=1);

namespace App\Services\YouTube;

/**
 * playlistItems.list API の1ページ取得結果を表す Value Object。
 */
final readonly class FetchedPlaylistPage
{
    /**
     * @param string[] $videoIds      取得した youtube_video_id の配列
     * @param ?string  $nextPageToken 次ページトークン（null なら最後のページ）
     * @param bool     $reachedKnown  fetchUntilKnown で既存 ID に到達したか
     */
    public function __construct(
        public array   $videoIds,
        public ?string $nextPageToken,
        public bool    $reachedKnown = false,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use Illuminate\Support\Str;

/**
 * ResolvedVideo の配列を youtube_videos テーブルへ upsert するサービス。
 *
 * youtube_video_id をキーに冪等実行（憲法 II / research.md Decision 7）。
 * HasUuids トレイトは upsert() では自動生成されないため、id を事前に明示付与する。
 */
class SyncChannelVideosService
{
    /**
     * 動画詳細リストを upsert して影響件数を返す。
     *
     * @param  ResolvedVideo[] $resolvedVideos
     */
    public function upsert(YoutubeChannel $channel, array $resolvedVideos): int
    {
        if (empty($resolvedVideos)) {
            return 0;
        }

        $now  = now();
        $rows = [];

        // 既存の youtube_video_id → uuid を取得（INSERT 時の id 重複を避けるため）
        $videoIds   = array_map(fn (ResolvedVideo $v): string => $v->youtubeVideoId, $resolvedVideos);
        $existingIds = YoutubeVideo::whereIn('youtube_video_id', $videoIds)
            ->pluck('id', 'youtube_video_id')
            ->all();

        foreach ($resolvedVideos as $video) {
            $rows[] = [
                // 既存レコードの UUID を再利用。新規なら新しい UUID を生成
                'id'                  => $existingIds[$video->youtubeVideoId] ?? (string) Str::uuid(),
                'youtube_video_id'    => $video->youtubeVideoId,
                'youtube_channel_id'  => $channel->id,
                'title'               => $video->title,
                'description'         => $video->description,
                'thumbnail_url'       => $video->thumbnailUrl,
                'published_at'        => $video->publishedAt,
                'duration_seconds'    => $video->durationSeconds,
                'video_type'          => $video->videoType,
                'live_status'         => $video->liveStatus,
                'scheduled_start_at'  => $video->scheduledStartAt,
                'actual_start_at'     => $video->actualStartAt,
                'actual_end_at'       => $video->actualEndAt,
                'privacy_status'      => $video->privacyStatus,
                'is_available'        => true,
                'last_fetched_at'     => $now,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }

        // created_at は UPDATE 対象外にして初回作成タイムスタンプを保持
        $updateColumns = [
            'youtube_channel_id',
            'title',
            'description',
            'thumbnail_url',
            'published_at',
            'duration_seconds',
            'video_type',
            'live_status',
            'scheduled_start_at',
            'actual_start_at',
            'actual_end_at',
            'privacy_status',
            'is_available',
            'last_fetched_at',
            'updated_at',
        ];

        return YoutubeVideo::upsert($rows, ['youtube_video_id'], $updateColumns);
    }
}

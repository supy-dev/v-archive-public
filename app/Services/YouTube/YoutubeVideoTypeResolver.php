<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use App\Enums\LiveStatus;
use App\Enums\VideoType;

/**
 * YouTube Data API の videos.list レスポンスから video_type と live_status を判定する。
 *
 * 判定ロジックを一か所に集約し、テスト容易性と一貫性を保つ（憲法 I）。
 */
final class YoutubeVideoTypeResolver
{
    /**
     * API レスポンスアイテムから VideoType を判定する。
     *
     * @param array<string, mixed> $item videos.list の items[] の1要素
     */
    public static function resolveVideoType(array $item): VideoType
    {
        $snippet      = $item['snippet'] ?? [];
        $contentDetails = $item['contentDetails'] ?? [];
        $liveDetails  = $item['liveStreamingDetails'] ?? null;

        $broadcastContent = $snippet['liveBroadcastContent'] ?? 'none';
        $duration         = $contentDetails['duration'] ?? '';

        // 配信予定
        if ($broadcastContent === 'upcoming') {
            return VideoType::Upcoming;
        }

        // ライブ配信中
        if ($broadcastContent === 'live' || $duration === 'P0D') {
            return VideoType::Live;
        }

        // ライブ配信の詳細が存在 → アーカイブ
        if ($liveDetails !== null) {
            return VideoType::Archive;
        }

        // 通常動画（Shorts 判定は API 非対応のため暫定スキップ）
        return VideoType::Video;
    }

    /**
     * API レスポンスアイテムから LiveStatus を判定する。
     *
     * @param array<string, mixed> $item videos.list の items[] の1要素
     */
    public static function resolveLiveStatus(array $item): LiveStatus
    {
        $snippet     = $item['snippet'] ?? [];
        $liveDetails = $item['liveStreamingDetails'] ?? null;

        // ライブ配信情報が存在しない → 通常動画
        if ($liveDetails === null) {
            return LiveStatus::None;
        }

        $broadcastContent = $snippet['liveBroadcastContent'] ?? 'none';

        if ($broadcastContent === 'upcoming') {
            return LiveStatus::Upcoming;
        }

        if ($broadcastContent === 'live') {
            return LiveStatus::Live;
        }

        // 終了時刻が存在すれば配信終了
        if (!empty($liveDetails['actualEndTime'])) {
            return LiveStatus::Completed;
        }

        return LiveStatus::Unknown;
    }
}

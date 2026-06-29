<?php

declare(strict_types=1);

namespace App\Actions\WatchItem;

use App\Enums\WatchStatus;
use App\Models\Profile;
use App\Models\UserWatchItem;
use App\Models\YoutubeVideo;

/**
 * 動画を見るリストに追加（または見送り登録）する Action。
 *
 * updateOrCreate（upsert）で同一ユーザー・同一動画の重複を防ぐ（FR-007）。
 * added_at は作成時のみ設定し、再 upsert 時は更新しない。
 */
class AddToWatchListAction
{
    public function execute(
        Profile $profile,
        YoutubeVideo $video,
        WatchStatus $status,
    ): UserWatchItem {
        // upsert: 既存レコードがあればステータスのみ更新、なければ作成
        /** @var UserWatchItem $item */
        $item = UserWatchItem::updateOrCreate(
            [
                'profile_id'       => $profile->id,
                'youtube_video_id' => $video->id,
            ],
            [
                'status'   => $status->value,
                'added_at' => now(),
            ],
        );

        return $item;
    }
}

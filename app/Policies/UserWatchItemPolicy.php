<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Profile;
use App\Models\UserWatchItem;
use App\Models\YoutubeVideo;

/**
 * 視聴アイテムリソースの認可ポリシー。
 * 憲法 III: ユーザーが操作できるのは自身のデータのみ。
 */
class UserWatchItemPolicy
{
    /**
     * 動画を見るリストに追加できる。
     *
     * 動画のチャンネルがユーザーの登録チャンネルに含まれることを確認し、
     * 他ユーザーのチャンネル動画への不正登録を防ぐ（FR-009）。
     */
    public function create(Profile $profile, YoutubeVideo $video): bool
    {
        return $profile->userChannels()
            ->where('youtube_channel_id', $video->youtube_channel_id)
            ->exists();
    }

    /** 自身の視聴アイテムのみ参照可 */
    public function view(Profile $profile, UserWatchItem $item): bool
    {
        return $item->profile_id === $profile->id;
    }

    /** 自身の視聴アイテムのみ更新可（ステータス変更） */
    public function update(Profile $profile, UserWatchItem $item): bool
    {
        return $item->profile_id === $profile->id;
    }

    /** 自身の視聴アイテムのみ削除可（未整理に戻す） */
    public function delete(Profile $profile, UserWatchItem $item): bool
    {
        return $item->profile_id === $profile->id;
    }
}

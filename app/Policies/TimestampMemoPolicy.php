<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Profile;
use App\Models\TimestampMemo;
use App\Models\UserWatchItem;

/**
 * タイムスタンプメモリソースの認可ポリシー。
 * 憲法 III: ユーザーが操作できるのは自身のデータのみ。
 */
class TimestampMemoPolicy
{
    /**
     * 指定の watch_item にメモを作成できる。
     * watch_item の所有者のみ作成可（自分の動画にのみメモを付けられる）。
     */
    public function create(Profile $profile, UserWatchItem $watchItem): bool
    {
        return $watchItem->profile_id === $profile->id;
    }

    /** 自身のメモのみ参照可 */
    public function view(Profile $profile, TimestampMemo $memo): bool
    {
        return $memo->profile_id === $profile->id;
    }

    /** 自身のメモのみ更新可 */
    public function update(Profile $profile, TimestampMemo $memo): bool
    {
        return $memo->profile_id === $profile->id;
    }

    /** 自身のメモのみ削除可 */
    public function delete(Profile $profile, TimestampMemo $memo): bool
    {
        return $memo->profile_id === $profile->id;
    }
}

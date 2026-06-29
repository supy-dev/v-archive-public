<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Profile;
use App\Models\UserWatchItem;
use App\Models\VideoNote;

/**
 * 動画ノートリソースの認可ポリシー。
 * 憲法 III: ユーザーが操作できるのは自身のデータのみ。
 */
class VideoNotePolicy
{
    /**
     * 指定の watch_item にノートを作成・更新できる（upsert）。
     * watch_item の所有者のみ操作可。
     */
    public function upsert(Profile $profile, UserWatchItem $watchItem): bool
    {
        return $watchItem->profile_id === $profile->id;
    }

    /** 自身のノートのみ削除可 */
    public function delete(Profile $profile, VideoNote $note): bool
    {
        return $note->profile_id === $profile->id;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Memo;

use App\Models\UserWatchItem;
use App\Models\VideoNote;

/**
 * 動画ノートを削除するAction（FR-006）。
 * 存在しない場合は何もしない（冪等）。
 */
class DeleteVideoNoteAction
{
    public function execute(UserWatchItem $watchItem): void
    {
        VideoNote::where('profile_id', $watchItem->profile_id)
            ->where('youtube_video_id', $watchItem->youtube_video_id)
            ->delete();
    }
}

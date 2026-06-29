<?php

declare(strict_types=1);

namespace App\Actions\Memo;

use App\Models\UserWatchItem;
use App\Models\VideoNote;

/**
 * 動画ノートを upsert するAction（FR-005）。
 *
 * 1 ユーザー・1 動画につき 1 件を UNIQUE 制約で保証しているため
 * updateOrCreate で安全に上書き保存できる。
 */
class SaveVideoNoteAction
{
    public function execute(UserWatchItem $watchItem, string $body): VideoNote
    {
        return VideoNote::updateOrCreate(
            [
                'profile_id'       => $watchItem->profile_id,
                'youtube_video_id' => $watchItem->youtube_video_id,
            ],
            ['body' => $body],
        );
    }
}

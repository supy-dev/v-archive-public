<?php

declare(strict_types=1);

namespace App\Actions\Memo;

use App\Models\TimestampMemo;

/**
 * タイムスタンプメモを削除するAction（FR-004）。
 * タグの紐付け（timestamp_memo_tags）は ON DELETE CASCADE で自動削除される。
 */
class DeleteTimestampMemoAction
{
    public function execute(TimestampMemo $memo): void
    {
        $memo->delete();
    }
}

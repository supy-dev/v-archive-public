<?php

declare(strict_types=1);

namespace App\Actions\Memo;

use App\Models\TimestampMemo;

/**
 * タイムスタンプメモの is_favorite フラグをトグルするAction（FR-010）。
 */
class ToggleMemoFavoriteAction
{
    public function execute(TimestampMemo $memo): bool
    {
        $memo->update(['is_favorite' => !$memo->is_favorite]);

        return $memo->is_favorite;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\WatchItem;

use App\Models\UserWatchItem;

/**
 * 視聴アイテムを削除する Action（FR-015）。
 *
 * 削除後、対象動画は未整理状態に戻り新着アーカイブ一覧に再表示される。
 */
class DeleteWatchItemAction
{
    public function execute(UserWatchItem $item): void
    {
        $item->delete();
    }
}

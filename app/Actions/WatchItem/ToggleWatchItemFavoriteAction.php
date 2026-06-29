<?php

declare(strict_types=1);

namespace App\Actions\WatchItem;

use App\Models\UserWatchItem;

/**
 * UserWatchItem の is_favorite フラグをトグルするAction（FR-新規）。
 */
class ToggleWatchItemFavoriteAction
{
    public function execute(UserWatchItem $watchItem): bool
    {
        $watchItem->update(['is_favorite' => !$watchItem->is_favorite]);

        return $watchItem->is_favorite;
    }
}

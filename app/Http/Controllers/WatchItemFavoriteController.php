<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\WatchItem\ToggleWatchItemFavoriteAction;
use App\Models\UserWatchItem;
use Illuminate\Http\JsonResponse;

/**
 * 神回フラグトグルコントローラー。
 * 憲法 I: Policy → Action → JSON 返却のみ。
 */
class WatchItemFavoriteController extends Controller
{
    /**
     * 神回フラグをトグルする。
     * PATCH /archives/{watchItem}/favorite → 200 JSON
     */
    public function update(
        UserWatchItem $watchItem,
        ToggleWatchItemFavoriteAction $action,
    ): JsonResponse {
        $this->authorize('update', $watchItem);

        $isFavorite = $action->execute($watchItem);

        return response()->json(['is_favorite' => $isFavorite]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Memo\ToggleMemoFavoriteAction;
use App\Models\TimestampMemo;
use App\Models\UserWatchItem;
use Illuminate\Http\JsonResponse;

/**
 * タイムスタンプメモのお気に入りトグルコントローラー。
 * 憲法 I: Policy → Action → JSON 返却のみ。
 */
class TimestampMemoFavoriteController extends Controller
{
    /**
     * お気に入りフラグをトグルする（FR-010）。
     * PATCH /archives/{watchItem}/memos/{memo}/favorite → 200 JSON
     */
    public function update(
        UserWatchItem $watchItem,
        TimestampMemo $memo,
        ToggleMemoFavoriteAction $action,
    ): JsonResponse {
        $this->authorize('update', $memo);

        $isFavorite = $action->execute($memo);

        return response()->json(['is_favorite' => $isFavorite]);
    }
}

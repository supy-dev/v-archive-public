<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Memo\DeleteVideoNoteAction;
use App\Actions\Memo\SaveVideoNoteAction;
use App\Http\Requests\SaveVideoNoteRequest;
use App\Models\UserWatchItem;
use Illuminate\Http\JsonResponse;

/**
 * 動画ノート（全体感想）の upsert / destroy コントローラー。
 * 憲法 I: FormRequest → Policy → Action → JSON 返却のみ。
 */
class VideoNoteController extends Controller
{
    /**
     * 動画ノートを upsert する（FR-005）。
     * PUT /archives/{watchItem}/note → 200 JSON
     * watchItem の所有権確認に UserWatchItemPolicy::update を再利用する。
     */
    public function upsert(
        SaveVideoNoteRequest $request,
        UserWatchItem $watchItem,
        SaveVideoNoteAction $action,
    ): JsonResponse {
        $this->authorize('update', $watchItem);

        $note = $action->execute($watchItem, (string) $request->validated('body'));

        return response()->json([
            'status'     => 'saved',
            'updated_at' => $note->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * 動画ノートを削除する（FR-006）。
     * DELETE /archives/{watchItem}/note → 204
     */
    public function destroy(
        UserWatchItem $watchItem,
        DeleteVideoNoteAction $action,
    ): JsonResponse {
        $this->authorize('update', $watchItem);

        $action->execute($watchItem);

        return response()->json(null, 204);
    }
}

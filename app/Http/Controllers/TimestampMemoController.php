<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Memo\CreateTimestampMemoAction;
use App\Actions\Memo\DeleteTimestampMemoAction;
use App\Actions\Memo\UpdateTimestampMemoAction;
use App\Http\Requests\StoreTimestampMemoRequest;
use App\Http\Requests\UpdateTimestampMemoRequest;
use App\Models\TimestampMemo;
use App\Models\UserWatchItem;
use Illuminate\Http\JsonResponse;

/**
 * タイムスタンプメモの CRUD コントローラー。
 * 憲法 I: FormRequest → Policy → Action → JSON 返却のみ。
 * FR-003a: 応答は JSON。Alpine.js がリストをインプレース更新する。
 */
class TimestampMemoController extends Controller
{
    /**
     * タイムスタンプメモを新規作成する（FR-001）。
     * POST /archives/{watchItem}/memos → 201 JSON
     */
    public function store(
        StoreTimestampMemoRequest $request,
        UserWatchItem $watchItem,
        CreateTimestampMemoAction $action,
    ): JsonResponse {
        $this->authorize('create', [TimestampMemo::class, $watchItem]);

        $memo = $action->execute(
            watchItem:    $watchItem,
            seconds:      (int) $request->validated('seconds'),
            body:         (string) $request->validated('body'),
            tagIds:       (array) ($request->validated('tag_ids') ?? []),
            newTagNames:  (array) ($request->validated('new_tag_names') ?? []),
        );

        return response()->json(['memo' => $this->formatMemo($memo, $watchItem)], 201);
    }

    /**
     * タイムスタンプメモを更新する（FR-004）。
     * PATCH /archives/{watchItem}/memos/{memo} → 200 JSON
     */
    public function update(
        UpdateTimestampMemoRequest $request,
        UserWatchItem $watchItem,
        TimestampMemo $memo,
        UpdateTimestampMemoAction $action,
    ): JsonResponse {
        $this->authorize('update', $memo);

        $memo = $action->execute(
            memo:         $memo,
            watchItem:    $watchItem,
            seconds:      (int) $request->validated('seconds'),
            body:         (string) $request->validated('body'),
            tagIds:       (array) ($request->validated('tag_ids') ?? []),
            newTagNames:  (array) ($request->validated('new_tag_names') ?? []),
        );

        return response()->json(['memo' => $this->formatMemo($memo, $watchItem)]);
    }

    /**
     * タイムスタンプメモを削除する（FR-004）。
     * DELETE /archives/{watchItem}/memos/{memo} → 204
     */
    public function destroy(
        UserWatchItem $watchItem,
        TimestampMemo $memo,
        DeleteTimestampMemoAction $action,
    ): JsonResponse {
        $this->authorize('delete', $memo);

        $action->execute($memo);

        return response()->json(null, 204);
    }

    /** メモを JSON シリアライズ用配列に変換する */
    private function formatMemo(TimestampMemo $memo, UserWatchItem $watchItem): array
    {
        $videoId = $watchItem->youtubeVideo?->youtube_video_id ?? '';

        return [
            'id'            => $memo->id,
            'seconds'       => $memo->seconds,
            'seconds_label' => $memo->seconds_label,
            'body'          => $memo->body,
            'is_favorite'   => $memo->is_favorite,
            'tags'          => $memo->tags->map(fn ($tag) => [
                'id'    => $tag->id,
                'name'  => $tag->name,
                'slug'  => $tag->slug,
                'color' => $tag->color,
            ])->values()->all(),
            'youtube_url'   => "https://www.youtube.com/watch?v={$videoId}&t={$memo->seconds}s",
            'created_at'    => $memo->created_at?->toIso8601String(),
        ];
    }
}

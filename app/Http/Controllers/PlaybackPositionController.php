<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\WatchItem\UpdatePlaybackPositionAction;
use App\Http\Requests\UpdatePlaybackPositionRequest;
use App\Models\UserWatchItem;
use Illuminate\Http\Response;

/**
 * 再生位置保存 API コントローラー（FR-017）。
 *
 * PATCH /watch-items/{watchItem}/position
 * 憲法 I: FormRequest バリデーション → Policy 確認 → Action 呼び出し → 204 返却のみ。
 */
class PlaybackPositionController extends Controller
{
    /**
     * 再生位置を保存し、ステータスを自動遷移させる。
     *
     * レートリミット（10 req/分/ユーザー）は routes/web.php の throttle:playback-position で適用済み。
     * 所有権確認（UserWatchItemPolicy::update）はここで実施する（FR-009）。
     */
    public function update(
        UpdatePlaybackPositionRequest $request,
        UserWatchItem $watchItem,
        UpdatePlaybackPositionAction $action,
    ): Response {
        $this->authorize('update', $watchItem);

        $validated = $request->validated();
        $action->execute(
            $watchItem,
            (int) $validated['last_position_seconds'],
            (bool) $validated['is_ended'],
        );

        return response()->noContent();
    }
}

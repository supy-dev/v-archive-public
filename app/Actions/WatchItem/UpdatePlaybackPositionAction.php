<?php

declare(strict_types=1);

namespace App\Actions\WatchItem;

use App\Enums\WatchStatus;
use App\Models\UserWatchItem;

/**
 * 再生位置を保存し、視聴ステータスを自動遷移させる Action（FR-004 / FR-008 / FR-010 / FR-011）。
 *
 * 遷移ルール:
 * - is_ended=false かつ status=want_to_watch → watching へ遷移（started_at は未設定時のみ設定）
 * - is_ended=false かつ status=watching/watched/skipped → 変化なし（once skipped, stays skipped）
 * - is_ended=true  かつ status!=watched → watched へ遷移（watched_at=now()）
 * - is_ended=true  かつ status=watched  → 変化なし（冪等）
 *
 * 上書き防止（FR-011）:
 * - is_ended=false 時は新しい値 > 現在値（または現在値が null）の場合のみ last_position_seconds を更新
 * - is_ended=true  時は常に保存（最終位置として確定）
 */
class UpdatePlaybackPositionAction
{
    public function execute(UserWatchItem $item, int $newPositionSeconds, bool $isEnded): void
    {
        $updates = [];

        if ($isEnded) {
            // 動画終了: watched でなければ watched へ遷移
            if ($item->status !== WatchStatus::Watched) {
                $updates['status']     = WatchStatus::Watched->value;
                $updates['watched_at'] = now();
            }
            // 終了時は上書き防止なしで最終位置を確定
            $updates['last_position_seconds'] = $newPositionSeconds;
        } else {
            // 再生中: want_to_watch のみ watching へ遷移
            if ($item->status === WatchStatus::WantToWatch) {
                $updates['status'] = WatchStatus::Watching->value;
                if ($item->started_at === null) {
                    $updates['started_at'] = now();
                }
            }

            // 上書き防止: 新しい値が現在値より大きい（または未設定）場合のみ更新
            if ($item->last_position_seconds === null || $newPositionSeconds > $item->last_position_seconds) {
                $updates['last_position_seconds'] = $newPositionSeconds;
            }
        }

        if (!empty($updates)) {
            $item->update($updates);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\WatchItem;

use App\Enums\WatchStatus;
use App\Models\UserWatchItem;

/**
 * 視聴ステータスを変更し、対応するタイムスタンプを自動設定する Action（FR-008）。
 *
 * タイムスタンプ対応:
 * - want_to_watch: タイムスタンプ変更なし
 * - watching:      started_at を更新（Feature 005 で使用）
 * - watched:       watched_at を更新
 * - skipped:       skipped_at を更新
 */
class UpdateWatchStatusAction
{
    public function execute(UserWatchItem $item, WatchStatus $newStatus): UserWatchItem
    {
        $updates = ['status' => $newStatus->value];

        // ステータスに対応するタイムスタンプを設定する
        $tsColumn = $newStatus->timestampColumn();
        if ($tsColumn !== null) {
            $updates[$tsColumn] = now();
        }

        $item->update($updates);

        return $item;
    }
}

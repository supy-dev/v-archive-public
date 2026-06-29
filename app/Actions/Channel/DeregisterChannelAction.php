<?php

declare(strict_types=1);

namespace App\Actions\Channel;

use App\Models\UserChannel;
use Illuminate\Support\Facades\DB;

class DeregisterChannelAction
{
    /**
     * チャンネル登録を解除する。
     *
     * メインチャンネルを解除する場合は、残存する最古のチャンネルを新しいメインに昇格させる。
     * 共有マスタ（youtube_channels）は削除しない（FR-017）。
     *
     * 部分ユニークインデックス（profile_id, oshi_id WHERE is_main=true）の制約を回避するため、
     * DELETE を先に実行し、その後 nextMain を昇格させる。
     */
    public function execute(UserChannel $userChannel): void
    {
        DB::transaction(function () use ($userChannel): void {
            $needsNewMain    = $userChannel->is_main;
            $profileId       = $userChannel->profile_id;
            $oshiId          = $userChannel->oshi_id;
            $currentId       = $userChannel->id;

            // 先に削除（is_main=true のレコードを消すことで制約が解除される）
            $userChannel->delete();

            // メインだった場合は残存チャンネルで最古のものを昇格
            if ($needsNewMain) {
                UserChannel::where('profile_id', $profileId)
                    ->where('oshi_id', $oshiId)
                    ->oldest('registered_at')
                    ->first()
                    ?->update(['is_main' => true]);
            }
        });
    }
}

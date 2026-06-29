<?php

declare(strict_types=1);

namespace App\Actions\Channel;

use App\Models\UserChannel;
use Illuminate\Support\Facades\DB;

class SetMainChannelAction
{
    /**
     * 指定チャンネルをメインに設定する。
     *
     * トランザクション内で以下を実行:
     * 1. 同一 (profile_id, oshi_id) の既存メインを false に更新
     * 2. 指定 userChannel の is_main を true に更新
     *
     * 部分ユニークインデックス（profile_id, oshi_id WHERE is_main=true）が
     * DB レベルで複数メインを阻止する。
     */
    public function execute(UserChannel $userChannel): void
    {
        DB::transaction(function () use ($userChannel): void {
            // 同一ユーザー・同一推しの既存メインを解除（部分インデックス違反を防ぐため先に解除）
            UserChannel::where('profile_id', $userChannel->profile_id)
                ->where('oshi_id', $userChannel->oshi_id)
                ->where('is_main', true)
                ->update(['is_main' => false]);

            // 指定チャンネルをメインに設定
            $userChannel->update(['is_main' => true]);
        });
    }
}

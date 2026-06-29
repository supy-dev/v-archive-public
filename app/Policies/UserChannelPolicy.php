<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Oshi;
use App\Models\Profile;
use App\Models\UserChannel;

/**
 * チャンネル登録リソースの認可ポリシー。
 * 憲法 III: ユーザーが操作できるのは自身のデータのみ。
 */
class UserChannelPolicy
{
    /**
     * 自分の推しにチャンネルを登録できる。
     * 第2引数の Oshi で対象推しの所有権を確認する。
     */
    public function create(Profile $profile, Oshi $oshi): bool
    {
        return $oshi->profile_id === $profile->id;
    }

    /** 自分のチャンネル登録のみ参照可 */
    public function view(Profile $profile, UserChannel $userChannel): bool
    {
        return $userChannel->profile_id === $profile->id;
    }

    /** 自分のチャンネル登録のみ更新可（設定・メイン変更） */
    public function update(Profile $profile, UserChannel $userChannel): bool
    {
        return $userChannel->profile_id === $profile->id;
    }

    /** 自分のチャンネル登録のみ削除可 */
    public function delete(Profile $profile, UserChannel $userChannel): bool
    {
        return $userChannel->profile_id === $profile->id;
    }
}

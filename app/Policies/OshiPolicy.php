<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Oshi;
use App\Models\Profile;

/**
 * 推しリソースの認可ポリシー。
 * 憲法 III: ユーザーが操作できるのは自身のデータのみ。
 */
class OshiPolicy
{
    /** ログイン済みユーザーなら推しを作成できる */
    public function create(Profile $profile): bool
    {
        return true;
    }

    /** 自分の推しのみ参照可 */
    public function view(Profile $profile, Oshi $oshi): bool
    {
        return $oshi->profile_id === $profile->id;
    }

    /** 自分の推しのみ更新可 */
    public function update(Profile $profile, Oshi $oshi): bool
    {
        return $oshi->profile_id === $profile->id;
    }

    /** 自分の推しのみ削除可 */
    public function delete(Profile $profile, Oshi $oshi): bool
    {
        return $oshi->profile_id === $profile->id;
    }
}

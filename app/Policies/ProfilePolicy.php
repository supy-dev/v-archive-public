<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Profile;

class ProfilePolicy
{
    /**
     * ユーザーは自分のプロフィールのみ閲覧できる（UUID 一致による所有権確認）。
     */
    public function view(Profile $user, Profile $profile): bool
    {
        return $user->getKey() === $profile->getKey();
    }

    public function update(Profile $user, Profile $profile): bool
    {
        return $user->getKey() === $profile->getKey();
    }
}

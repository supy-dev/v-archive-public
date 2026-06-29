<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Profile;
use App\Models\Tag;

/**
 * タグリソースの認可ポリシー。
 * 憲法 III: システムタグは読み取り専用、ユーザー固有タグは所有者のみ変更可。
 */
class TagPolicy
{
    /** 認証済みユーザーはタグを作成できる（ユーザー固有タグのインライン作成） */
    public function create(Profile $profile): bool
    {
        return true;
    }

    /** 自身の固有タグのみ削除可（システムタグは削除不可） */
    public function delete(Profile $profile, Tag $tag): bool
    {
        return !$tag->is_system && $tag->profile_id === $profile->id;
    }
}

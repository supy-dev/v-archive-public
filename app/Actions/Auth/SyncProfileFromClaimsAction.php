<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\Profile;
use App\Services\Auth\VerifiedClaims;
use Illuminate\Support\Str;

/**
 * 検証済み Supabase ユーザーに対応するローカルの profiles を冪等に作成する。
 * キーは JWT の `sub`(UUID) であり、Supabase が同一ユーザーにリンクした複数の
 * identity（例: Google ＋ メール）は常に単一の profiles に解決される
 * （FR-005a / FR-006 / SC-010）。
 */
class SyncProfileFromClaimsAction
{
    public function execute(VerifiedClaims $claims): Profile
    {
        return Profile::firstOrCreate(
            ['id' => $claims->sub],
            [
                'display_name' => $this->deriveDisplayName($claims),
                'avatar_url' => $claims->picture,
                'timezone' => 'Asia/Tokyo',
            ],
        );
    }

    private function deriveDisplayName(VerifiedClaims $claims): string
    {
        if ($claims->name !== null && trim($claims->name) !== '') {
            return $claims->name;
        }

        if ($claims->email !== null && Str::contains($claims->email, '@')) {
            return Str::before($claims->email, '@');
        }

        return 'ユーザー';
    }
}

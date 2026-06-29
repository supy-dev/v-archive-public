<?php

declare(strict_types=1);

use App\Actions\Auth\SyncProfileFromClaimsAction;
use App\Models\Profile;
use App\Services\Auth\VerifiedClaims;

function makeClaims(array $overrides = []): VerifiedClaims
{
    return VerifiedClaims::fromPayload(array_merge([
        'sub' => '22222222-2222-2222-2222-222222222222',
        'email' => 'fan@example.com',
        'email_verified' => true,
        'iss' => 'https://project.supabase.co/auth/v1',
        'aud' => 'authenticated',
        'exp' => time() + 3600,
    ], $overrides));
}

it('creates a profile from claims with name and picture', function () {
    $profile = (new SyncProfileFromClaimsAction)->execute(
        makeClaims(['name' => 'みこ', 'picture' => 'https://img/avatar.png'])
    );

    expect($profile->display_name)->toBe('みこ')
        ->and($profile->avatar_url)->toBe('https://img/avatar.png')
        ->and($profile->timezone)->toBe('Asia/Tokyo')
        ->and(Profile::count())->toBe(1);
});

it('derives display name from the email local part when name is absent', function () {
    $profile = (new SyncProfileFromClaimsAction)->execute(makeClaims());

    expect($profile->display_name)->toBe('fan');
});

it('falls back to a default display name when name and email are absent', function () {
    $profile = (new SyncProfileFromClaimsAction)->execute(
        makeClaims(['email' => null])
    );

    expect($profile->display_name)->toBe('ユーザー');
});

it('is idempotent: the same sub never creates a duplicate profile (SC-010/FR-005a)', function () {
    $action = new SyncProfileFromClaimsAction;

    // 1つ目の identity（例: Google）。
    $action->execute(makeClaims(['name' => 'Google Name']));
    // リンク済みメール identity 経由の同一 Supabase ユーザー → 同じ sub。
    $second = $action->execute(makeClaims(['name' => 'Email Name']));

    expect(Profile::count())->toBe(1)
        ->and($second->id)->toBe('22222222-2222-2222-2222-222222222222')
        // 再同期しても既存プロフィールは保持される（上書きしない）。
        ->and($second->display_name)->toBe('Google Name');
});

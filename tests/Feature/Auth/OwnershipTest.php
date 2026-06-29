<?php

declare(strict_types=1);

use App\Models\Profile;

it('lets a user view their own profile', function () {
    $user = Profile::factory()->create();

    expect($user->can('view', $user))->toBeTrue();
});

it('forbids viewing another user\'s profile via the policy', function () {
    $user = Profile::factory()->create();
    $other = Profile::factory()->create();

    expect($user->can('view', $other))->toBeFalse();
});

it('always renders the authenticated user\'s own profile, never an impersonated one', function () {
    $user = Profile::factory()->create(['display_name' => '本人']);
    Profile::factory()->create(['display_name' => '別人']);

    // 本人識別はサーバセッションのみから取得し、リクエスト由来のIDは無視する（FR-004）。
    $this->actingAs($user)
        ->get('/profile?id='.Profile::factory()->create()->id)
        ->assertOk()
        ->assertSee('本人')
        ->assertDontSee('別人');
});

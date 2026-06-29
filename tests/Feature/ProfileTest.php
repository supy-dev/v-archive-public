<?php

declare(strict_types=1);

use App\Models\Profile;

it('shows the authenticated user their display name, timezone and avatar', function () {
    $user = Profile::factory()->create([
        'display_name' => 'みこ',
        'timezone' => 'Asia/Tokyo',
        'avatar_url' => 'https://img/avatar.png',
    ]);

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk()
        ->assertSee('みこ')
        ->assertSee('Asia/Tokyo')
        ->assertSee('https://img/avatar.png')
        ->assertSee('アカウント・ログイン')
        ->assertSee('データとプライバシー');
});

it('requires authentication to view a profile', function () {
    $this->get('/profile')->assertRedirect('/login');
});

it('updates the authenticated user profile settings', function () {
    $user = Profile::factory()->create();

    $this->actingAs($user)
        ->patch('/profile', [
            'display_name' => '新しい表示名',
            'timezone' => 'UTC',
        ])
        ->assertRedirect('/profile#profile-settings')
        ->assertSessionHas('status');

    $this->assertDatabaseHas('profiles', [
        'id' => $user->id,
        'display_name' => '新しい表示名',
        'timezone' => 'UTC',
    ]);
});

it('validates profile settings', function () {
    $user = Profile::factory()->create();

    $this->actingAs($user)
        ->from('/profile')
        ->patch('/profile', [
            'display_name' => '',
            'timezone' => 'Invalid/Timezone',
        ])
        ->assertRedirect('/profile')
        ->assertSessionHasErrors(['display_name', 'timezone']);
});

it('requires authentication to update a profile', function () {
    $this->patch('/profile', [
        'display_name' => 'ゲスト',
        'timezone' => 'Asia/Tokyo',
    ])->assertRedirect('/login');
});

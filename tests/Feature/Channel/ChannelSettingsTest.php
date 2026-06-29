<?php

declare(strict_types=1);

use App\Models\Oshi;
use App\Models\Profile;
use App\Models\UserChannel;
use App\Models\YoutubeChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sync_enabled を OFF にできる（FR-012）', function (): void {
    $user = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $user->id]);
    $ch = YoutubeChannel::factory()->create();
    $uc = UserChannel::factory()->main()->create([
        'profile_id' => $user->id,
        'oshi_id' => $oshi->id,
        'youtube_channel_id' => $ch->id,
        'sync_enabled' => true,
    ]);

    $this->actingAs($user)
        ->patch("/oshis/{$oshi->id}/channels/{$uc->id}", ['sync_enabled' => '0'])
        ->assertRedirect("/oshis/{$oshi->id}");

    $this->assertDatabaseHas('user_channels', ['id' => $uc->id, 'sync_enabled' => false]);
});

it('通知設定は一時停止中のため変更できない', function (): void {
    $user = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $user->id]);
    $ch = YoutubeChannel::factory()->create();
    $uc = UserChannel::factory()->main()->create([
        'profile_id' => $user->id,
        'oshi_id' => $oshi->id,
        'youtube_channel_id' => $ch->id,
        'notify_enabled' => false,
    ]);

    $this->actingAs($user)
        ->patch("/oshis/{$oshi->id}/channels/{$uc->id}", ['notify_enabled' => '1'])
        ->assertSessionHasErrors('sync_enabled');

    $this->assertDatabaseHas('user_channels', ['id' => $uc->id, 'notify_enabled' => false]);
});

it('他ユーザーの設定は変更できない', function (): void {
    $user = Profile::factory()->create();
    $other = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $other->id]);
    $ch = YoutubeChannel::factory()->create();
    $uc = UserChannel::factory()->main()->create([
        'profile_id' => $other->id,
        'oshi_id' => $oshi->id,
        'youtube_channel_id' => $ch->id,
    ]);

    $this->actingAs($user)
        ->patch("/oshis/{$oshi->id}/channels/{$uc->id}", ['sync_enabled' => '0'])
        ->assertForbidden();
});

<?php

declare(strict_types=1);

use App\Models\Oshi;
use App\Models\Profile;
use App\Models\UserChannel;
use App\Models\YoutubeChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('自分の推しを削除できる', function (): void {
    $user = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $user->id]);

    $this->actingAs($user)
        ->delete("/oshis/{$oshi->id}")
        ->assertRedirect('/oshis');

    $this->assertDatabaseMissing('oshis', ['id' => $oshi->id]);
});

it('推しを削除すると紐づく user_channels も削除される', function (): void {
    $user    = Profile::factory()->create();
    $oshi    = Oshi::factory()->create(['profile_id' => $user->id]);
    $channel = YoutubeChannel::factory()->create();
    UserChannel::factory()->create([
        'profile_id'         => $user->id,
        'oshi_id'            => $oshi->id,
        'youtube_channel_id' => $channel->id,
        'is_main'            => true,
    ]);

    $this->actingAs($user)->delete("/oshis/{$oshi->id}");

    // user_channels は削除される
    $this->assertDatabaseMissing('user_channels', ['oshi_id' => $oshi->id]);
    // 共有マスタは残る（FR-017）
    $this->assertDatabaseHas('youtube_channels', ['id' => $channel->id]);
});

it('他ユーザーの推しは削除できない', function (): void {
    $user  = Profile::factory()->create();
    $other = Profile::factory()->create();
    $oshi  = Oshi::factory()->create(['profile_id' => $other->id]);

    $this->actingAs($user)
        ->delete("/oshis/{$oshi->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('oshis', ['id' => $oshi->id]);
});

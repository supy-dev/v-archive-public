<?php

declare(strict_types=1);

use App\Models\Oshi;
use App\Models\Profile;
use App\Models\UserChannel;
use App\Models\YoutubeChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('自分のチャンネル登録を解除できる', function (): void {
    $user       = Profile::factory()->create();
    $oshi       = Oshi::factory()->create(['profile_id' => $user->id]);
    $channel    = YoutubeChannel::factory()->create();
    $userChannel = UserChannel::factory()->main()->create([
        'profile_id'         => $user->id,
        'oshi_id'            => $oshi->id,
        'youtube_channel_id' => $channel->id,
    ]);

    $this->actingAs($user)
        ->delete("/oshis/{$oshi->id}/channels/{$userChannel->id}")
        ->assertRedirect("/oshis/{$oshi->id}");

    $this->assertDatabaseMissing('user_channels', ['id' => $userChannel->id]);
    // 共有マスタは残る（FR-017）
    $this->assertDatabaseHas('youtube_channels', ['id' => $channel->id]);
});

it('メインチャンネルを解除すると最古の別チャンネルが自動的にメインになる', function (): void {
    $user    = Profile::factory()->create();
    $oshi    = Oshi::factory()->create(['profile_id' => $user->id]);
    $channel1 = YoutubeChannel::factory()->create();
    $channel2 = YoutubeChannel::factory()->create();

    $mainChannel = UserChannel::factory()->main()->create([
        'profile_id'         => $user->id,
        'oshi_id'            => $oshi->id,
        'youtube_channel_id' => $channel1->id,
        'registered_at'      => now()->subMinutes(10),
    ]);

    $otherChannel = UserChannel::factory()->create([
        'profile_id'         => $user->id,
        'oshi_id'            => $oshi->id,
        'youtube_channel_id' => $channel2->id,
        'is_main'            => false,
        'registered_at'      => now(),
    ]);

    $this->actingAs($user)
        ->delete("/oshis/{$oshi->id}/channels/{$mainChannel->id}");

    // 残ったチャンネルがメインになっている
    $this->assertDatabaseMissing('user_channels', ['id' => $mainChannel->id]);
    $this->assertDatabaseHas('user_channels', ['id' => $otherChannel->id, 'is_main' => true]);
});

it('他ユーザーのチャンネル登録は解除できない', function (): void {
    $user       = Profile::factory()->create();
    $other      = Profile::factory()->create();
    $oshi       = Oshi::factory()->create(['profile_id' => $other->id]);
    $channel    = YoutubeChannel::factory()->create();
    $userChannel = UserChannel::factory()->main()->create([
        'profile_id'         => $other->id,
        'oshi_id'            => $oshi->id,
        'youtube_channel_id' => $channel->id,
    ]);

    $this->actingAs($user)
        ->delete("/oshis/{$oshi->id}/channels/{$userChannel->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('user_channels', ['id' => $userChannel->id]);
});

it('他ユーザーの登録解除は共有マスタと他ユーザーの登録に影響しない', function (): void {
    $user1  = Profile::factory()->create();
    $user2  = Profile::factory()->create();
    $oshi1  = Oshi::factory()->create(['profile_id' => $user1->id]);
    $oshi2  = Oshi::factory()->create(['profile_id' => $user2->id]);
    $channel = YoutubeChannel::factory()->create();

    $uc1 = UserChannel::factory()->main()->create([
        'profile_id'         => $user1->id,
        'oshi_id'            => $oshi1->id,
        'youtube_channel_id' => $channel->id,
    ]);
    UserChannel::factory()->main()->create([
        'profile_id'         => $user2->id,
        'oshi_id'            => $oshi2->id,
        'youtube_channel_id' => $channel->id,
    ]);

    // user1 が解除
    $this->actingAs($user1)->delete("/oshis/{$oshi1->id}/channels/{$uc1->id}");

    // user2 の登録は影響なし
    $this->assertDatabaseHas('user_channels', ['profile_id' => $user2->id]);
    // 共有マスタも残る
    $this->assertDatabaseHas('youtube_channels', ['id' => $channel->id]);
});

<?php

declare(strict_types=1);

use App\Models\Oshi;
use App\Models\Profile;
use App\Models\UserChannel;
use App\Models\YoutubeChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('未認証でチャンネル登録しようとすると /login へリダイレクト', function (): void {
    $oshi = Oshi::factory()->create();
    $this->post("/oshis/{$oshi->id}/channels", ['channel_url' => '@test'])
        ->assertRedirect('/login');
});

it('未認証でチャンネル解除しようとすると /login へリダイレクト', function (): void {
    $oshi = Oshi::factory()->create();
    $ch   = YoutubeChannel::factory()->create();
    $uc   = UserChannel::factory()->main()->create([
        'profile_id' => $oshi->profile_id, 'oshi_id' => $oshi->id, 'youtube_channel_id' => $ch->id,
    ]);

    $this->delete("/oshis/{$oshi->id}/channels/{$uc->id}")
        ->assertRedirect('/login');
});

it('他ユーザーの推しへのチャンネル登録は 403', function (): void {
    $user  = Profile::factory()->create();
    $other = Profile::factory()->create();
    $oshi  = Oshi::factory()->create(['profile_id' => $other->id]);

    $this->actingAs($user)
        ->post("/oshis/{$oshi->id}/channels", ['channel_url' => '@test'])
        ->assertForbidden();
});

it('他ユーザーの UserChannel への PATCH は 403', function (): void {
    $user  = Profile::factory()->create();
    $other = Profile::factory()->create();
    $oshi  = Oshi::factory()->create(['profile_id' => $other->id]);
    $ch    = YoutubeChannel::factory()->create();
    $uc    = UserChannel::factory()->main()->create([
        'profile_id' => $other->id, 'oshi_id' => $oshi->id, 'youtube_channel_id' => $ch->id,
    ]);

    $this->actingAs($user)
        ->patch("/oshis/{$oshi->id}/channels/{$uc->id}", ['sync_enabled' => '0'])
        ->assertForbidden();
});

it('他ユーザーの UserChannel への PUT/main は 403', function (): void {
    $user  = Profile::factory()->create();
    $other = Profile::factory()->create();
    $oshi  = Oshi::factory()->create(['profile_id' => $other->id]);
    $ch    = YoutubeChannel::factory()->create();
    $uc    = UserChannel::factory()->main()->create([
        'profile_id' => $other->id, 'oshi_id' => $oshi->id, 'youtube_channel_id' => $ch->id,
    ]);

    $this->actingAs($user)
        ->put("/oshis/{$oshi->id}/channels/{$uc->id}/main")
        ->assertForbidden();
});

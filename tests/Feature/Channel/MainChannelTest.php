<?php

declare(strict_types=1);

use App\Models\Oshi;
use App\Models\Profile;
use App\Models\UserChannel;
use App\Models\YoutubeChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('最初のチャンネル登録で is_main が true になる（FR-009）', function (): void {
    $user = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $user->id]);

    Http::fake([
        'googleapis.com/*' => Http::response([
            'items' => [[
                'id' => 'UCfirst',
                'snippet' => [
                    'title' => '1ch', 'description' => '', 'customUrl' => '@first',
                    'thumbnails' => [], 'publishedAt' => null,
                ],
                'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UUfirst']],
            ]],
        ]),
    ]);

    $this->actingAs($user)->post("/oshis/{$oshi->id}/channels", [
        'channel_url' => '@first',
    ]);

    $this->assertDatabaseHas('user_channels', ['oshi_id' => $oshi->id, 'is_main' => true]);
});

it('2件目の登録は is_main が false になる', function (): void {
    $user     = Profile::factory()->create();
    $oshi     = Oshi::factory()->create(['profile_id' => $user->id]);
    $channel1 = YoutubeChannel::factory()->create();
    UserChannel::factory()->main()->create([
        'profile_id'         => $user->id,
        'oshi_id'            => $oshi->id,
        'youtube_channel_id' => $channel1->id,
    ]);

    Http::fake([
        'googleapis.com/*' => Http::response([
            'items' => [[
                'id' => 'UCsecond',
                'snippet' => [
                    'title' => '2ch', 'description' => '', 'customUrl' => '@second',
                    'thumbnails' => [], 'publishedAt' => null,
                ],
                'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UUsecond']],
            ]],
        ]),
    ]);

    $this->actingAs($user)->post("/oshis/{$oshi->id}/channels", ['channel_url' => '@second']);

    $channels = UserChannel::where('oshi_id', $oshi->id)->get();
    expect($channels->where('is_main', true)->count())->toBe(1)
        ->and($channels->where('youtube_channel_id', $channel1->id)->first()->is_main)->toBeTrue();
});

it('メインチャンネルを変更できる（常に1つのみ）', function (): void {
    $user    = Profile::factory()->create();
    $oshi    = Oshi::factory()->create(['profile_id' => $user->id]);
    $ch1     = YoutubeChannel::factory()->create();
    $ch2     = YoutubeChannel::factory()->create();
    $uc1     = UserChannel::factory()->main()->create([
        'profile_id' => $user->id, 'oshi_id' => $oshi->id, 'youtube_channel_id' => $ch1->id,
    ]);
    $uc2     = UserChannel::factory()->create([
        'profile_id' => $user->id, 'oshi_id' => $oshi->id, 'youtube_channel_id' => $ch2->id, 'is_main' => false,
    ]);

    $this->actingAs($user)
        ->put("/oshis/{$oshi->id}/channels/{$uc2->id}/main")
        ->assertRedirect("/oshis/{$oshi->id}");

    $this->assertDatabaseHas('user_channels', ['id' => $uc1->id, 'is_main' => false]);
    $this->assertDatabaseHas('user_channels', ['id' => $uc2->id, 'is_main' => true]);
});

it('メインチャンネルは推し・ユーザーごとに常に1つ（部分ユニーク制約確認）', function (): void {
    $user = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $user->id]);
    $ch1  = YoutubeChannel::factory()->create();
    $ch2  = YoutubeChannel::factory()->create();
    UserChannel::factory()->main()->create([
        'profile_id' => $user->id, 'oshi_id' => $oshi->id, 'youtube_channel_id' => $ch1->id,
    ]);
    $uc2 = UserChannel::factory()->create([
        'profile_id' => $user->id, 'oshi_id' => $oshi->id, 'youtube_channel_id' => $ch2->id, 'is_main' => false,
    ]);

    // SetMainChannelAction 経由でメイン変更
    $this->actingAs($user)->put("/oshis/{$oshi->id}/channels/{$uc2->id}/main");

    $mainCount = UserChannel::where('profile_id', $user->id)
        ->where('oshi_id', $oshi->id)
        ->where('is_main', true)
        ->count();

    expect($mainCount)->toBe(1);
});

it('他ユーザーのチャンネルのメインは変更できない', function (): void {
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

<?php

declare(strict_types=1);

use App\Jobs\InitialSyncYoutubeChannelJob;
use App\Models\Oshi;
use App\Models\Profile;
use App\Models\YoutubeChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// チャンネル登録後に InitialSyncJob が dispatch されるが、
// 同期処理では実 API を叩いてしまうため Queue::fake() で停止させる。
beforeEach(function (): void {
    Queue::fake();
});

/** YouTube API の正常レスポンスを返す Http::fake セットアップ */
function fakeYouTubeSuccess(string $channelId = 'UCtest123'): void
{
    Http::fake([
        'googleapis.com/*' => Http::response([
            'items' => [[
                'id' => $channelId,
                'snippet' => [
                    'title'       => 'テストチャンネル',
                    'description' => '説明文',
                    'customUrl'   => '@testchannel',
                    'thumbnails'  => ['medium' => ['url' => 'https://example.com/thumb.jpg']],
                    'publishedAt' => '2020-01-01T00:00:00Z',
                ],
                'contentDetails' => [
                    'relatedPlaylists' => ['uploads' => 'UU' . substr($channelId, 2)],
                ],
            ]],
        ]),
    ]);
}

it('チャンネル URL で登録できる', function (): void {
    $user = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $user->id]);
    fakeYouTubeSuccess();

    $this->actingAs($user)
        ->post("/oshis/{$oshi->id}/channels", [
            'channel_url' => 'https://www.youtube.com/channel/UCtest123',
        ])
        ->assertRedirect("/oshis/{$oshi->id}");

    $this->assertDatabaseHas('youtube_channels', ['youtube_channel_id' => 'UCtest123']);
    $this->assertDatabaseHas('user_channels', [
        'profile_id' => $user->id,
        'oshi_id'    => $oshi->id,
        'is_main'    => true,
    ]);

    // 登録後に初回同期 Job が dispatch されること（FR-005）
    Queue::assertPushed(InitialSyncYoutubeChannelJob::class);
});

it('@handle で登録できる', function (): void {
    $user = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $user->id]);
    fakeYouTubeSuccess('UChandle123');

    $this->actingAs($user)
        ->post("/oshis/{$oshi->id}/channels", ['channel_url' => '@testhandle'])
        ->assertRedirect("/oshis/{$oshi->id}");

    $this->assertDatabaseHas('youtube_channels', ['youtube_channel_id' => 'UChandle123']);
});

it('対応形式外の URL は登録を拒否する', function (): void {
    $user = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $user->id]);
    Http::fake(); // API は呼ばれない

    $this->actingAs($user)
        ->post("/oshis/{$oshi->id}/channels", ['channel_url' => 'https://www.google.com/'])
        ->assertSessionHasErrors('channel_url');

    Http::assertNothingSent();
    $this->assertDatabaseCount('user_channels', 0);
});

it('存在しないチャンネルは登録を拒否する', function (): void {
    $user = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $user->id]);

    Http::fake([
        'googleapis.com/*' => Http::response(['items' => []]),
    ]);

    $this->actingAs($user)
        ->post("/oshis/{$oshi->id}/channels", ['channel_url' => '@nonexistent_abc'])
        ->assertSessionHasErrors('channel_url');

    $this->assertDatabaseCount('user_channels', 0);
});

it('同一ユーザーが同一チャンネルを重複登録できない（FR-007）', function (): void {
    $user    = Profile::factory()->create();
    $oshi    = Oshi::factory()->create(['profile_id' => $user->id]);
    $channel = YoutubeChannel::factory()->create(['youtube_channel_id' => 'UCtest123']);

    // すでに登録済みの user_channel を作成
    \App\Models\UserChannel::factory()->create([
        'profile_id'         => $user->id,
        'oshi_id'            => $oshi->id,
        'youtube_channel_id' => $channel->id,
        'is_main'            => true,
    ]);

    fakeYouTubeSuccess();

    $this->actingAs($user)
        ->post("/oshis/{$oshi->id}/channels", [
            'channel_url' => 'https://www.youtube.com/channel/UCtest123',
        ])
        ->assertSessionHasErrors('channel_url');

    $this->assertDatabaseCount('user_channels', 1);
});

it('別ユーザーが同一チャンネルを登録しても共有マスタは1件（FR-005 / SC-004）', function (): void {
    $user1 = Profile::factory()->create();
    $user2 = Profile::factory()->create();
    $oshi1 = Oshi::factory()->create(['profile_id' => $user1->id]);
    $oshi2 = Oshi::factory()->create(['profile_id' => $user2->id]);

    fakeYouTubeSuccess('UCshared123');

    // user1 が登録
    $this->actingAs($user1)
        ->post("/oshis/{$oshi1->id}/channels", [
            'channel_url' => 'https://www.youtube.com/channel/UCshared123',
        ]);

    // user2 が同じチャンネルを登録（Http::fake は再利用される）
    fakeYouTubeSuccess('UCshared123');
    $this->actingAs($user2)
        ->post("/oshis/{$oshi2->id}/channels", [
            'channel_url' => 'https://www.youtube.com/channel/UCshared123',
        ]);

    // 共有マスタは1件のみ
    $this->assertDatabaseCount('youtube_channels', 1);
    // 各ユーザーの登録は2件
    $this->assertDatabaseCount('user_channels', 2);
});

it('YouTube API エラー時でも画面がエラーで停止しない（FR-014）', function (): void {
    $user = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $user->id]);

    Http::fake([
        'googleapis.com/*' => Http::response([], 500),
    ]);

    $this->actingAs($user)
        ->post("/oshis/{$oshi->id}/channels", [
            'channel_url' => '@testchannel',
        ])
        ->assertSessionHasErrors('channel_url');

    $this->assertDatabaseCount('user_channels', 0);
});

it('他ユーザーの推しにチャンネルを登録できない（所有権保護）', function (): void {
    $user  = Profile::factory()->create();
    $other = Profile::factory()->create();
    $oshi  = Oshi::factory()->create(['profile_id' => $other->id]);

    $this->actingAs($user)
        ->post("/oshis/{$oshi->id}/channels", ['channel_url' => '@test'])
        ->assertForbidden();
});

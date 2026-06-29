<?php

declare(strict_types=1);

use App\Models\Oshi;
use App\Models\Profile;
use App\Models\TimestampMemo;
use App\Models\UserChannel;
use App\Models\UserWatchItem;
use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

// ==================== channel-sync レート制限テスト ====================

/**
 * fetchOlder（手動チャンネル同期）は 5回/分の channel-sync リミッターが適用される。
 * FR-004 / contracts/routes.md §変更ルート 対応。
 */
it('fetchOlder は 5 回まで成功し 6 回目は 429 を返す', function (): void {
    Queue::fake();
    $profile        = Profile::factory()->create();
    $oshi           = Oshi::factory()->create(['profile_id' => $profile->id]);
    $youtubeChannel = YoutubeChannel::factory()->create(['oldest_page_token' => 'token_abc']);
    $userChannel    = UserChannel::factory()->create([
        'profile_id'         => $profile->id,
        'oshi_id'            => $oshi->id,
        'youtube_channel_id' => $youtubeChannel->id,
    ]);

    // レート制限をリセット
    RateLimiter::clear('channel-sync|' . $profile->id);

    $url = route('oshis.channels.fetchOlder', [$oshi, $userChannel]);

    // 1〜5 回目は成功（302 リダイレクト）
    foreach (range(1, 5) as $i) {
        $this->actingAs($profile)
            ->post($url)
            ->assertRedirect();
    }

    // 6 回目は 429
    $this->actingAs($profile)
        ->post($url)
        ->assertStatus(429);
});

// ==================== memo-mutations レート制限テスト ====================

/**
 * タイムスタンプメモ作成は 60回/分の memo-mutations リミッターが適用される。
 * FR-003 対応（書き込み操作の濫用防止）。
 */
it('タイムスタンプメモ作成は 60 回まで成功し 61 回目は 429 を返す', function (): void {
    $profile   = Profile::factory()->create();
    $video     = YoutubeVideo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
    ]);

    RateLimiter::clear('memo-mutations|' . $profile->id);

    $url  = route('archives.memos.store', $watchItem);
    $body = ['seconds' => 10, 'body' => 'テストメモ'];

    // 1〜60 回目は成功
    foreach (range(1, 60) as $i) {
        $this->actingAs($profile)
            ->postJson($url, $body)
            ->assertSuccessful();

        // DB の重複を避けるため秒数をずらす
        $body['seconds'] = $i;
    }

    // 61 回目は 429
    $body['seconds'] = 999;
    $this->actingAs($profile)
        ->postJson($url, $body)
        ->assertStatus(429);
});

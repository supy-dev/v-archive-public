<?php

declare(strict_types=1);

use App\Enums\WatchStatus;
use App\Models\Profile;
use App\Models\UserWatchItem;
use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ==================== セットアップヘルパー ====================

/**
 * 配信詳細ページのテスト用フィクスチャを作成する。
 *
 * @return array{profile: Profile, watchItem: UserWatchItem}
 */
function makeArchiveShowFixture(array $videoAttrs = [], array $watchItemAttrs = []): array
{
    $profile = Profile::factory()->create();
    $channel = YoutubeChannel::factory()->create();
    $video   = YoutubeVideo::factory()->create(array_merge(
        ['youtube_channel_id' => $channel->id, 'is_available' => true, 'duration_seconds' => 600],
        $videoAttrs,
    ));
    $watchItem = UserWatchItem::factory()->create(array_merge(
        ['profile_id' => $profile->id, 'youtube_video_id' => $video->id, 'status' => WatchStatus::WantToWatch->value],
        $watchItemAttrs,
    ));

    return compact('profile', 'watchItem');
}

// ==================== 未認証テスト ====================

it('未認証では /login へリダイレクトされる', function (): void {
    $watchItem = UserWatchItem::factory()->create();

    $this->get("/archives/{$watchItem->id}")
        ->assertRedirect('/login');
});

// ==================== 正常系テスト ====================

it('認証済みユーザーが自分の watch_item 詳細ページを 200 で表示できる', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeArchiveShowFixture();

    $this->actingAs($profile)
        ->get("/archives/{$watchItem->id}")
        ->assertOk()
        ->assertViewIs('archives.show')
        ->assertViewHas('watchItem');
});

it('ページに動画タイトルが表示される', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeArchiveShowFixture();

    $title = $watchItem->youtubeVideo->title;

    $this->actingAs($profile)
        ->get("/archives/{$watchItem->id}")
        ->assertOk()
        ->assertSeeText($title);
});

it('配信詳細に再生導線と視聴ステータス変更UIが表示される', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeArchiveShowFixture(
        ['youtube_video_id' => 'detailVideo123'],
        ['status' => WatchStatus::Watching->value, 'last_position_seconds' => 125],
    );

    $this->actingAs($profile)
        ->get("/archives/{$watchItem->id}")
        ->assertOk()
        ->assertSeeText('続きから見る')
        ->assertSeeText('YouTubeで開く')
        ->assertSee('https://www.youtube.com/watch?v=detailVideo123&amp;t=125s', false)
        ->assertSee('name="status"', false)
        ->assertSeeText('視聴中');
});

it('タイムスタンプメモのクイック入力と操作メニューを表示する', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeArchiveShowFixture();

    $this->actingAs($profile)
        ->get("/archives/{$watchItem->id}")
        ->assertOk()
        ->assertSee('placeholder="この場面についてメモ…"', false)
        ->assertSee('詳細設定', false)
        ->assertSee('aria-label="その他の操作"', false)
        ->assertSee('aria-label="メインナビゲーション"', false);
});

it('YouTube Player を Alpine の init と x-init で二重初期化しない', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeArchiveShowFixture();

    $this->actingAs($profile)
        ->get("/archives/{$watchItem->id}")
        ->assertOk()
        ->assertDontSee('x-init="init()"', false);
});

// ==================== 認可テスト ====================

it('他ユーザーの watch_item は 403 を返す（FR-001 / SC-004）', function (): void {
    $owner     = Profile::factory()->create();
    $intruder  = Profile::factory()->create();
    $watchItem = UserWatchItem::factory()->create(['profile_id' => $owner->id]);

    $this->actingAs($intruder)
        ->get("/archives/{$watchItem->id}")
        ->assertForbidden();
});

it('存在しない watch_item は 404 を返す', function (): void {
    $profile = Profile::factory()->create();

    $this->actingAs($profile)
        ->get('/archives/00000000-0000-0000-0000-000000000000')
        ->assertNotFound();
});

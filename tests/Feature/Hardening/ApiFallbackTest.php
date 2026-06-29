<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\TimestampMemo;
use App\Models\UserWatchItem;
use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// ==================== YouTube API 障害フォールバックテスト ====================

/**
 * YouTube API が 503 を返す状態でも保存済みデータの閲覧が維持されることを確認する。
 * FR-008・FR-009 / SC-003 対応。
 */

beforeEach(function (): void {
    // YouTube API への全リクエストを 503 エラーでフェイク
    Http::fake([
        '*.googleapis.com/*' => Http::response(null, 503),
    ]);
});

it('YouTube API 障害時も新着アーカイブ一覧を表示できる（FR-008）', function (): void {
    $profile = Profile::factory()->create();
    $video   = YoutubeVideo::factory()->create();
    UserWatchItem::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
    ]);

    $this->actingAs($profile)
        ->get(route('archive.index'))
        ->assertOk();
});

it('YouTube API 障害時もタイムスタンプメモ保管庫を表示できる（FR-008）', function (): void {
    $profile = Profile::factory()->create();
    $video   = YoutubeVideo::factory()->create();
    UserWatchItem::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
    ]);
    TimestampMemo::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
    ]);

    $this->actingAs($profile)
        ->get(route('memos.index'))
        ->assertOk();
});

it('YouTube API 障害時も神回・お気に入りページを表示できる（FR-008）', function (): void {
    $profile   = Profile::factory()->create();
    $video     = YoutubeVideo::factory()->create();
    UserWatchItem::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
        'is_favorite'      => true,
    ]);

    $this->actingAs($profile)
        ->get(route('favorites.index'))
        ->assertOk();
});

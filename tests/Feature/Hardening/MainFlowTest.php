<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\UserWatchItem;
use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ==================== 主要フロー横断テスト ====================

/**
 * 認証→一覧→詳細→メモ作成→神回トグル→お気に入り確認の一連の流れを検証する。
 * US1 の独立テスト基準（FR-014 / SC-001）に対応。
 */

it('主要フロー: アーカイブ一覧を閲覧できる', function (): void {
    $profile = Profile::factory()->create();

    $this->actingAs($profile)
        ->get(route('archive.index'))
        ->assertOk();
});

it('主要フロー: 配信詳細ページを閲覧できる', function (): void {
    $profile   = Profile::factory()->create();
    $channel   = YoutubeChannel::factory()->create();
    $video     = YoutubeVideo::factory()->create(['youtube_channel_id' => $channel->id]);
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
    ]);

    $this->actingAs($profile)
        ->get(route('archives.show', $watchItem))
        ->assertOk();
});

it('主要フロー: タイムスタンプメモを作成できる', function (): void {
    $profile   = Profile::factory()->create();
    $video     = YoutubeVideo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
    ]);

    $this->actingAs($profile)
        ->postJson(route('archives.memos.store', $watchItem), [
            'seconds' => 120,
            'body'    => '好きなシーン',
        ])
        ->assertCreated();
});

it('主要フロー: 神回フラグをトグルできる', function (): void {
    $profile   = Profile::factory()->create();
    $video     = YoutubeVideo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
        'is_favorite'      => false,
    ]);

    $this->actingAs($profile)
        ->patchJson(route('archives.watch-item.favorite.update', $watchItem))
        ->assertOk()
        ->assertJson(['is_favorite' => true]);
});

it('主要フロー: 神回・お気に入りページを閲覧できる', function (): void {
    $profile = Profile::factory()->create();

    $this->actingAs($profile)
        ->get(route('favorites.index'))
        ->assertOk();
});

it('主要フロー: タイムスタンプメモ保管庫を閲覧できる', function (): void {
    $profile = Profile::factory()->create();

    $this->actingAs($profile)
        ->get(route('memos.index'))
        ->assertOk();
});

// ==================== 所有権（Policy）横断確認 ====================

it('所有権: 他ユーザーの配信詳細へのアクセスは 403 を返す', function (): void {
    $owner     = Profile::factory()->create();
    $attacker  = Profile::factory()->create();
    $video     = YoutubeVideo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $owner->id,
        'youtube_video_id' => $video->id,
    ]);

    $this->actingAs($attacker)
        ->get(route('archives.show', $watchItem))
        ->assertForbidden();
});

it('所有権: 他ユーザーのメモ作成先に投稿しようとすると 403 を返す', function (): void {
    $owner     = Profile::factory()->create();
    $attacker  = Profile::factory()->create();
    $video     = YoutubeVideo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $owner->id,
        'youtube_video_id' => $video->id,
    ]);

    $this->actingAs($attacker)
        ->postJson(route('archives.memos.store', $watchItem), [
            'seconds' => 60,
            'body'    => '不正なメモ',
        ])
        ->assertForbidden();
});

it('所有権: 他ユーザーの神回フラグをトグルしようとすると 403 を返す', function (): void {
    $owner     = Profile::factory()->create();
    $attacker  = Profile::factory()->create();
    $video     = YoutubeVideo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $owner->id,
        'youtube_video_id' => $video->id,
    ]);

    $this->actingAs($attacker)
        ->patchJson(route('archives.watch-item.favorite.update', $watchItem))
        ->assertForbidden();
});

it('所有権: 未認証ユーザーがアーカイブ一覧へアクセスするとログイン画面へリダイレクトされる', function (): void {
    $this->get(route('archive.index'))
        ->assertRedirect(route('login'));
});

<?php

declare(strict_types=1);

use App\Enums\WatchStatus;
use App\Models\Oshi;
use App\Models\Profile;
use App\Models\UserChannel;
use App\Models\UserWatchItem;
use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ==================== セットアップヘルパー ====================

/**
 * ユーザー・チャンネル・動画を一括作成する。
 *
 * @return array{profile: Profile, video: YoutubeVideo}
 */
function makeWatchItemFixture(): array
{
    $profile = Profile::factory()->create();
    $oshi    = Oshi::factory()->create(['profile_id' => $profile->id]);
    $channel = YoutubeChannel::factory()->create();

    UserChannel::factory()->create([
        'profile_id'         => $profile->id,
        'oshi_id'            => $oshi->id,
        'youtube_channel_id' => $channel->id,
    ]);

    $video = YoutubeVideo::factory()->create([
        'youtube_channel_id' => $channel->id,
        'is_available'       => true,
    ]);

    return compact('profile', 'video');
}

// ==================== 未認証テスト ====================

it('未認証では /login へリダイレクトされる', function (): void {
    $video = YoutubeVideo::factory()->create();
    $this->post("/archive/{$video->id}/watch-item", ['status' => 'want_to_watch'])
        ->assertRedirect('/login');
});

// ==================== 正常系テスト ====================

it('「見るリストに追加」で want_to_watch の user_watch_items が作成される（US1 AC-2）', function (): void {
    ['profile' => $profile, 'video' => $video] = makeWatchItemFixture();

    $this->actingAs($profile)
        ->post("/archive/{$video->id}/watch-item", ['status' => 'want_to_watch'])
        ->assertRedirect();

    $this->assertDatabaseHas('user_watch_items', [
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
        'status'           => WatchStatus::WantToWatch->value,
    ]);
});

it('「見送る」で skipped の user_watch_items が作成される（US1 AC-3）', function (): void {
    ['profile' => $profile, 'video' => $video] = makeWatchItemFixture();

    $this->actingAs($profile)
        ->post("/archive/{$video->id}/watch-item", ['status' => 'skipped'])
        ->assertRedirect();

    $this->assertDatabaseHas('user_watch_items', [
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
        'status'           => WatchStatus::Skipped->value,
    ]);
});

it('同一動画に 2 回送信しても 1 件しか作成されない（FR-007 upsert 冪等性）', function (): void {
    ['profile' => $profile, 'video' => $video] = makeWatchItemFixture();

    $this->actingAs($profile)->post("/archive/{$video->id}/watch-item", ['status' => 'want_to_watch']);
    $this->actingAs($profile)->post("/archive/{$video->id}/watch-item", ['status' => 'skipped']);

    $this->assertDatabaseCount('user_watch_items', 1);
    $this->assertDatabaseHas('user_watch_items', [
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
        'status'           => WatchStatus::Skipped->value,  // 最後のステータスに更新
    ]);
});

// ==================== 認可テスト ====================

it('自分が登録していないチャンネルの動画は追加できない（FR-009）', function (): void {
    $user  = Profile::factory()->create();
    // 動画のチャンネルは user_channels に登録しない
    $video = YoutubeVideo::factory()->create();

    $this->actingAs($user)
        ->post("/archive/{$video->id}/watch-item", ['status' => 'want_to_watch'])
        ->assertForbidden();

    $this->assertDatabaseEmpty('user_watch_items');
});

it('status バリデーションに失敗する', function (): void {
    ['profile' => $profile, 'video' => $video] = makeWatchItemFixture();

    // watching は手動設定不可（FR-005）
    $this->actingAs($profile)
        ->post("/archive/{$video->id}/watch-item", ['status' => 'watching'])
        ->assertSessionHasErrors('status');

    $this->assertDatabaseEmpty('user_watch_items');
});

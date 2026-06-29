<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\UserWatchItem;
use App\Models\VideoNote;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 動画ノートテスト用フィクスチャを作成する。
 *
 * @return array{profile: Profile, video: YoutubeVideo, watchItem: UserWatchItem}
 */
function makeNoteFixture(): array
{
    $profile   = Profile::factory()->create();
    $video     = YoutubeVideo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
    ]);

    return compact('profile', 'video', 'watchItem');
}

// ==================== 保存（PUT）テスト ====================

it('動画ノートを新規保存すると 200 を返す（FR-005）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeNoteFixture();

    $this->actingAs($profile)
        ->putJson("/archives/{$watchItem->id}/note", ['body' => '全体感想テキスト'])
        ->assertOk()
        ->assertJsonPath('status', 'saved');

    $this->assertDatabaseHas('video_notes', [
        'profile_id'       => $profile->id,
        'youtube_video_id' => $watchItem->youtube_video_id,
        'body'             => '全体感想テキスト',
    ]);
});

it('既存ノートを上書き保存すると内容が更新される（FR-005）', function (): void {
    ['profile' => $profile, 'video' => $video, 'watchItem' => $watchItem] = makeNoteFixture();

    VideoNote::create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
        'body'             => '元の感想',
    ]);

    $this->actingAs($profile)
        ->putJson("/archives/{$watchItem->id}/note", ['body' => '更新後の感想'])
        ->assertOk();

    $this->assertDatabaseHas('video_notes', ['body' => '更新後の感想']);
    $this->assertDatabaseMissing('video_notes', ['body' => '元の感想']);
    // 1件のみ存在することを確認
    $this->assertDatabaseCount('video_notes', 1);
});

it('body が空の場合は 422 を返す（FR-006）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeNoteFixture();

    $this->actingAs($profile)
        ->putJson("/archives/{$watchItem->id}/note", ['body' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
});

it('body が 5001 文字以上の場合は 422 を返す（FR-015）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeNoteFixture();

    $this->actingAs($profile)
        ->putJson("/archives/{$watchItem->id}/note", ['body' => str_repeat('あ', 5001)])
        ->assertUnprocessable();
});

// ==================== 削除（DELETE）テスト ====================

it('動画ノートを削除すると 204 を返す', function (): void {
    ['profile' => $profile, 'video' => $video, 'watchItem' => $watchItem] = makeNoteFixture();

    VideoNote::create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
        'body'             => '感想',
    ]);

    $this->actingAs($profile)
        ->deleteJson("/archives/{$watchItem->id}/note")
        ->assertNoContent();

    $this->assertDatabaseEmpty('video_notes');
});

// ==================== 所有権テスト ====================

it('未認証で PUT すると 401 を返す', function (): void {
    $watchItem = UserWatchItem::factory()->create();

    $this->putJson("/archives/{$watchItem->id}/note", ['body' => 'テスト'])
        ->assertUnauthorized();
});

it('他ユーザーの watchItem へのノート保存は 403 を返す（SC-005）', function (): void {
    $owner    = Profile::factory()->create();
    $intruder = Profile::factory()->create();
    $video    = YoutubeVideo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $owner->id,
        'youtube_video_id' => $video->id,
    ]);

    $this->actingAs($intruder)
        ->putJson("/archives/{$watchItem->id}/note", ['body' => '不正保存'])
        ->assertForbidden();

    $this->assertDatabaseEmpty('video_notes');
});

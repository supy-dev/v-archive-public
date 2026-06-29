<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\Tag;
use App\Models\TimestampMemo;
use App\Models\UserWatchItem;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ==================== フィクスチャヘルパー ====================

/**
 * タイムスタンプメモテスト用フィクスチャを作成する。
 *
 * @return array{profile: Profile, video: YoutubeVideo, watchItem: UserWatchItem}
 */
function makeMemoFixture(array $watchItemAttrs = []): array
{
    $profile   = Profile::factory()->create();
    $video     = YoutubeVideo::factory()->create(['is_available' => true]);
    $watchItem = UserWatchItem::factory()->create(array_merge(
        ['profile_id' => $profile->id, 'youtube_video_id' => $video->id],
        $watchItemAttrs,
    ));

    return compact('profile', 'video', 'watchItem');
}

// ==================== 新規作成（POST）テスト ====================

it('メモを新規作成すると 201 と JSON を返す（FR-001）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeMemoFixture();

    $this->actingAs($profile)
        ->postJson("/archives/{$watchItem->id}/memos", [
            'seconds' => 185,
            'body'    => 'このシーンが好き',
        ])
        ->assertCreated()
        ->assertJsonPath('memo.seconds', 185)
        ->assertJsonPath('memo.body', 'このシーンが好き')
        ->assertJsonPath('memo.is_favorite', false)
        ->assertJsonStructure(['memo' => ['id', 'seconds', 'seconds_label', 'body', 'is_favorite', 'tags', 'youtube_url']]);

    $this->assertDatabaseHas('timestamp_memos', [
        'profile_id'       => $profile->id,
        'youtube_video_id' => $watchItem->youtube_video_id,
        'seconds'          => 185,
        'body'             => 'このシーンが好き',
    ]);
});

it('seconds=0 のメモを作成できる（edge case）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeMemoFixture();

    $this->actingAs($profile)
        ->postJson("/archives/{$watchItem->id}/memos", [
            'seconds' => 0,
            'body'    => '冒頭のメモ',
        ])
        ->assertCreated()
        ->assertJsonPath('memo.seconds', 0);
});

it('同一秒数に複数のメモを作成できる', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeMemoFixture();

    $this->actingAs($profile)
        ->postJson("/archives/{$watchItem->id}/memos", ['seconds' => 60, 'body' => 'メモ1'])
        ->assertCreated();

    $this->actingAs($profile)
        ->postJson("/archives/{$watchItem->id}/memos", ['seconds' => 60, 'body' => 'メモ2'])
        ->assertCreated();

    $this->assertDatabaseCount('timestamp_memos', 2);
});

// ==================== バリデーションテスト ====================

it('body が空の場合は 422 を返す（FR-014）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeMemoFixture();

    $this->actingAs($profile)
        ->postJson("/archives/{$watchItem->id}/memos", [
            'seconds' => 60,
            'body'    => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
});

it('body が 1001 文字以上の場合は 422 を返す（FR-014）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeMemoFixture();

    $this->actingAs($profile)
        ->postJson("/archives/{$watchItem->id}/memos", [
            'seconds' => 60,
            'body'    => str_repeat('あ', 1001),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
});

it('seconds が負の場合は 422 を返す', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeMemoFixture();

    $this->actingAs($profile)
        ->postJson("/archives/{$watchItem->id}/memos", [
            'seconds' => -1,
            'body'    => 'テスト',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['seconds']);
});

it('seconds が欠損の場合は 422 を返す', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeMemoFixture();

    $this->actingAs($profile)
        ->postJson("/archives/{$watchItem->id}/memos", [
            'body' => 'テスト',
        ])
        ->assertUnprocessable();
});

// ==================== 更新（PATCH）テスト ====================

it('メモを更新すると 200 と更新後 JSON を返す（FR-004）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeMemoFixture();

    $memo = TimestampMemo::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $watchItem->youtube_video_id,
        'seconds'          => 100,
        'body'             => '元のメモ',
    ]);

    $this->actingAs($profile)
        ->patchJson("/archives/{$watchItem->id}/memos/{$memo->id}", [
            'seconds' => 200,
            'body'    => '更新後のメモ',
        ])
        ->assertOk()
        ->assertJsonPath('memo.seconds', 200)
        ->assertJsonPath('memo.body', '更新後のメモ');

    $this->assertDatabaseHas('timestamp_memos', [
        'id'      => $memo->id,
        'seconds' => 200,
        'body'    => '更新後のメモ',
    ]);
});

// ==================== 削除（DELETE）テスト ====================

it('メモを削除すると 204 を返す（FR-004）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeMemoFixture();

    $memo = TimestampMemo::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $watchItem->youtube_video_id,
    ]);

    $this->actingAs($profile)
        ->deleteJson("/archives/{$watchItem->id}/memos/{$memo->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('timestamp_memos', ['id' => $memo->id]);
});

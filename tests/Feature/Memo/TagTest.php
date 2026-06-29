<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\Tag;
use App\Models\TimestampMemo;
use App\Models\UserWatchItem;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * タグ付きメモテスト用フィクスチャ。
 *
 * @return array{profile: Profile, video: YoutubeVideo, watchItem: UserWatchItem}
 */
function makeTagFixture(): array
{
    $profile   = Profile::factory()->create();
    $video     = YoutubeVideo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
    ]);

    return compact('profile', 'video', 'watchItem');
}

// ==================== システムタグ付与テスト ====================

it('システムタグを指定してメモを保存するとタグが紐付く（FR-007 / FR-008）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeTagFixture();

    $systemTag = Tag::factory()->system()->create(['name' => '笑った', 'slug' => 'waratta']);

    $this->actingAs($profile)
        ->postJson("/archives/{$watchItem->id}/memos", [
            'seconds' => 60,
            'body'    => 'テスト',
            'tag_ids' => [$systemTag->id],
        ])
        ->assertCreated()
        ->assertJsonPath('memo.tags.0.name', '笑った');

    $this->assertDatabaseHas('timestamp_memo_tags', ['tag_id' => $systemTag->id]);
});

// ==================== ユーザー固有タグインライン作成テスト ====================

it('new_tag_names で指定した名前のユーザー固有タグが作成されメモに紐付く（FR-009）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeTagFixture();

    $res = $this->actingAs($profile)
        ->postJson("/archives/{$watchItem->id}/memos", [
            'seconds'       => 60,
            'body'          => 'テスト',
            'new_tag_names' => ['推しかわいい'],
        ])
        ->assertCreated();

    $memo = TimestampMemo::first();
    $this->assertCount(1, $memo->tags);
    $this->assertEquals('推しかわいい', $memo->tags->first()->name);
    $this->assertEquals($profile->id, $memo->tags->first()->profile_id);
    $this->assertFalse($memo->tags->first()->is_system);
});

it('同名のユーザー固有タグを重複して作成しない（firstOrCreate の冪等性）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeTagFixture();

    // 1回目：タグが新規作成される
    $this->actingAs($profile)
        ->postJson("/archives/{$watchItem->id}/memos", [
            'seconds'       => 60,
            'body'          => 'メモ1',
            'new_tag_names' => ['推しかわいい'],
        ])
        ->assertCreated();

    // 2回目：同名タグはインポートされ新規作成されない
    $this->actingAs($profile)
        ->postJson("/archives/{$watchItem->id}/memos", [
            'seconds'       => 120,
            'body'          => 'メモ2',
            'new_tag_names' => ['推しかわいい'],
        ])
        ->assertCreated();

    // タグは1件のみ（重複なし）
    $this->assertDatabaseCount('tags', 1);
    $this->assertDatabaseCount('timestamp_memos', 2);
});

// ==================== タグ更新テスト ====================

it('メモ更新時にタグを差分 sync できる（FR-007）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makeTagFixture();

    $tagA = Tag::factory()->system()->create(['slug' => 'tag-a']);
    $tagB = Tag::factory()->system()->create(['slug' => 'tag-b']);

    $memo = TimestampMemo::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $watchItem->youtube_video_id,
    ]);
    $memo->tags()->attach($tagA->id);

    $this->actingAs($profile)
        ->patchJson("/archives/{$watchItem->id}/memos/{$memo->id}", [
            'seconds' => $memo->seconds,
            'body'    => $memo->body,
            'tag_ids' => [$tagB->id],
        ])
        ->assertOk()
        ->assertJsonPath('memo.tags.0.id', $tagB->id);

    $this->assertDatabaseMissing('timestamp_memo_tags', ['tag_id' => $tagA->id]);
    $this->assertDatabaseHas('timestamp_memo_tags', ['tag_id' => $tagB->id]);
});

// ==================== タグスコープ分離テスト ====================

it('他ユーザーの固有タグは別ユーザーのタグ一覧に含まれない（FR-008）', function (): void {
    $user1 = Profile::factory()->create();
    $user2 = Profile::factory()->create();

    // user1 の固有タグを作成
    Tag::factory()->forUser($user1->id)->create(['name' => 'ユーザー1タグ']);

    // user2 のタグ一覧には含まれないことを確認
    $user2Tags = Tag::forUser($user2->id)->get();
    $this->assertCount(0, $user2Tags);
});

<?php

declare(strict_types=1);

use App\Models\Oshi;
use App\Models\Profile;
use App\Models\Tag;
use App\Models\TimestampMemo;
use App\Models\UserWatchItem;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('神回・お気に入り一覧が表示される（FR-011）', function (): void {
    $profile   = Profile::factory()->create();
    $video     = YoutubeVideo::factory()->create();
    UserWatchItem::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
    ]);
    TimestampMemo::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
        'is_favorite'      => true,
        'body'             => 'お気に入りメモ',
    ]);

    $this->actingAs($profile)
        ->get('/favorites?tab=memos')
        ->assertOk()
        ->assertSee('お気に入りメモ');
});

it('お気に入りでないメモは一覧に表示されない', function (): void {
    $profile   = Profile::factory()->create();
    $video     = YoutubeVideo::factory()->create();
    UserWatchItem::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
    ]);
    TimestampMemo::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
        'is_favorite'      => false,
        'body'             => '通常メモ',
    ]);

    $this->actingAs($profile)
        ->get('/favorites?tab=memos')
        ->assertOk()
        ->assertDontSee('通常メモ');
});

it('未認証で /favorites にアクセスするとリダイレクトされる', function (): void {
    $this->get('/favorites')
        ->assertRedirect('/login');
});

it('タグでフィルタリングするとタグ付きメモのみ表示される（FR-012）', function (): void {
    $profile = Profile::factory()->create();
    $video   = YoutubeVideo::factory()->create();
    UserWatchItem::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
    ]);
    $tag = Tag::factory()->system()->create(['name' => '笑った', 'slug' => 'waratta']);

    $memoWithTag = TimestampMemo::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
        'is_favorite'      => true,
        'body'             => 'タグ付きメモ',
    ]);
    $memoWithTag->tags()->attach($tag->id);

    $memoWithoutTag = TimestampMemo::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
        'is_favorite'      => true,
        'body'             => 'タグなしメモ',
    ]);

    $this->actingAs($profile)
        ->get('/favorites?tab=memos&tag_id=' . $tag->id)
        ->assertOk()
        ->assertSee('タグ付きメモ')
        ->assertDontSee('タグなしメモ');
});

it('他ユーザーのお気に入りメモは表示されない', function (): void {
    $user1   = Profile::factory()->create();
    $user2   = Profile::factory()->create();
    $video   = YoutubeVideo::factory()->create();

    TimestampMemo::factory()->create([
        'profile_id'       => $user1->id,
        'youtube_video_id' => $video->id,
        'is_favorite'      => true,
        'body'             => 'user1のメモ',
    ]);

    $this->actingAs($user2)
        ->get('/favorites?tab=memos')
        ->assertOk()
        ->assertDontSee('user1のメモ');
});

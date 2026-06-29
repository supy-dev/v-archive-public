<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\UserWatchItem;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('未認証では /login へリダイレクトされる', function (): void {
    $item = UserWatchItem::factory()->create();
    $this->delete("/watchlist/{$item->id}")->assertRedirect('/login');
});

it('自分の user_watch_items を削除できる（FR-015）', function (): void {
    $user = Profile::factory()->create();
    $item = UserWatchItem::factory()->create(['profile_id' => $user->id]);

    $this->actingAs($user)
        ->delete("/watchlist/{$item->id}")
        ->assertRedirect(route('watchlist.index'));

    $this->assertDatabaseMissing('user_watch_items', ['id' => $item->id]);
});

it('削除後に動画は未整理（user_watch_items なし）状態に戻る（FR-015）', function (): void {
    $user  = Profile::factory()->create();
    $item  = UserWatchItem::factory()->create(['profile_id' => $user->id]);
    $videoId = $item->youtube_video_id;

    $this->actingAs($user)->delete("/watchlist/{$item->id}");

    // YoutubeVideo 自体は削除されていない
    $this->assertDatabaseHas('youtube_videos', ['id' => $videoId]);
    // user_watch_items は削除されている
    $this->assertDatabaseMissing('user_watch_items', ['youtube_video_id' => $videoId, 'profile_id' => $user->id]);
});

it('他ユーザーの user_watch_items は削除できない（FR-009）', function (): void {
    $user  = Profile::factory()->create();
    $other = Profile::factory()->create();
    $item  = UserWatchItem::factory()->create(['profile_id' => $other->id]);

    $this->actingAs($user)
        ->delete("/watchlist/{$item->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('user_watch_items', ['id' => $item->id]);
});

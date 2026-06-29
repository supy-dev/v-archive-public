<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\TimestampMemo;
use App\Models\UserWatchItem;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('お気に入りをトグルして is_favorite が反転する（FR-010）', function (): void {
    $profile   = Profile::factory()->create();
    $video     = YoutubeVideo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
    ]);
    $memo = TimestampMemo::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
        'is_favorite'      => false,
    ]);

    // 登録
    $this->actingAs($profile)
        ->patchJson("/archives/{$watchItem->id}/memos/{$memo->id}/favorite")
        ->assertOk()
        ->assertJsonPath('is_favorite', true);

    $this->assertDatabaseHas('timestamp_memos', ['id' => $memo->id, 'is_favorite' => true]);

    // 解除（再度トグル）
    $this->actingAs($profile)
        ->patchJson("/archives/{$watchItem->id}/memos/{$memo->id}/favorite")
        ->assertOk()
        ->assertJsonPath('is_favorite', false);

    $this->assertDatabaseHas('timestamp_memos', ['id' => $memo->id, 'is_favorite' => false]);
});

it('未認証でお気に入りトグルすると 401 を返す', function (): void {
    $memo      = TimestampMemo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $memo->profile_id,
        'youtube_video_id' => $memo->youtube_video_id,
    ]);

    $this->patchJson("/archives/{$watchItem->id}/memos/{$memo->id}/favorite")
        ->assertUnauthorized();
});

it('他ユーザーのメモのお気に入りトグルは 403 を返す（SC-005）', function (): void {
    $owner    = Profile::factory()->create();
    $intruder = Profile::factory()->create();
    $video    = YoutubeVideo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $owner->id,
        'youtube_video_id' => $video->id,
    ]);
    $memo = TimestampMemo::factory()->create([
        'profile_id'       => $owner->id,
        'youtube_video_id' => $video->id,
        'is_favorite'      => false,
    ]);

    $this->actingAs($intruder)
        ->patchJson("/archives/{$watchItem->id}/memos/{$memo->id}/favorite")
        ->assertForbidden();

    $this->assertDatabaseHas('timestamp_memos', ['id' => $memo->id, 'is_favorite' => false]);
});

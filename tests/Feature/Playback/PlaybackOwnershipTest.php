<?php

declare(strict_types=1);

use App\Enums\WatchStatus;
use App\Models\Profile;
use App\Models\UserWatchItem;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ==================== 未認証テスト ====================

it('未認証で PATCH すると 401 を返す', function (): void {
    $watchItem = UserWatchItem::factory()->create();

    $this->patchJson("/watch-items/{$watchItem->id}/position", [
        'last_position_seconds' => 100,
        'is_ended'              => false,
    ])->assertUnauthorized();
});

// ==================== 所有権テスト ====================

it('他ユーザーの watch_item への PATCH は 403 を返す（SC-004）', function (): void {
    $owner    = Profile::factory()->create();
    $intruder = Profile::factory()->create();
    $video    = YoutubeVideo::factory()->create(['duration_seconds' => 600]);
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $owner->id,
        'youtube_video_id' => $video->id,
        'status'           => WatchStatus::Watching->value,
    ]);

    $this->actingAs($intruder)
        ->patchJson("/watch-items/{$watchItem->id}/position", [
            'last_position_seconds' => 100,
            'is_ended'              => false,
        ])
        ->assertForbidden();

    // 他ユーザーのデータは変更されていない
    $this->assertDatabaseHas('user_watch_items', [
        'id'                    => $watchItem->id,
        'last_position_seconds' => null,
    ]);
});

it('自分の watch_item への PATCH は 204 を返す', function (): void {
    $profile   = Profile::factory()->create();
    $video     = YoutubeVideo::factory()->create(['duration_seconds' => 600]);
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
    ]);

    $this->actingAs($profile)
        ->patchJson("/watch-items/{$watchItem->id}/position", [
            'last_position_seconds' => 120,
            'is_ended'              => false,
        ])
        ->assertNoContent();
});

it('存在しない watch_item への PATCH は 404 を返す', function (): void {
    $profile = Profile::factory()->create();

    $this->actingAs($profile)
        ->patchJson('/watch-items/00000000-0000-0000-0000-000000000000/position', [
            'last_position_seconds' => 100,
            'is_ended'              => false,
        ])
        ->assertNotFound();
});

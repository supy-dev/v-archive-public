<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\UserWatchItem;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('神回フラグを false から true へトグルする', function (): void {
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

    expect($watchItem->fresh()->is_favorite)->toBeTrue();
});

it('神回フラグを true から false へトグルする', function (): void {
    $profile   = Profile::factory()->create();
    $video     = YoutubeVideo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
        'is_favorite'      => true,
    ]);

    $this->actingAs($profile)
        ->patchJson(route('archives.watch-item.favorite.update', $watchItem))
        ->assertOk()
        ->assertJson(['is_favorite' => false]);

    expect($watchItem->fresh()->is_favorite)->toBeFalse();
});

it('他ユーザーの watchItem への PATCH は 403', function (): void {
    $owner    = Profile::factory()->create();
    $attacker = Profile::factory()->create();
    $video    = YoutubeVideo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $owner->id,
        'youtube_video_id' => $video->id,
    ]);

    $this->actingAs($attacker)
        ->patchJson(route('archives.watch-item.favorite.update', $watchItem))
        ->assertForbidden();
});

it('未認証では 401 が返る', function (): void {
    $watchItem = UserWatchItem::factory()->create();

    $this->patchJson(route('archives.watch-item.favorite.update', $watchItem))
        ->assertUnauthorized();
});

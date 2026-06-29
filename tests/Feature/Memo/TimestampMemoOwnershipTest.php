<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\TimestampMemo;
use App\Models\UserWatchItem;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ==================== 未認証テスト ====================

it('未認証で POST すると 401 を返す', function (): void {
    $watchItem = UserWatchItem::factory()->create();

    $this->postJson("/archives/{$watchItem->id}/memos", [
        'seconds' => 60,
        'body'    => 'テスト',
    ])->assertUnauthorized();
});

it('未認証で PATCH すると 401 を返す', function (): void {
    $memo      = TimestampMemo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $memo->profile_id,
        'youtube_video_id' => $memo->youtube_video_id,
    ]);

    $this->patchJson("/archives/{$watchItem->id}/memos/{$memo->id}", [
        'seconds' => 60,
        'body'    => 'テスト',
    ])->assertUnauthorized();
});

it('未認証で DELETE すると 401 を返す', function (): void {
    $memo      = TimestampMemo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $memo->profile_id,
        'youtube_video_id' => $memo->youtube_video_id,
    ]);

    $this->deleteJson("/archives/{$watchItem->id}/memos/{$memo->id}")
        ->assertUnauthorized();
});

// ==================== 所有権テスト ====================

it('他ユーザーの watchItem へのメモ作成は 403 を返す（SC-005）', function (): void {
    $owner    = Profile::factory()->create();
    $intruder = Profile::factory()->create();
    $video    = YoutubeVideo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id'       => $owner->id,
        'youtube_video_id' => $video->id,
    ]);

    $this->actingAs($intruder)
        ->postJson("/archives/{$watchItem->id}/memos", [
            'seconds' => 60,
            'body'    => '不正作成',
        ])
        ->assertForbidden();

    $this->assertDatabaseEmpty('timestamp_memos');
});

it('他ユーザーのメモへの PATCH は 403 を返す（SC-005）', function (): void {
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
        'body'             => '元のメモ',
    ]);

    $this->actingAs($intruder)
        ->patchJson("/archives/{$watchItem->id}/memos/{$memo->id}", [
            'seconds' => 60,
            'body'    => '不正更新',
        ])
        ->assertForbidden();

    // データは変更されていない
    $this->assertDatabaseHas('timestamp_memos', ['id' => $memo->id, 'body' => '元のメモ']);
});

it('他ユーザーのメモへの DELETE は 403 を返す（SC-005）', function (): void {
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
    ]);

    $this->actingAs($intruder)
        ->deleteJson("/archives/{$watchItem->id}/memos/{$memo->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('timestamp_memos', ['id' => $memo->id]);
});

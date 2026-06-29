<?php

declare(strict_types=1);

use App\Enums\WatchStatus;
use App\Models\Profile;
use App\Models\UserWatchItem;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ==================== セットアップヘルパー ====================

/**
 * 再生位置保存テスト用フィクスチャを作成する。
 *
 * @return array{profile: Profile, watchItem: UserWatchItem}
 */
function makePlaybackFixture(array $itemAttrs = [], array $videoAttrs = []): array
{
    $profile = Profile::factory()->create();
    $video   = YoutubeVideo::factory()->create(array_merge(
        ['is_available' => true, 'duration_seconds' => 600],
        $videoAttrs,
    ));
    $watchItem = UserWatchItem::factory()->create(array_merge(
        ['profile_id' => $profile->id, 'youtube_video_id' => $video->id, 'status' => WatchStatus::WantToWatch->value],
        $itemAttrs,
    ));

    return compact('profile', 'watchItem');
}

// ==================== 正常系テスト ====================

it('is_ended=false で last_position_seconds が保存される（SC-001）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makePlaybackFixture();

    $this->actingAs($profile)
        ->patchJson("/watch-items/{$watchItem->id}/position", [
            'last_position_seconds' => 120,
            'is_ended'              => false,
        ])
        ->assertNoContent();

    $this->assertDatabaseHas('user_watch_items', [
        'id'                    => $watchItem->id,
        'last_position_seconds' => 120,
    ]);
});

it('is_ended=false で want_to_watch → watching へステータスが変わる（FR-004）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makePlaybackFixture(
        ['status' => WatchStatus::WantToWatch->value, 'started_at' => null]
    );

    $this->actingAs($profile)
        ->patchJson("/watch-items/{$watchItem->id}/position", [
            'last_position_seconds' => 30,
            'is_ended'              => false,
        ])
        ->assertNoContent();

    $this->assertDatabaseHas('user_watch_items', [
        'id'     => $watchItem->id,
        'status' => WatchStatus::Watching->value,
    ]);

    // started_at が設定される
    expect($watchItem->fresh()->started_at)->not->toBeNull();
});

it('is_ended=false で watching ステータスは変化しない（FR-010）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makePlaybackFixture(
        ['status' => WatchStatus::Watching->value]
    );

    $this->actingAs($profile)
        ->patchJson("/watch-items/{$watchItem->id}/position", [
            'last_position_seconds' => 200,
            'is_ended'              => false,
        ])
        ->assertNoContent();

    $this->assertDatabaseHas('user_watch_items', [
        'id'     => $watchItem->id,
        'status' => WatchStatus::Watching->value,
    ]);
});

it('is_ended=false で watched ステータスは watching へ戻らない（FR-010）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makePlaybackFixture(
        ['status' => WatchStatus::Watched->value]
    );

    $this->actingAs($profile)
        ->patchJson("/watch-items/{$watchItem->id}/position", [
            'last_position_seconds' => 300,
            'is_ended'              => false,
        ])
        ->assertNoContent();

    $this->assertDatabaseHas('user_watch_items', [
        'id'     => $watchItem->id,
        'status' => WatchStatus::Watched->value,  // 変化なし
    ]);
});

it('is_ended=false で skipped ステータスは変化しない（仕様 Q1: once skipped stays skipped）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makePlaybackFixture(
        ['status' => WatchStatus::Skipped->value]
    );

    $this->actingAs($profile)
        ->patchJson("/watch-items/{$watchItem->id}/position", [
            'last_position_seconds' => 100,
            'is_ended'              => false,
        ])
        ->assertNoContent();

    $this->assertDatabaseHas('user_watch_items', [
        'id'     => $watchItem->id,
        'status' => WatchStatus::Skipped->value,  // 変化なし
    ]);
});

it('is_ended=true で watched になり watched_at が設定される（FR-008 / SC-003）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makePlaybackFixture(
        ['status' => WatchStatus::Watching->value]
    );

    $this->actingAs($profile)
        ->patchJson("/watch-items/{$watchItem->id}/position", [
            'last_position_seconds' => 595,
            'is_ended'              => true,
        ])
        ->assertNoContent();

    $watchItem->refresh();
    expect($watchItem->status)->toBe(WatchStatus::Watched)
        ->and($watchItem->watched_at)->not->toBeNull();
});

it('is_ended=true で skipped → watched になる（動画終了は完全視聴とみなす）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makePlaybackFixture(
        ['status' => WatchStatus::Skipped->value]
    );

    $this->actingAs($profile)
        ->patchJson("/watch-items/{$watchItem->id}/position", [
            'last_position_seconds' => 595,
            'is_ended'              => true,
        ])
        ->assertNoContent();

    $this->assertDatabaseHas('user_watch_items', [
        'id'     => $watchItem->id,
        'status' => WatchStatus::Watched->value,
    ]);
});

it('is_ended=true で watched は変化しない（冪等）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makePlaybackFixture(
        ['status' => WatchStatus::Watched->value, 'watched_at' => now()->subDay()]
    );

    $originalWatchedAt = $watchItem->watched_at;

    $this->actingAs($profile)
        ->patchJson("/watch-items/{$watchItem->id}/position", [
            'last_position_seconds' => 595,
            'is_ended'              => true,
        ])
        ->assertNoContent();

    // watched ステータスのまま、watched_at は上書きされない
    $watchItem->refresh();
    expect($watchItem->status)->toBe(WatchStatus::Watched)
        ->and($watchItem->watched_at->toDateTimeString())
        ->toBe($originalWatchedAt->toDateTimeString());
});

// ==================== 上書き防止テスト ====================

it('現在値より小さい last_position_seconds は上書きしない（FR-011 / SC-007）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makePlaybackFixture(
        ['last_position_seconds' => 300, 'status' => WatchStatus::Watching->value]
    );

    $this->actingAs($profile)
        ->patchJson("/watch-items/{$watchItem->id}/position", [
            'last_position_seconds' => 100,  // 300 より小さい
            'is_ended'              => false,
        ])
        ->assertNoContent();

    // 300 のまま変わらない
    $this->assertDatabaseHas('user_watch_items', [
        'id'                    => $watchItem->id,
        'last_position_seconds' => 300,
    ]);
});

it('現在値と同じ last_position_seconds は更新しない', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makePlaybackFixture(
        ['last_position_seconds' => 200, 'status' => WatchStatus::Watching->value]
    );

    $this->actingAs($profile)
        ->patchJson("/watch-items/{$watchItem->id}/position", [
            'last_position_seconds' => 200,
            'is_ended'              => false,
        ])
        ->assertNoContent();

    $this->assertDatabaseHas('user_watch_items', [
        'id'                    => $watchItem->id,
        'last_position_seconds' => 200,
    ]);
});

// ==================== バリデーションテスト ====================

it('last_position_seconds が負の値で 422 を返す', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makePlaybackFixture();

    $this->actingAs($profile)
        ->patchJson("/watch-items/{$watchItem->id}/position", [
            'last_position_seconds' => -1,
            'is_ended'              => false,
        ])
        ->assertUnprocessable();
});

it('last_position_seconds が duration_seconds を超える値で 422 を返す（FR-017）', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makePlaybackFixture(
        [],
        ['duration_seconds' => 600]
    );

    $this->actingAs($profile)
        ->patchJson("/watch-items/{$watchItem->id}/position", [
            'last_position_seconds' => 700,  // 600 より大きい
            'is_ended'              => false,
        ])
        ->assertUnprocessable();
});

it('duration_seconds が null の動画では max バリデーションをスキップする', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makePlaybackFixture(
        [],
        ['duration_seconds' => null]
    );

    // duration が null でも 0以上の値は受け付ける
    $this->actingAs($profile)
        ->patchJson("/watch-items/{$watchItem->id}/position", [
            'last_position_seconds' => 99999,
            'is_ended'              => false,
        ])
        ->assertNoContent();
});

it('is_ended が欠けると 422 を返す', function (): void {
    ['profile' => $profile, 'watchItem' => $watchItem] = makePlaybackFixture();

    $this->actingAs($profile)
        ->patchJson("/watch-items/{$watchItem->id}/position", [
            'last_position_seconds' => 100,
        ])
        ->assertUnprocessable();
});

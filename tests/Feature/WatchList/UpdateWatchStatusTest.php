<?php

declare(strict_types=1);

use App\Enums\WatchStatus;
use App\Models\Profile;
use App\Models\UserWatchItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('未認証では /login へリダイレクトされる', function (): void {
    $item = UserWatchItem::factory()->create();
    $this->patch("/watchlist/{$item->id}", ['status' => 'watched'])
        ->assertRedirect('/login');
});

it('want_to_watch から watched へ変更すると watched_at が設定される（FR-008）', function (): void {
    $user = Profile::factory()->create();
    $item = UserWatchItem::factory()->wantToWatch()->create(['profile_id' => $user->id]);

    $this->actingAs($user)
        ->patch("/watchlist/{$item->id}", ['status' => 'watched'])
        ->assertRedirect();

    $this->assertDatabaseHas('user_watch_items', [
        'id'         => $item->id,
        'status'     => WatchStatus::Watched->value,
    ]);

    // watched_at が設定されていることを確認
    $updated = $item->fresh();
    expect($updated->watched_at)->not->toBeNull();
    expect($updated->status)->toBe(WatchStatus::Watched);
});

it('watched から want_to_watch へ戻せる（US2 AC-4）', function (): void {
    $user = Profile::factory()->create();
    $item = UserWatchItem::factory()->watched()->create(['profile_id' => $user->id]);

    $this->actingAs($user)
        ->patch("/watchlist/{$item->id}", ['status' => 'want_to_watch'])
        ->assertRedirect();

    $this->assertDatabaseHas('user_watch_items', [
        'id'     => $item->id,
        'status' => WatchStatus::WantToWatch->value,
    ]);
});

it('skipped へ変更すると skipped_at が設定される（FR-008）', function (): void {
    $user = Profile::factory()->create();
    $item = UserWatchItem::factory()->wantToWatch()->create(['profile_id' => $user->id]);

    $this->actingAs($user)
        ->patch("/watchlist/{$item->id}", ['status' => 'skipped'])
        ->assertRedirect();

    $updated = $item->fresh();
    expect($updated->skipped_at)->not->toBeNull();
    expect($updated->status)->toBe(WatchStatus::Skipped);
});

it('他ユーザーの user_watch_items は更新できない（FR-009・SC-005）', function (): void {
    $user  = Profile::factory()->create();
    $other = Profile::factory()->create();
    $item  = UserWatchItem::factory()->create(['profile_id' => $other->id]);

    $this->actingAs($user)
        ->patch("/watchlist/{$item->id}", ['status' => 'watched'])
        ->assertForbidden();

    // ステータスが変わっていないことを確認
    $this->assertDatabaseHas('user_watch_items', [
        'id'     => $item->id,
        'status' => $item->status->value,
    ]);
});

it('watching へ手動変更すると started_at が設定される', function (): void {
    $user = Profile::factory()->create();
    $item = UserWatchItem::factory()->wantToWatch()->create(['profile_id' => $user->id]);

    $this->actingAs($user)
        ->patch("/watchlist/{$item->id}", ['status' => 'watching'])
        ->assertRedirect();

    $updated = $item->fresh();
    expect($updated->status)->toBe(WatchStatus::Watching);
    expect($updated->started_at)->not->toBeNull();
});

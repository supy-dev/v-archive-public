<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\UserWatchItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('見るリストの各カードに副次操作メニューとステータスラベルを表示する', function (): void {
    $profile = Profile::factory()->create();
    $item = UserWatchItem::factory()->wantToWatch()->create([
        'profile_id' => $profile->id,
    ]);

    $this->actingAs($profile)
        ->get(route('watchlist.index'))
        ->assertOk()
        ->assertSee('その他の操作')
        ->assertSee('削除して未整理に戻す')
        ->assertSee('watchlist-mobile-status-label', false)
        ->assertSee(route('watchlist.destroy', $item), false);
});

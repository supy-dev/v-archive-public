<?php

declare(strict_types=1);

use App\Enums\ChannelSyncStatus;
use App\Models\Oshi;
use App\Models\Profile;
use App\Models\UserChannel;
use App\Models\YoutubeChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('推しが1件のとき同期状況と次のアクションを表示する', function (): void {
    $profile = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $profile->id]);
    $channel = YoutubeChannel::factory()->create([
        'sync_status' => ChannelSyncStatus::Synced,
    ]);

    UserChannel::factory()->main()->create([
        'profile_id' => $profile->id,
        'oshi_id' => $oshi->id,
        'youtube_channel_id' => $channel->id,
    ]);

    $this->actingAs($profile)
        ->get(route('oshis.index'))
        ->assertOk()
        ->assertSee('推し活の準備を整えましょう')
        ->assertSee('同期済み')
        ->assertSee('チャンネル設定')
        ->assertSee(route('archive.index', ['oshi_id' => $oshi->id]), false);
});

it('推しが複数いるとき次のステップ案内を表示しない', function (): void {
    $profile = Profile::factory()->create();

    Oshi::factory()->count(2)->create(['profile_id' => $profile->id]);

    $this->actingAs($profile)
        ->get(route('oshis.index'))
        ->assertOk()
        ->assertDontSee('推し活の準備を整えましょう');
});

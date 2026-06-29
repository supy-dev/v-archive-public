<?php

declare(strict_types=1);

use App\Enums\WatchStatus;
use App\Models\Oshi;
use App\Models\Profile;
use App\Models\TimestampMemo;
use App\Models\UserChannel;
use App\Models\UserWatchItem;
use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('未認証では /login へリダイレクトされる', function (): void {
    $this->get(route('home'))->assertRedirect('/login');
});

it('ホーム画面のサマリー件数が実データと一致する（FR-010・SC-006）', function (): void {
    $profile = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $profile->id]);
    $channel = YoutubeChannel::factory()->create();

    UserChannel::factory()->create([
        'profile_id' => $profile->id,
        'oshi_id' => $oshi->id,
        'youtube_channel_id' => $channel->id,
    ]);

    // 利用可能な動画を 10 件作成
    $videos = YoutubeVideo::factory()->count(10)->create([
        'youtube_channel_id' => $channel->id,
        'is_available' => true,
    ]);

    // 5 件を want_to_watch に登録
    UserWatchItem::factory()->count(5)->create([
        'profile_id' => $profile->id,
        'youtube_video_id' => fn () => $videos->shift()->id,
        'status' => WatchStatus::WantToWatch->value,
    ]);

    // 2 件を watched に登録
    UserWatchItem::factory()->count(2)->create([
        'profile_id' => $profile->id,
        'youtube_video_id' => fn () => $videos->shift()->id,
        'status' => WatchStatus::Watched->value,
    ]);

    // 1 件を watching に登録
    UserWatchItem::factory()->count(1)->create([
        'profile_id' => $profile->id,
        'youtube_video_id' => fn () => $videos->shift()->id,
        'status' => WatchStatus::Watching->value,
    ]);

    // 残り 2 件は未整理（user_watch_items なし）
    // unorganized = 10 - 8 = 2

    $response = $this->actingAs($profile)->get(route('home'));
    $response->assertOk();

    $homeStats = $response->viewData('homeStats');

    expect($homeStats['unorganized'])->toBe(2);
    expect($homeStats['want_to_watch'])->toBe(5);
    expect($homeStats['watched'])->toBe(2);
    expect($homeStats['watching'])->toBe(1);
});

it('未整理件数は is_available=false の動画を含まない（FR-011）', function (): void {
    $profile = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $profile->id]);
    $channel = YoutubeChannel::factory()->create();

    UserChannel::factory()->create([
        'profile_id' => $profile->id,
        'oshi_id' => $oshi->id,
        'youtube_channel_id' => $channel->id,
    ]);

    YoutubeVideo::factory()->create([
        'youtube_channel_id' => $channel->id,
        'is_available' => true,
    ]);
    // 非表示動画（未整理件数に含まれない）
    YoutubeVideo::factory()->create([
        'youtube_channel_id' => $channel->id,
        'is_available' => false,
    ]);

    $response = $this->actingAs($profile)->get(route('home'));
    $homeStats = $response->viewData('homeStats');

    // 利用可能な動画は 1 件のみ
    expect($homeStats['unorganized'])->toBe(1);
});

it('サマリーカードに「未整理」「見るリスト」「視聴中」「視聴済み」が表示される（FR-010）', function (): void {
    $profile = Profile::factory()->create();

    $this->actingAs($profile)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('未整理')
        ->assertSee('見るリスト')
        ->assertSee('視聴中')
        ->assertSee('視聴済み');
});

it('「最近のタイムスタンプ」に実データが含まれる（Feature 007 US3）', function (): void {
    $profile = Profile::factory()->create();
    $video   = YoutubeVideo::factory()->create();

    TimestampMemo::factory()->create([
        'profile_id'       => $profile->id,
        'youtube_video_id' => $video->id,
        'body'             => '実データのタイムスタンプメモ',
    ]);

    $response = $this->actingAs($profile)->get(route('home'));
    $response->assertOk();

    $recentMemos = $response->viewData('recentMemos');
    expect($recentMemos)->not->toBeNull();
    expect($recentMemos->count())->toBeGreaterThan(0);
});

it('サマリーカードのリンクが正しいページへ遷移する（US3 AC-2/3）', function (): void {
    $profile = Profile::factory()->create();

    $response = $this->actingAs($profile)->get(route('home'));
    $response->assertOk()
        ->assertSee(route('archive.index'), false)
        ->assertSee(route('watchlist.index'), false);
});

it('ホームから推し・チャンネル管理へ移動でき、登録件数を表示する', function (): void {
    $profile = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $profile->id]);

    UserChannel::factory()->count(2)->create([
        'profile_id' => $profile->id,
        'oshi_id' => $oshi->id,
    ]);

    $this->actingAs($profile)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('推し・登録チャンネル')
        ->assertSee('2')
        ->assertSee(route('oshis.index'), false);
});

it('最近のアーカイブが3件以上ある場合は残りを見る導線を表示する', function (): void {
    $profile = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $profile->id]);
    $channel = YoutubeChannel::factory()->create();

    UserChannel::factory()->create([
        'profile_id' => $profile->id,
        'oshi_id' => $oshi->id,
        'youtube_channel_id' => $channel->id,
    ]);

    YoutubeVideo::factory()->count(3)->create([
        'youtube_channel_id' => $channel->id,
        'is_available' => true,
    ]);

    $this->actingAs($profile)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('2件表示中・残りのアーカイブを見る')
        ->assertSee(route('archive.index'), false);
});

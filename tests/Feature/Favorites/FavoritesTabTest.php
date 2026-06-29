<?php

declare(strict_types=1);

use App\Enums\OshiColor;
use App\Models\Oshi;
use App\Models\Profile;
use App\Models\TimestampMemo;
use App\Models\UserChannel;
use App\Models\UserWatchItem;
use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('デフォルト（パラメータなし）は神回タブを表示する', function (): void {
    $profile = Profile::factory()->create();

    $this->actingAs($profile)
        ->get('/favorites')
        ->assertOk()
        ->assertSee('神回');
});

it('神回タブに is_favorite=true の動画カードが表示される', function (): void {
    $profile = Profile::factory()->create();
    $video = YoutubeVideo::factory()->create(['title' => '神回テスト動画']);
    UserWatchItem::factory()->create([
        'profile_id' => $profile->id,
        'youtube_video_id' => $video->id,
        'is_favorite' => true,
    ]);

    $this->actingAs($profile)
        ->get('/favorites?tab=kamikai')
        ->assertOk()
        ->assertSee('神回テスト動画');
});

it('神回タブに is_favorite=false の動画は表示されない', function (): void {
    $profile = Profile::factory()->create();
    $video = YoutubeVideo::factory()->create(['title' => '通常の動画']);
    UserWatchItem::factory()->create([
        'profile_id' => $profile->id,
        'youtube_video_id' => $video->id,
        'is_favorite' => false,
    ]);

    $this->actingAs($profile)
        ->get('/favorites?tab=kamikai')
        ->assertOk()
        ->assertDontSee('通常の動画');
});

it('?tab=memos でお気に入りメモタブを表示する', function (): void {
    $profile = Profile::factory()->create();
    $video = YoutubeVideo::factory()->create();
    UserWatchItem::factory()->create([
        'profile_id' => $profile->id,
        'youtube_video_id' => $video->id,
    ]);
    TimestampMemo::factory()->create([
        'profile_id' => $profile->id,
        'youtube_video_id' => $video->id,
        'is_favorite' => true,
        'body' => 'お気に入りメモ本文',
    ]);

    $this->actingAs($profile)
        ->get('/favorites?tab=memos')
        ->assertOk()
        ->assertSee('お気に入りメモ本文');
});

it('神回タブで推し別フィルタが動作する', function (): void {
    $profile = Profile::factory()->create();
    $oshi1 = Oshi::factory()->create([
        'profile_id' => $profile->id,
        'name' => '黄色の推し',
        'color_id' => OshiColor::Yellow->value,
    ]);
    $oshi2 = Oshi::factory()->create(['profile_id' => $profile->id]);
    $channel1 = YoutubeChannel::factory()->create();
    $channel2 = YoutubeChannel::factory()->create();

    UserChannel::factory()->create(['profile_id' => $profile->id, 'oshi_id' => $oshi1->id, 'youtube_channel_id' => $channel1->id]);
    UserChannel::factory()->create(['profile_id' => $profile->id, 'oshi_id' => $oshi2->id, 'youtube_channel_id' => $channel2->id]);

    $video1 = YoutubeVideo::factory()->create(['title' => '推し1の神回', 'youtube_channel_id' => $channel1->id]);
    $video2 = YoutubeVideo::factory()->create(['title' => '推し2の神回', 'youtube_channel_id' => $channel2->id]);

    UserWatchItem::factory()->create(['profile_id' => $profile->id, 'youtube_video_id' => $video1->id, 'is_favorite' => true]);
    UserWatchItem::factory()->create(['profile_id' => $profile->id, 'youtube_video_id' => $video2->id, 'is_favorite' => true]);

    $this->actingAs($profile)
        ->get("/favorites?tab=kamikai&oshi_id={$oshi1->id}")
        ->assertOk()
        ->assertSee('推し1の神回')
        ->assertSee('黄色の推し')
        ->assertSee('oshi-color-yellow', false)
        ->assertDontSee('推し2の神回');
});

it('神回が0件のとき空状態メッセージを表示する', function (): void {
    $profile = Profile::factory()->create();

    $this->actingAs($profile)
        ->get('/favorites?tab=kamikai')
        ->assertOk()
        ->assertSee('神回登録した動画がありません。')
        ->assertSee('視聴中の配信を見る')
        ->assertSee(route('watchlist.index', ['status' => 'watching']), false);
});

it('神回が1件のとき登録方法の補助情報を表示する', function (): void {
    $profile = Profile::factory()->create();
    $video = YoutubeVideo::factory()->create();

    UserWatchItem::factory()->create([
        'profile_id' => $profile->id,
        'youtube_video_id' => $video->id,
        'is_favorite' => true,
    ]);

    $this->actingAs($profile)
        ->get('/favorites?tab=kamikai')
        ->assertOk()
        ->assertSee('心に残った配信を集めましょう')
        ->assertSee(route('watchlist.index'), false);
});

it('他ユーザーの神回は表示されない', function (): void {
    $user1 = Profile::factory()->create();
    $user2 = Profile::factory()->create();
    $video = YoutubeVideo::factory()->create(['title' => 'ユーザー1の神回']);

    UserWatchItem::factory()->create([
        'profile_id' => $user1->id,
        'youtube_video_id' => $video->id,
        'is_favorite' => true,
    ]);

    $this->actingAs($user2)
        ->get('/favorites?tab=kamikai')
        ->assertOk()
        ->assertDontSee('ユーザー1の神回');
});

it('未認証で /favorites にアクセスするとリダイレクトされる', function (): void {
    $this->get('/favorites')->assertRedirect('/login');
});

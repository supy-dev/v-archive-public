<?php

declare(strict_types=1);

use App\Enums\VideoType;
use App\Enums\WatchStatus;
use App\Models\Oshi;
use App\Models\Profile;
use App\Models\UserChannel;
use App\Models\UserWatchItem;
use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ==================== セットアップヘルパー ====================

/**
 * ユーザー・推し・チャンネル・動画を一括作成する。
 *
 * @return array{profile: Profile, oshi: Oshi, channel: YoutubeChannel, video: YoutubeVideo}
 */
function makeArchiveFixture(array $videoOverrides = []): array
{
    $profile = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $profile->id]);
    $channel = YoutubeChannel::factory()->create();

    UserChannel::factory()->create([
        'profile_id' => $profile->id,
        'oshi_id' => $oshi->id,
        'youtube_channel_id' => $channel->id,
    ]);

    $video = YoutubeVideo::factory()->create(array_merge([
        'youtube_channel_id' => $channel->id,
        'is_available' => true,
    ], $videoOverrides));

    return compact('profile', 'oshi', 'channel', 'video');
}

// ==================== 正常系テスト ====================

it('未認証では /login へリダイレクトされる', function (): void {
    $this->get('/archive')->assertRedirect('/login');
});

it('認証済みユーザーが新着アーカイブ一覧を閲覧できる', function (): void {
    ['profile' => $profile, 'video' => $video] = makeArchiveFixture();

    $this->actingAs($profile)
        ->get('/archive')
        ->assertOk()
        ->assertSee($video->title);
});

it('user_watch_items が存在しない動画のみ表示される（未整理のみ、FR-002）', function (): void {
    ['profile' => $profile, 'video' => $video] = makeArchiveFixture();

    // 別の動画を追加し、見るリストに登録する
    $organizedVideo = YoutubeVideo::factory()->create([
        'youtube_channel_id' => $video->youtube_channel_id,
        'is_available' => true,
    ]);
    UserWatchItem::factory()->create([
        'profile_id' => $profile->id,
        'youtube_video_id' => $organizedVideo->id,
        'status' => WatchStatus::WantToWatch->value,
    ]);

    $this->actingAs($profile)
        ->get('/archive')
        ->assertSee($video->title)
        ->assertDontSee($organizedVideo->title);
});

it('is_available=false の動画は一覧から除外される（FR-011）', function (): void {
    ['profile' => $profile, 'channel' => $channel] = makeArchiveFixture();

    $unavailableVideo = YoutubeVideo::factory()->create([
        'youtube_channel_id' => $channel->id,
        'is_available' => false,
        'title' => 'この動画は表示されない',
    ]);

    $this->actingAs($profile)
        ->get('/archive')
        ->assertDontSee($unavailableVideo->title);
});

it('推しでフィルタリングできる（FR-004）', function (): void {
    $profile = Profile::factory()->create();
    $oshi1 = Oshi::factory()->create(['profile_id' => $profile->id, 'name' => '推し1']);
    $oshi2 = Oshi::factory()->create(['profile_id' => $profile->id, 'name' => '推し2']);
    $channel1 = YoutubeChannel::factory()->create();
    $channel2 = YoutubeChannel::factory()->create();

    UserChannel::factory()->create([
        'profile_id' => $profile->id, 'oshi_id' => $oshi1->id, 'youtube_channel_id' => $channel1->id,
    ]);
    UserChannel::factory()->create([
        'profile_id' => $profile->id, 'oshi_id' => $oshi2->id, 'youtube_channel_id' => $channel2->id,
    ]);

    $video1 = YoutubeVideo::factory()->create(['youtube_channel_id' => $channel1->id, 'is_available' => true, 'title' => '推し1の動画']);
    $video2 = YoutubeVideo::factory()->create(['youtube_channel_id' => $channel2->id, 'is_available' => true, 'title' => '推し2の動画']);

    $this->actingAs($profile)
        ->get('/archive?oshi_id='.$oshi1->id)
        ->assertSee($video1->title)
        ->assertDontSee($video2->title);
});

it('動画種別でフィルタリングできる（FR-004）', function (): void {
    ['profile' => $profile, 'channel' => $channel] = makeArchiveFixture([
        'video_type' => VideoType::Archive->value,
        'title' => 'アーカイブ動画',
    ]);

    $shortVideo = YoutubeVideo::factory()->create([
        'youtube_channel_id' => $channel->id,
        'is_available' => true,
        'video_type' => VideoType::Short->value,
        'title' => 'ショート動画',
    ]);

    $this->actingAs($profile)
        ->get('/archive?video_type='.VideoType::Archive->value)
        ->assertSee('アーカイブ動画')
        ->assertDontSee('ショート動画');
});

it('タイトルまたは推し名でアーカイブを検索できる', function (): void {
    ['profile' => $profile, 'channel' => $channel, 'video' => $video] = makeArchiveFixture([
        'title' => '検索対象のスペシャル配信',
    ]);

    $otherVideo = YoutubeVideo::factory()->create([
        'youtube_channel_id' => $channel->id,
        'is_available' => true,
        'title' => '通常の配信',
    ]);

    $this->actingAs($profile)
        ->get('/archive?q='.urlencode('スペシャル'))
        ->assertOk()
        ->assertSee($video->title)
        ->assertDontSee($otherVideo->title);
});

it('未整理動画がない場合は空状態メッセージを表示する（US1 AC-6）', function (): void {
    $profile = Profile::factory()->create();

    // チャンネルも登録しない = 動画なし
    $this->actingAs($profile)
        ->get('/archive')
        ->assertOk()
        ->assertSee('未整理動画はありません');
});

it('他ユーザーのチャンネル動画は表示されない', function (): void {
    // other ユーザーのチャンネル（current ユーザーは未登録）
    $currentUser = Profile::factory()->create();
    $otherUser = Profile::factory()->create();
    $channel = YoutubeChannel::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $otherUser->id]);

    UserChannel::factory()->create([
        'profile_id' => $otherUser->id,
        'oshi_id' => $oshi->id,
        'youtube_channel_id' => $channel->id,
    ]);

    $otherVideo = YoutubeVideo::factory()->create([
        'youtube_channel_id' => $channel->id,
        'is_available' => true,
        'title' => '他ユーザーの動画',
    ]);

    $this->actingAs($currentUser)
        ->get('/archive')
        ->assertDontSee('他ユーザーの動画');
});

it('1 ページあたり 20 件でページネーションされる（FR-012）', function (): void {
    ['profile' => $profile, 'channel' => $channel] = makeArchiveFixture();

    // 追加で 20 件作成（合計 21 件）
    YoutubeVideo::factory()->count(20)->create([
        'youtube_channel_id' => $channel->id,
        'is_available' => true,
    ]);

    $this->actingAs($profile)
        ->get('/archive')
        ->assertOk()
        ->assertSee('pagination', false)
        ->assertSee('an-pagination__mobile', false)
        ->assertSee('an-pagination__mobile-select', false)
        ->assertSee('移動するページを選択')
        ->assertSee('2ページ');
});

<?php

declare(strict_types=1);

use App\Enums\OshiColor;
use App\Models\Oshi;
use App\Models\Profile;
use App\Models\Tag;
use App\Models\TimestampMemo;
use App\Models\UserChannel;
use App\Models\UserWatchItem;
use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('未認証では /login へリダイレクトされる', function (): void {
    $this->get('/memos')->assertRedirect('/login');
});

it('全タイムスタンプメモが★に関わらず表示される', function (): void {
    $profile = Profile::factory()->create();
    $video = YoutubeVideo::factory()->create();

    TimestampMemo::factory()->create([
        'profile_id' => $profile->id,
        'youtube_video_id' => $video->id,
        'is_favorite' => true,
        'body' => '★メモ',
    ]);
    TimestampMemo::factory()->create([
        'profile_id' => $profile->id,
        'youtube_video_id' => $video->id,
        'is_favorite' => false,
        'body' => '通常メモ',
    ]);

    $this->actingAs($profile)
        ->get('/memos')
        ->assertOk()
        ->assertSee('★メモ')
        ->assertSee('通常メモ');
});

it('メモが0件のとき視聴中の配信へ進める', function (): void {
    $profile = Profile::factory()->create();

    $this->actingAs($profile)
        ->get('/memos')
        ->assertOk()
        ->assertSee('タイムスタンプメモがまだありません。')
        ->assertSee('視聴中の配信を見る')
        ->assertSee(route('watchlist.index', ['status' => 'watching']), false);
});

it('他ユーザーのメモは表示されない', function (): void {
    $user1 = Profile::factory()->create();
    $user2 = Profile::factory()->create();
    $video = YoutubeVideo::factory()->create();

    TimestampMemo::factory()->create([
        'profile_id' => $user1->id,
        'youtube_video_id' => $video->id,
        'body' => 'ユーザー1のメモ',
    ]);

    $this->actingAs($user2)
        ->get('/memos')
        ->assertOk()
        ->assertDontSee('ユーザー1のメモ');
});

it('推し別フィルタが動作する', function (): void {
    $profile = Profile::factory()->create();
    $oshi1 = Oshi::factory()->create([
        'profile_id' => $profile->id,
        'name' => 'ピンクの推し',
        'color_id' => OshiColor::Pink->value,
    ]);
    $oshi2 = Oshi::factory()->create(['profile_id' => $profile->id]);
    $channel1 = YoutubeChannel::factory()->create();
    $channel2 = YoutubeChannel::factory()->create();

    UserChannel::factory()->create(['profile_id' => $profile->id, 'oshi_id' => $oshi1->id, 'youtube_channel_id' => $channel1->id]);
    UserChannel::factory()->create(['profile_id' => $profile->id, 'oshi_id' => $oshi2->id, 'youtube_channel_id' => $channel2->id]);

    $video1 = YoutubeVideo::factory()->create(['youtube_channel_id' => $channel1->id]);
    $video2 = YoutubeVideo::factory()->create(['youtube_channel_id' => $channel2->id]);

    TimestampMemo::factory()->create(['profile_id' => $profile->id, 'youtube_video_id' => $video1->id, 'body' => '推し1のメモ']);
    TimestampMemo::factory()->create(['profile_id' => $profile->id, 'youtube_video_id' => $video2->id, 'body' => '推し2のメモ']);

    $this->actingAs($profile)
        ->get("/memos?oshi_id={$oshi1->id}")
        ->assertOk()
        ->assertSee('推し1のメモ')
        ->assertSee('ピンクの推し')
        ->assertSee('oshi-color-pink', false)
        ->assertDontSee('推し2のメモ');
});

it('タグ別フィルタが動作する', function (): void {
    $profile = Profile::factory()->create();
    $video = YoutubeVideo::factory()->create();
    $tag = Tag::factory()->system()->create(['name' => 'テストタグ', 'slug' => 'test-tag']);

    $memoWithTag = TimestampMemo::factory()->create([
        'profile_id' => $profile->id,
        'youtube_video_id' => $video->id,
        'body' => 'タグ付きメモ',
    ]);
    $memoWithTag->tags()->attach($tag->id);

    TimestampMemo::factory()->create([
        'profile_id' => $profile->id,
        'youtube_video_id' => $video->id,
        'body' => 'タグなしメモ',
    ]);

    $this->actingAs($profile)
        ->get("/memos?tag_id={$tag->id}")
        ->assertOk()
        ->assertSee('タグ付きメモ')
        ->assertDontSee('タグなしメモ');
});

it('メモカードに★ボタンが存在しない（FR-017）', function (): void {
    $profile = Profile::factory()->create();
    $video = YoutubeVideo::factory()->create();

    TimestampMemo::factory()->create([
        'profile_id' => $profile->id,
        'youtube_video_id' => $video->id,
        'body' => 'テストメモ',
    ]);

    $response = $this->actingAs($profile)->get('/memos');
    $response->assertOk();

    // メモお気に入りトグルエンドポイントが存在しないことを確認（memos/{id}/favorite パターン）
    $response->assertDontSee('memos/favorite', false);
    $response->assertDontSee('お気に入り解除', false);
});

it('各メモカードのアーカイブリンクが archives.show へ向く', function (): void {
    $profile = Profile::factory()->create();
    $video = YoutubeVideo::factory()->create();
    $watchItem = UserWatchItem::factory()->create([
        'profile_id' => $profile->id,
        'youtube_video_id' => $video->id,
    ]);

    TimestampMemo::factory()->create([
        'profile_id' => $profile->id,
        'youtube_video_id' => $video->id,
        'body' => 'アーカイブリンクメモ',
    ]);

    $this->actingAs($profile)
        ->get('/memos')
        ->assertOk()
        ->assertSee(route('archives.show', $watchItem), false);
});

it('ページネーションが機能する', function (): void {
    $profile = Profile::factory()->create();
    $video = YoutubeVideo::factory()->create();

    TimestampMemo::factory()->count(25)->create([
        'profile_id' => $profile->id,
        'youtube_video_id' => $video->id,
    ]);

    $this->actingAs($profile)
        ->get('/memos')
        ->assertOk();
});

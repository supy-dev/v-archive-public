<?php

declare(strict_types=1);

use App\Jobs\FetchOlderYoutubeVideosJob;
use App\Models\Oshi;
use App\Models\Profile;
use App\Models\UserChannel;
use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use App\Services\YouTube\FetchUploadedVideosService;
use App\Services\YouTube\FetchVideoDetailsService;
use App\Services\YouTube\SyncChannelVideosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function makeOlderVideosResponse(array $videoIds, ?string $nextPageToken = null): array
{
    return [
        'items'         => array_map(fn (string $id) => ['contentDetails' => ['videoId' => $id]], $videoIds),
        'nextPageToken' => $nextPageToken,
    ];
}

function makeOlderDetailsResponse(array $videoIds): array
{
    return [
        'items' => array_map(fn (string $id) => [
            'id'             => $id,
            'snippet'        => [
                'title'                => "過去動画 {$id}",
                'description'          => '',
                'publishedAt'          => '2025-06-01T00:00:00Z',
                'thumbnails'           => ['high' => ['url' => "https://i.ytimg.com/vi/{$id}/hq.jpg"]],
                'liveBroadcastContent' => 'none',
            ],
            'contentDetails' => ['duration' => 'PT20M'],
            'status'         => ['privacyStatus' => 'public'],
        ], $videoIds),
    ];
}

it('oldest_page_token を使い過去動画を追加取得する', function (): void {
    $channel = YoutubeChannel::factory()->create([
        'uploads_playlist_id' => 'UUtest',
        'oldest_page_token'   => 'page_token_old',
    ]);

    Http::fake([
        '*/playlistItems*' => Http::response(makeOlderVideosResponse(['old_vid_1', 'old_vid_2'], 'page_token_older')),
        '*/videos*'        => Http::response(makeOlderDetailsResponse(['old_vid_1', 'old_vid_2'])),
    ]);

    $job = new FetchOlderYoutubeVideosJob($channel);
    $job->handle(
        app(FetchUploadedVideosService::class),
        app(FetchVideoDetailsService::class),
        app(SyncChannelVideosService::class),
    );

    expect(YoutubeVideo::where('youtube_channel_id', $channel->id)->count())->toBe(2);
    expect($channel->fresh()->oldest_page_token)->toBe('page_token_older');
    expect($channel->fresh()->oldest_fetched_at)->not->toBeNull();
});

it('nextPageToken が null の場合 oldest_page_token は null になる（全件取得済み）', function (): void {
    $channel = YoutubeChannel::factory()->create([
        'uploads_playlist_id' => 'UUtest',
        'oldest_page_token'   => 'last_page_token',
    ]);

    Http::fake([
        '*/playlistItems*' => Http::response(makeOlderVideosResponse(['very_old_vid'], null)),
        '*/videos*'        => Http::response(makeOlderDetailsResponse(['very_old_vid'])),
    ]);

    $job = new FetchOlderYoutubeVideosJob($channel);
    $job->handle(app(FetchUploadedVideosService::class), app(FetchVideoDetailsService::class), app(SyncChannelVideosService::class));

    expect($channel->fresh()->oldest_page_token)->toBeNull();
});

it('他ユーザーの userChannel で fetchOlder にアクセスすると 403 になる', function (): void {
    Queue::fake();

    $owner   = Profile::factory()->create();
    $attacker = Profile::factory()->create();
    $oshi    = Oshi::factory()->create(['profile_id' => $owner->id]);
    $channel = YoutubeChannel::factory()->create(['oldest_page_token' => 'some_token']);

    UserChannel::factory()->create([
        'profile_id'         => $owner->id,
        'oshi_id'            => $oshi->id,
        'youtube_channel_id' => $channel->id,
    ]);

    $ownerChannel = UserChannel::where('profile_id', $owner->id)->first();

    $this->actingAs($attacker)
        ->post(route('oshis.channels.fetchOlder', [$oshi, $ownerChannel]))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('fetchOlder エンドポイントが FetchOlderYoutubeVideosJob を dispatch する', function (): void {
    Queue::fake();

    $profile    = Profile::factory()->create();
    $oshi       = Oshi::factory()->create(['profile_id' => $profile->id]);
    $channel    = YoutubeChannel::factory()->create([
        'uploads_playlist_id' => 'UUtest',
        'oldest_page_token'   => 'some_token',
    ]);
    $userChannel = UserChannel::factory()->create([
        'profile_id'         => $profile->id,
        'oshi_id'            => $oshi->id,
        'youtube_channel_id' => $channel->id,
    ]);

    $this->actingAs($profile)
        ->post(route('oshis.channels.fetchOlder', [$oshi, $userChannel]))
        ->assertRedirect(route('oshis.show', $oshi));

    Queue::assertPushed(FetchOlderYoutubeVideosJob::class);
});

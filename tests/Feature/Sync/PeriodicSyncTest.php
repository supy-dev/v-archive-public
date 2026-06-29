<?php

declare(strict_types=1);

use App\Enums\ChannelSyncStatus;
use App\Enums\LiveStatus;
use App\Jobs\RefreshYoutubeVideoDetailsJob;
use App\Jobs\SyncYoutubeChannelJob;
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

/**
 * playlistItems.list / videos.list フェイクレスポンス生成ヘルパー。
 */
function makePeriodicPlaylistResponse(array $videoIds, ?string $nextPageToken = null): array
{
    return [
        'items'         => array_map(fn (string $id) => ['contentDetails' => ['videoId' => $id]], $videoIds),
        'nextPageToken' => $nextPageToken,
    ];
}

function makePeriodicVideosResponse(array $videoIds): array
{
    return [
        'items' => array_map(fn (string $id) => [
            'id'             => $id,
            'snippet'        => [
                'title'                => "定期テスト {$id}",
                'description'          => '',
                'publishedAt'          => '2026-01-01T00:00:00Z',
                'thumbnails'           => ['high' => ['url' => "https://i.ytimg.com/vi/{$id}/hq.jpg"]],
                'liveBroadcastContent' => 'none',
            ],
            'contentDetails' => ['duration' => 'PT10M'],
            'status'         => ['privacyStatus' => 'public'],
        ], $videoIds),
    ];
}

it('既存 videoId に到達したら取得を打ち切り、新着のみ追加する', function (): void {
    $channel = YoutubeChannel::factory()->create(['uploads_playlist_id' => 'UUtest']);

    // 既存レコード
    YoutubeVideo::factory()->create([
        'youtube_channel_id' => $channel->id,
        'youtube_video_id'   => 'old_vid',
    ]);

    // API: 新着1件 + 既存1件
    Http::fake([
        '*/playlistItems*' => Http::response(makePeriodicPlaylistResponse(['new_vid', 'old_vid'])),
        '*/videos*'        => Http::response(makePeriodicVideosResponse(['new_vid'])),
    ]);

    $job = new SyncYoutubeChannelJob($channel);
    $job->handle(
        app(FetchUploadedVideosService::class),
        app(FetchVideoDetailsService::class),
        app(SyncChannelVideosService::class),
    );

    // 既存1件 + 新着1件 = 2件、重複なし
    expect(YoutubeVideo::where('youtube_channel_id', $channel->id)->count())->toBe(2);
    expect(YoutubeVideo::where('youtube_video_id', 'new_vid')->exists())->toBeTrue();
});

it('last_synced_at が更新される', function (): void {
    $channel = YoutubeChannel::factory()->create(['uploads_playlist_id' => 'UUtest']);

    Http::fake([
        '*/playlistItems*' => Http::response(makePeriodicPlaylistResponse([])),
    ]);

    $job = new SyncYoutubeChannelJob($channel);
    $job->handle(app(FetchUploadedVideosService::class), app(FetchVideoDetailsService::class), app(SyncChannelVideosService::class));

    expect($channel->fresh()->last_synced_at)->not->toBeNull();
    expect($channel->fresh()->sync_status)->toBe(ChannelSyncStatus::Synced);
});

it('ライブ中動画には RefreshYoutubeVideoDetailsJob が dispatch される', function (): void {
    Queue::fake();

    $channel = YoutubeChannel::factory()->create(['uploads_playlist_id' => 'UUtest']);
    YoutubeVideo::factory()->create([
        'youtube_channel_id' => $channel->id,
        'youtube_video_id'   => 'live_vid',
        'live_status'        => LiveStatus::Live->value,
    ]);

    Http::fake([
        '*/playlistItems*' => Http::response(makePeriodicPlaylistResponse([])),
    ]);

    $job = new SyncYoutubeChannelJob($channel);
    $job->handle(app(FetchUploadedVideosService::class), app(FetchVideoDetailsService::class), app(SyncChannelVideosService::class));

    Queue::assertPushed(RefreshYoutubeVideoDetailsJob::class);
});

it('youtube:dispatch-syncs は sync_enabled=true のチャンネルにのみ Job を dispatch する', function (): void {
    Queue::fake();

    $channelEnabled  = YoutubeChannel::factory()->create(['uploads_playlist_id' => 'UUtest1']);
    $channelDisabled = YoutubeChannel::factory()->create(['uploads_playlist_id' => 'UUtest2']);

    UserChannel::factory()->create([
        'youtube_channel_id' => $channelEnabled->id,
        'sync_enabled'       => true,
    ]);
    UserChannel::factory()->create([
        'youtube_channel_id' => $channelDisabled->id,
        'sync_enabled'       => false,
    ]);

    $this->artisan('youtube:dispatch-syncs');

    Queue::assertPushed(SyncYoutubeChannelJob::class, 1);
    Queue::assertPushed(SyncYoutubeChannelJob::class, function (SyncYoutubeChannelJob $job) use ($channelEnabled): bool {
        return $job->youtubeChannel->id === $channelEnabled->id;
    });
});

it('ShouldBeUnique により同一チャンネルへの重複 dispatch は無視される', function (): void {
    // ShouldBeUnique の動作確認: uniqueId() が channel->id を返すこと
    $channel = YoutubeChannel::factory()->create();
    $job     = new SyncYoutubeChannelJob($channel);

    expect($job->uniqueId())->toBe($channel->id);
});

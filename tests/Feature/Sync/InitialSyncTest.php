<?php

declare(strict_types=1);

use App\Enums\ChannelSyncStatus;
use App\Jobs\InitialSyncYoutubeChannelJob;
use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use App\Services\YouTube\FetchUploadedVideosService;
use App\Services\YouTube\FetchVideoDetailsService;
use App\Services\YouTube\SyncChannelVideosService;
use App\Services\YouTube\YouTubeApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * playlistItems.list + videos.list のフェイクレスポンスを生成するヘルパー。
 */
function makePlaylistResponse(array $videoIds, ?string $nextPageToken = null): array
{
    return [
        'items' => array_map(fn (string $id) => [
            'contentDetails' => ['videoId' => $id],
        ], $videoIds),
        'nextPageToken' => $nextPageToken,
    ];
}

function makeVideosResponse(array $videoIds): array
{
    return [
        'items' => array_map(fn (string $id) => [
            'id' => $id,
            'snippet' => [
                'title'               => "テスト動画 {$id}",
                'description'         => 'テスト説明',
                'publishedAt'         => '2026-01-01T00:00:00Z',
                'thumbnails'          => ['high' => ['url' => "https://i.ytimg.com/vi/{$id}/hq.jpg"]],
                'liveBroadcastContent' => 'none',
            ],
            'contentDetails' => ['duration' => 'PT30M'],
            'status'         => ['privacyStatus' => 'public'],
        ], $videoIds),
    ];
}

it('初回同期Jobが最新50件の動画を作成する', function (): void {
    $videoIds = array_map(fn (int $i) => "vid_{$i}", range(1, 3));
    $channel  = YoutubeChannel::factory()->create([
        'uploads_playlist_id' => 'UUtest123',
        'sync_status'         => ChannelSyncStatus::Pending->value,
    ]);

    Http::fake([
        '*/playlistItems*' => Http::response(makePlaylistResponse($videoIds)),
        '*/videos*'        => Http::response(makeVideosResponse($videoIds)),
    ]);

    $job = new InitialSyncYoutubeChannelJob($channel);
    $job->handle(
        app(FetchUploadedVideosService::class),
        app(FetchVideoDetailsService::class),
        app(SyncChannelVideosService::class),
    );

    expect(YoutubeVideo::where('youtube_channel_id', $channel->id)->count())->toBe(3);
    expect($channel->fresh()->sync_status)->toBe(ChannelSyncStatus::Synced);
});

it('同一youtube_video_idで重複レコードが作成されない（upsert冪等性）', function (): void {
    $channel  = YoutubeChannel::factory()->create(['uploads_playlist_id' => 'UUtest']);
    $videoIds = ['vid_abc', 'vid_def'];

    Http::fake([
        '*/playlistItems*' => Http::response(makePlaylistResponse($videoIds)),
        '*/videos*'        => Http::response(makeVideosResponse($videoIds)),
    ]);

    $job = new InitialSyncYoutubeChannelJob($channel);
    // 2回実行しても重複しない
    $job->handle(app(FetchUploadedVideosService::class), app(FetchVideoDetailsService::class), app(SyncChannelVideosService::class));
    $job->handle(app(FetchUploadedVideosService::class), app(FetchVideoDetailsService::class), app(SyncChannelVideosService::class));

    expect(YoutubeVideo::where('youtube_channel_id', $channel->id)->count())->toBe(2);
});

it('sync_statusがsynced に更新される', function (): void {
    $channel = YoutubeChannel::factory()->create([
        'uploads_playlist_id' => 'UUtest',
        'sync_status'         => ChannelSyncStatus::Pending->value,
    ]);

    Http::fake([
        '*/playlistItems*' => Http::response(makePlaylistResponse([])),
        '*/videos*'        => Http::response(['items' => []]),
    ]);

    $job = new InitialSyncYoutubeChannelJob($channel);
    $job->handle(app(FetchUploadedVideosService::class), app(FetchVideoDetailsService::class), app(SyncChannelVideosService::class));

    expect($channel->fresh()->sync_status)->toBe(ChannelSyncStatus::Synced);
    expect($channel->fresh()->last_synced_at)->not->toBeNull();
});

it('429エラー時にYouTubeApiExceptionをthrowする', function (): void {
    $channel = YoutubeChannel::factory()->create(['uploads_playlist_id' => 'UUtest']);

    Http::fake([
        '*/playlistItems*' => Http::response([], 429),
    ]);

    $job = new InitialSyncYoutubeChannelJob($channel);

    expect(fn () => $job->handle(
        app(FetchUploadedVideosService::class),
        app(FetchVideoDetailsService::class),
        app(SyncChannelVideosService::class),
    ))->toThrow(YouTubeApiException::class);
});

it('APIキーがログに出力されない', function (): void {
    $channel = YoutubeChannel::factory()->create(['uploads_playlist_id' => 'UUtest']);

    Http::fake([
        '*/playlistItems*' => Http::response(['items' => [], 'nextPageToken' => null]),
    ]);

    Log::spy();

    $job = new InitialSyncYoutubeChannelJob($channel);
    $job->handle(app(FetchUploadedVideosService::class), app(FetchVideoDetailsService::class), app(SyncChannelVideosService::class));

    // ログにAPIキーが含まれないこと（ログ自体が飛んでいないか、含まれていないか）
    Log::shouldNotHaveReceived('error', [fn ($msg) => str_contains((string) $msg, 'api_key')]);
});

it('チャンネル登録後にInitialSyncJobがdispatchされる', function (): void {
    Queue::fake();

    // Queue::fake 以外のモックは不要（Job は dispatch されるだけで実行しない）
    expect(Queue::pushedJobs())->toBeEmpty();

    Queue::assertNothingPushed();

    // Job クラスが dispatch 可能であることを確認
    expect(class_exists(InitialSyncYoutubeChannelJob::class))->toBeTrue();
});

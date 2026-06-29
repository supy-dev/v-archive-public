<?php

declare(strict_types=1);

use App\Jobs\MarkUnavailableYoutubeVideosJob;
use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use App\Services\YouTube\FetchVideoDetailsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('API が返さない動画 ID は is_available が false になる', function (): void {
    $channel = YoutubeChannel::factory()->create();
    $video   = YoutubeVideo::factory()->create([
        'youtube_channel_id' => $channel->id,
        'youtube_video_id'   => 'deleted_vid',
        'is_available'       => true,
    ]);

    // API が空配列を返す（動画が削除された）
    Http::fake([
        '*/videos*' => Http::response(['items' => []]),
    ]);

    $job = new MarkUnavailableYoutubeVideosJob(['deleted_vid']);
    $job->handle(app(FetchVideoDetailsService::class));

    expect($video->fresh()->is_available)->toBeFalse();
});

it('API が返す利用可能な動画は変更されない', function (): void {
    $channel = YoutubeChannel::factory()->create();
    $video   = YoutubeVideo::factory()->create([
        'youtube_channel_id' => $channel->id,
        'youtube_video_id'   => 'active_vid',
        'is_available'       => true,
    ]);

    Http::fake([
        '*/videos*' => Http::response([
            'items' => [[
                'id'             => 'active_vid',
                'snippet'        => [
                    'title'                => 'アクティブ動画',
                    'description'          => '',
                    'publishedAt'          => '2026-01-01T00:00:00Z',
                    'thumbnails'           => ['high' => ['url' => 'https://example.com/thumb.jpg']],
                    'liveBroadcastContent' => 'none',
                ],
                'contentDetails' => ['duration' => 'PT10M'],
                'status'         => ['privacyStatus' => 'public'],
            ]],
        ]),
    ]);

    $job = new MarkUnavailableYoutubeVideosJob(['active_vid']);
    $job->handle(app(FetchVideoDetailsService::class));

    expect($video->fresh()->is_available)->toBeTrue();
});

it('privacyStatus が public 以外の動画は is_available が false になる', function (): void {
    $channel = YoutubeChannel::factory()->create();
    $video   = YoutubeVideo::factory()->create([
        'youtube_channel_id' => $channel->id,
        'youtube_video_id'   => 'private_vid',
        'is_available'       => true,
    ]);

    Http::fake([
        '*/videos*' => Http::response([
            'items' => [[
                'id'             => 'private_vid',
                'snippet'        => [
                    'title'                => '非公開動画',
                    'description'          => '',
                    'publishedAt'          => '2026-01-01T00:00:00Z',
                    'thumbnails'           => [],
                    'liveBroadcastContent' => 'none',
                ],
                'contentDetails' => ['duration' => 'PT5M'],
                'status'         => ['privacyStatus' => 'private'],
            ]],
        ]),
    ]);

    $job = new MarkUnavailableYoutubeVideosJob(['private_vid']);
    $job->handle(app(FetchVideoDetailsService::class));

    expect($video->fresh()->is_available)->toBeFalse();
});

it('youtube:mark-unavailable コマンドが Job を dispatch する', function (): void {
    Queue::fake();

    $channel = YoutubeChannel::factory()->create();
    YoutubeVideo::factory()->count(3)->create([
        'youtube_channel_id' => $channel->id,
        'is_available'       => true,
    ]);

    $this->artisan('youtube:mark-unavailable');

    Queue::assertPushed(MarkUnavailableYoutubeVideosJob::class);
});

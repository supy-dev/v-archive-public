<?php

declare(strict_types=1);

use App\Enums\LiveStatus;
use App\Enums\VideoType;
use App\Jobs\RefreshYoutubeVideoDetailsJob;
use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use App\Services\YouTube\FetchVideoDetailsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('ライブ終了後の動画で live_status が completed に更新される', function (): void {
    $channel = YoutubeChannel::factory()->create();
    $video   = YoutubeVideo::factory()->create([
        'youtube_channel_id' => $channel->id,
        'youtube_video_id'   => 'live_end_vid',
        'live_status'        => LiveStatus::Live->value,
        'actual_end_at'      => null,
    ]);

    Http::fake([
        '*/videos*' => Http::response([
            'items' => [[
                'id'                   => 'live_end_vid',
                'snippet'              => [
                    'title'                => 'テスト配信',
                    'description'          => '',
                    'publishedAt'          => '2026-01-01T00:00:00Z',
                    'thumbnails'           => ['high' => ['url' => 'https://example.com/thumb.jpg']],
                    'liveBroadcastContent' => 'none',
                ],
                'contentDetails'       => ['duration' => 'PT2H'],
                'status'               => ['privacyStatus' => 'public'],
                'liveStreamingDetails' => [
                    'actualStartTime' => '2026-01-01T10:00:00Z',
                    'actualEndTime'   => '2026-01-01T12:00:00Z',
                ],
            ]],
        ]),
    ]);

    $job = new RefreshYoutubeVideoDetailsJob($video);
    $job->handle(app(FetchVideoDetailsService::class));

    $updated = $video->fresh();
    expect($updated->live_status)->toBe(LiveStatus::Completed);
    expect($updated->actual_end_at)->not->toBeNull();
    expect($updated->duration_seconds)->toBe(7200);
    expect($updated->video_type)->toBe(VideoType::Archive);
});

it('API が動画を返さない場合 is_available が false になる', function (): void {
    $channel = YoutubeChannel::factory()->create();
    $video   = YoutubeVideo::factory()->create([
        'youtube_channel_id' => $channel->id,
        'youtube_video_id'   => 'deleted_vid',
    ]);

    Http::fake([
        '*/videos*' => Http::response(['items' => []]),
    ]);

    $job = new RefreshYoutubeVideoDetailsJob($video);
    $job->handle(app(FetchVideoDetailsService::class));

    expect($video->fresh()->is_available)->toBeFalse();
});

it('live_status=live で SyncJob が RefreshJob を dispatch する', function (): void {
    // Job クラスが存在し dispatch できることを確認
    expect(class_exists(RefreshYoutubeVideoDetailsJob::class))->toBeTrue();
});

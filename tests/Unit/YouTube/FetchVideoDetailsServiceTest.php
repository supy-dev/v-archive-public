<?php

declare(strict_types=1);

use App\Services\YouTube\FetchVideoDetailsService;
use App\Services\YouTube\ResolvedVideo;
use App\Services\YouTube\YouTubeApiException;
use Illuminate\Support\Facades\Http;

it('fetchBatch が videos.list から ResolvedVideo 配列を返す', function (): void {
    Http::fake([
        '*/videos*' => Http::response([
            'items' => [[
                'id'             => 'test_vid',
                'snippet'        => [
                    'title'                => 'テスト',
                    'description'          => '説明',
                    'publishedAt'          => '2026-01-01T00:00:00Z',
                    'thumbnails'           => ['high' => ['url' => 'https://i.ytimg.com/vi/test/hq.jpg']],
                    'liveBroadcastContent' => 'none',
                ],
                'contentDetails' => ['duration' => 'PT10M'],
                'status'         => ['privacyStatus' => 'public'],
            ]],
        ]),
    ]);

    $service  = app(FetchVideoDetailsService::class);
    $resolved = $service->fetchBatch(['test_vid']);

    expect($resolved)->toHaveCount(1);
    expect($resolved[0])->toBeInstanceOf(ResolvedVideo::class);
    expect($resolved[0]->youtubeVideoId)->toBe('test_vid');
    expect($resolved[0]->durationSeconds)->toBe(600);
    expect($resolved[0]->privacyStatus)->toBe('public');
});

it('空配列を渡すと空配列が返る', function (): void {
    $service = app(FetchVideoDetailsService::class);

    expect($service->fetchBatch([]))->toBeEmpty();
});

it('429 レスポンスで YouTubeApiException がスローされる', function (): void {
    Http::fake([
        '*/videos*' => Http::response([], 429),
    ]);

    $service = app(FetchVideoDetailsService::class);

    expect(fn () => $service->fetchBatch(['vid1']))->toThrow(YouTubeApiException::class);
});

it('description が 500 文字に切り詰められる', function (): void {
    $longDesc = str_repeat('あ', 600);

    Http::fake([
        '*/videos*' => Http::response([
            'items' => [[
                'id'             => 'vid1',
                'snippet'        => [
                    'title'                => 'テスト',
                    'description'          => $longDesc,
                    'publishedAt'          => '2026-01-01T00:00:00Z',
                    'thumbnails'           => [],
                    'liveBroadcastContent' => 'none',
                ],
                'contentDetails' => ['duration' => 'PT1M'],
                'status'         => ['privacyStatus' => 'public'],
            ]],
        ]),
    ]);

    $service  = app(FetchVideoDetailsService::class);
    $resolved = $service->fetchBatch(['vid1']);

    expect(mb_strlen($resolved[0]->description ?? ''))->toBeLessThanOrEqual(500);
});

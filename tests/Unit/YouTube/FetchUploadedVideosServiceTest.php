<?php

declare(strict_types=1);

use App\Models\YoutubeChannel;
use App\Services\YouTube\FetchUploadedVideosService;
use App\Services\YouTube\FetchedPlaylistPage;
use App\Services\YouTube\YouTubeApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('fetchLatest が playlistItems.list から videoId を収集する', function (): void {
    $channel = YoutubeChannel::factory()->create(['uploads_playlist_id' => 'UUtest']);

    Http::fake([
        '*/playlistItems*' => Http::response([
            'items' => [
                ['contentDetails' => ['videoId' => 'vid_a']],
                ['contentDetails' => ['videoId' => 'vid_b']],
            ],
            'nextPageToken' => null,
        ]),
    ]);

    $service = app(FetchUploadedVideosService::class);
    $page    = $service->fetchLatest($channel);

    expect($page)->toBeInstanceOf(FetchedPlaylistPage::class);
    expect($page->videoIds)->toBe(['vid_a', 'vid_b']);
    expect($page->nextPageToken)->toBeNull();
});

it('uploads_playlist_id が空の場合 fetchLatest は空ページを返す', function (): void {
    $channel = YoutubeChannel::factory()->create(['uploads_playlist_id' => null]);

    $service = app(FetchUploadedVideosService::class);
    $page    = $service->fetchLatest($channel);

    expect($page->videoIds)->toBeEmpty();
});

it('429 レスポンスで YouTubeApiException がスローされる', function (): void {
    $channel = YoutubeChannel::factory()->create(['uploads_playlist_id' => 'UUtest']);

    Http::fake([
        '*/playlistItems*' => Http::response([], 429),
    ]);

    $service = app(FetchUploadedVideosService::class);

    expect(fn () => $service->fetchLatest($channel))->toThrow(YouTubeApiException::class);
});

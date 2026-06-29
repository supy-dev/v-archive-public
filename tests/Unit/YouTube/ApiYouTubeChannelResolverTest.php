<?php

declare(strict_types=1);

use App\Services\YouTube\ApiYouTubeChannelResolver;
use App\Services\YouTube\ChannelInput;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->resolver = app(ApiYouTubeChannelResolver::class);
});

it('channel_id でチャンネルを正常取得する', function (): void {
    Http::fake([
        'googleapis.com/*' => Http::response([
            'items' => [[
                'id' => 'UCtest123',
                'snippet' => [
                    'title'       => 'テストチャンネル',
                    'description' => '説明文',
                    'customUrl'   => '@testchannel',
                    'thumbnails'  => ['medium' => ['url' => 'https://example.com/thumb.jpg']],
                    'publishedAt' => '2020-01-01T00:00:00Z',
                ],
                'contentDetails' => [
                    'relatedPlaylists' => ['uploads' => 'UUtest123'],
                ],
            ]],
        ]),
    ]);

    $input    = new ChannelInput('channel_id', 'UCtest123');
    $resolved = $this->resolver->resolve($input);

    expect($resolved)->not->toBeNull()
        ->and($resolved->youtubeChannelId)->toBe('UCtest123')
        ->and($resolved->title)->toBe('テストチャンネル')
        ->and($resolved->handle)->toBe('testchannel')
        ->and($resolved->uploadsPlaylistId)->toBe('UUtest123');
});

it('チャンネルが見つからない場合は null を返す', function (): void {
    Http::fake([
        'googleapis.com/*' => Http::response(['items' => []]),
    ]);

    $input    = new ChannelInput('handle', 'nonexistent_channel');
    $resolved = $this->resolver->resolve($input);

    expect($resolved)->toBeNull();
});

it('429 エラーは YouTubeApiException をスローする', function (): void {
    Http::fake([
        'googleapis.com/*' => Http::response(['error' => ['message' => 'quota exceeded']], 429),
    ]);

    $input = new ChannelInput('handle', 'test');

    expect(fn () => $this->resolver->resolve($input))
        ->toThrow(\App\Services\YouTube\YouTubeApiException::class);
});

it('500 エラーは YouTubeApiException をスローする', function (): void {
    Http::fake([
        'googleapis.com/*' => Http::response([], 500),
    ]);

    $input = new ChannelInput('channel_id', 'UCtest');

    expect(fn () => $this->resolver->resolve($input))
        ->toThrow(\App\Services\YouTube\YouTubeApiException::class);
});

it('handle の @ プレフィックスを除去して保存する', function (): void {
    Http::fake([
        'googleapis.com/*' => Http::response([
            'items' => [[
                'id' => 'UCtest',
                'snippet' => [
                    'title'       => 'テスト',
                    'description' => '',
                    'customUrl'   => '@suisei_hosimati',
                    'thumbnails'  => [],
                    'publishedAt' => null,
                ],
                'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UUtest']],
            ]],
        ]),
    ]);

    $input    = new ChannelInput('handle', 'suisei_hosimati');
    $resolved = $this->resolver->resolve($input);

    expect($resolved->handle)->toBe('suisei_hosimati');
});

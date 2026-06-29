<?php

declare(strict_types=1);

use App\Enums\LiveStatus;
use App\Enums\VideoType;
use App\Services\YouTube\YoutubeVideoTypeResolver;

it('liveBroadcastContent=upcoming → VideoType::Upcoming', function (): void {
    $item = ['snippet' => ['liveBroadcastContent' => 'upcoming'], 'contentDetails' => ['duration' => 'PT0S'], 'status' => []];
    expect(YoutubeVideoTypeResolver::resolveVideoType($item))->toBe(VideoType::Upcoming);
});

it('liveBroadcastContent=live + P0D → VideoType::Live', function (): void {
    $item = ['snippet' => ['liveBroadcastContent' => 'live'], 'contentDetails' => ['duration' => 'P0D'], 'status' => []];
    expect(YoutubeVideoTypeResolver::resolveVideoType($item))->toBe(VideoType::Live);
});

it('liveStreamingDetails あり + duration > 0 → VideoType::Archive', function (): void {
    $item = [
        'snippet'              => ['liveBroadcastContent' => 'none'],
        'contentDetails'       => ['duration' => 'PT1H'],
        'status'               => [],
        'liveStreamingDetails' => ['actualStartTime' => '2026-01-01T00:00:00Z'],
    ];
    expect(YoutubeVideoTypeResolver::resolveVideoType($item))->toBe(VideoType::Archive);
});

it('通常動画 → VideoType::Video', function (): void {
    $item = ['snippet' => ['liveBroadcastContent' => 'none'], 'contentDetails' => ['duration' => 'PT10M'], 'status' => []];
    expect(YoutubeVideoTypeResolver::resolveVideoType($item))->toBe(VideoType::Video);
});

it('liveStreamingDetails なし → LiveStatus::None', function (): void {
    $item = ['snippet' => ['liveBroadcastContent' => 'none'], 'contentDetails' => [], 'status' => []];
    expect(YoutubeVideoTypeResolver::resolveLiveStatus($item))->toBe(LiveStatus::None);
});

it('liveStreamingDetails あり + actualEndTime → LiveStatus::Completed', function (): void {
    $item = [
        'snippet'              => ['liveBroadcastContent' => 'none'],
        'contentDetails'       => [],
        'status'               => [],
        'liveStreamingDetails' => [
            'actualStartTime' => '2026-01-01T00:00:00Z',
            'actualEndTime'   => '2026-01-01T02:00:00Z',
        ],
    ];
    expect(YoutubeVideoTypeResolver::resolveLiveStatus($item))->toBe(LiveStatus::Completed);
});

it('liveStreamingDetails あり + actualEndTime なし → LiveStatus::Live', function (): void {
    $item = [
        'snippet'              => ['liveBroadcastContent' => 'live'],
        'contentDetails'       => [],
        'status'               => [],
        'liveStreamingDetails' => ['actualStartTime' => '2026-01-01T00:00:00Z'],
    ];
    expect(YoutubeVideoTypeResolver::resolveLiveStatus($item))->toBe(LiveStatus::Live);
});

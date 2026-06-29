<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * YouTube Data API の playlistItems.list を呼び出し、
 * チャンネルのアップロード動画 ID リストを取得するサービス。
 *
 * search.list は使用しない（憲法 V）。
 * APIキーをログに出力しない（憲法 IV）。
 */
class FetchUploadedVideosService
{
    private const MAX_RESULTS = 50;

    public function __construct(
        private readonly string $apiKey = '',
        private readonly string $baseUrl = '',
    ) {}

    /**
     * 最新動画を最大 maxPages × 50 件取得する（初回同期用）。
     */
    public function fetchLatest(YoutubeChannel $channel, int $maxPages = 1): FetchedPlaylistPage
    {
        if (empty($channel->uploads_playlist_id)) {
            return new FetchedPlaylistPage([], null);
        }

        $allVideoIds   = [];
        $pageToken     = null;
        $nextPageToken = null;

        for ($page = 0; $page < $maxPages; $page++) {
            $result = $this->callApi($channel, $pageToken);
            $allVideoIds   = array_merge($allVideoIds, $result->videoIds);
            $nextPageToken = $result->nextPageToken;
            $pageToken     = $nextPageToken;

            if ($nextPageToken === null) {
                break;
            }
        }

        return new FetchedPlaylistPage($allVideoIds, $nextPageToken);
    }

    /**
     * 既存の youtube_video_id に到達するまでページを取得する（定期同期用）。
     *
     * 既存 ID が見つかった時点で取得を打ち切り、新着分のみを返す。
     */
    public function fetchUntilKnown(YoutubeChannel $channel): FetchedPlaylistPage
    {
        if (empty($channel->uploads_playlist_id)) {
            return new FetchedPlaylistPage([], null);
        }

        $allVideoIds = [];
        $pageToken   = null;

        while (true) {
            $result = $this->callApi($channel, $pageToken);

            // 取得した ID のうち DB に存在しないものを収集
            $existingIds = YoutubeVideo::whereIn('youtube_video_id', $result->videoIds)
                ->pluck('youtube_video_id')
                ->all();

            foreach ($result->videoIds as $videoId) {
                if (in_array($videoId, $existingIds, true)) {
                    // 既存 ID に到達 → 新着収集終了
                    return new FetchedPlaylistPage($allVideoIds, null, true);
                }
                $allVideoIds[] = $videoId;
            }

            if ($result->nextPageToken === null) {
                break;
            }
            $pageToken = $result->nextPageToken;
        }

        return new FetchedPlaylistPage($allVideoIds, null, false);
    }

    /**
     * 指定ページトークンから1ページ取得する（過去動画追加取得用）。
     */
    public function fetchPage(YoutubeChannel $channel, ?string $pageToken): FetchedPlaylistPage
    {
        if (empty($channel->uploads_playlist_id)) {
            return new FetchedPlaylistPage([], null);
        }

        return $this->callApi($channel, $pageToken);
    }

    /**
     * playlistItems.list API を呼び出す。
     *
     * 429 / 5xx は YouTubeApiException をthrow。
     * APIキーはログに含めない（憲法 IV）。
     */
    private function callApi(YoutubeChannel $channel, ?string $pageToken): FetchedPlaylistPage
    {
        $params = [
            'part'       => 'contentDetails',
            'playlistId' => $channel->uploads_playlist_id,
            'maxResults' => self::MAX_RESULTS,
            'key'        => $this->resolveApiKey(),
        ];

        if ($pageToken !== null) {
            $params['pageToken'] = $pageToken;
        }

        $response = Http::get($this->resolveBaseUrl() . '/playlistItems', $params);

        if ($response->failed()) {
            Log::warning('playlistItems.list エラー', [
                'channel_id' => $channel->youtube_channel_id,
                'status'     => $response->status(),
                'body'       => $response->body(),
            ]);
            throw new YouTubeApiException(
                "YouTube API エラー (status={$response->status()})",
                $response->status(),
            );
        }

        $data      = $response->json();
        $videoIds  = array_map(
            fn (array $item): string => $item['contentDetails']['videoId'],
            $data['items'] ?? [],
        );

        return new FetchedPlaylistPage(
            videoIds:      $videoIds,
            nextPageToken: $data['nextPageToken'] ?? null,
        );
    }

    private function resolveApiKey(): string
    {
        return $this->apiKey ?: (string) config('youtube.api_key');
    }

    private function resolveBaseUrl(): string
    {
        return $this->baseUrl ?: (string) config('youtube.base_url');
    }
}

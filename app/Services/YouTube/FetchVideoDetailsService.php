<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * YouTube Data API の videos.list を呼び出し、動画詳細を取得するサービス。
 *
 * 最大 50 件をバッチで1callに集約しクォータを節約する（憲法 V / research.md Decision 4）。
 * APIキーをログに出力しない（憲法 IV）。
 */
class FetchVideoDetailsService
{
    private const BATCH_SIZE = 50;

    public function __construct(
        private readonly string $apiKey = '',
        private readonly string $baseUrl = '',
    ) {}

    /**
     * youtube_video_id の配列を最大 50 件バッチで videos.list から取得し、
     * ResolvedVideo の配列として返す。
     *
     * @param  string[]          $youtubeVideoIds
     * @return ResolvedVideo[]
     */
    public function fetchBatch(array $youtubeVideoIds): array
    {
        if (empty($youtubeVideoIds)) {
            return [];
        }

        $results = [];

        // 50 件ずつバッチ処理（YouTube API の上限）
        foreach (array_chunk($youtubeVideoIds, self::BATCH_SIZE) as $chunk) {
            $response = Http::get($this->resolveBaseUrl() . '/videos', [
                'part' => 'snippet,contentDetails,status,liveStreamingDetails',
                'id'   => implode(',', $chunk),
                'key'  => $this->resolveApiKey(),
            ]);

            if ($response->status() === 429 || $response->serverError()) {
                Log::warning('videos.list エラー', [
                    'status'     => $response->status(),
                    'video_ids'  => count($chunk),
                ]);
                throw new YouTubeApiException(
                    "YouTube API エラー (status={$response->status()})",
                    $response->status(),
                );
            }

            foreach ($response->json('items', []) as $item) {
                $results[] = ResolvedVideo::fromApiItem($item);
            }
        }

        return $results;
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

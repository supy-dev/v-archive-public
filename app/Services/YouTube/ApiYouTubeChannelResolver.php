<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * YouTube Data API v3 の channels.list を使ってチャンネルを解決する実装。
 * search.list は使用禁止（憲法 V）。
 * APIキーはサーバ専用・ログに出さない（憲法 IV）。
 */
class ApiYouTubeChannelResolver implements YouTubeChannelResolverInterface
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = (string) config('youtube.base_url', 'https://www.googleapis.com/youtube/v3');
        $this->apiKey  = (string) config('youtube.api_key');
    }

    public function resolve(ChannelInput $input): ?ResolvedChannel
    {
        $param = match ($input->type) {
            'channel_id' => ['id'          => $input->value],
            'handle'     => ['forHandle'   => $input->value],
            'username'   => ['forUsername' => $input->value],
        };

        try {
            $response = Http::get("{$this->baseUrl}/channels", array_merge([
                'part' => 'snippet,contentDetails',
                'key'  => $this->apiKey,
            ], $param));
        } catch (ConnectionException $e) {
            // 接続エラーは内部ログにのみ記録し、ユーザーへは汎用メッセージを返す（憲法 IV）
            Log::error('YouTube API connection error', ['type' => $input->type]);
            throw new YouTubeApiException('YouTube API connection failed', 0, $e);
        }

        if ($response->status() === 429 || $response->serverError()) {
            // クォータ超過・サーバエラー: 内部詳細はログのみ
            Log::warning('YouTube API error', ['status' => $response->status(), 'type' => $input->type]);
            throw new YouTubeApiException("YouTube API returned {$response->status()}");
        }

        if ($response->failed()) {
            Log::warning('YouTube API client error', ['status' => $response->status()]);
            throw new YouTubeApiException("YouTube API client error {$response->status()}");
        }

        $items = $response->json('items', []);

        if (empty($items)) {
            return null;
        }

        return ResolvedChannel::fromApiResponse($items[0]);
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\YoutubeChannel;
use App\Services\YouTube\FetchUploadedVideosService;
use App\Services\YouTube\FetchVideoDetailsService;
use App\Services\YouTube\SyncChannelVideosService;
use App\Services\YouTube\YouTubeApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 過去動画を1ページ分追加取得する Job。
 *
 * oldest_page_token を使い、チャンネルの過去方向へページを遡る。
 * ユーザーが「もっと見る」を押した際に ChannelSyncController から dispatch する。
 * ShouldBeUnique により同一チャンネルの並列実行を防止する。
 */
class FetchOlderYoutubeVideosJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** 最大リトライ回数 */
    public int $tries = 3;

    /** リトライ間隔（秒） */
    public array $backoff = [60, 120, 240];

    public function __construct(
        public readonly YoutubeChannel $youtubeChannel,
    ) {}

    /**
     * 同一チャンネルの重複 Job を排除するキー。
     */
    public function uniqueId(): string
    {
        return $this->youtubeChannel->id;
    }

    /**
     * oldest_page_token を使い過去動画1ページを取得・upsert する。
     *
     * 1. oldest_page_token を取得（null = 既に全件取得済み）
     * 2. fetchPage() で1ページ分の video ID を取得
     * 3. videos.list で詳細取得 → upsert
     * 4. oldest_page_token / oldest_fetched_at を更新
     */
    public function handle(
        FetchUploadedVideosService $fetchUploaded,
        FetchVideoDetailsService $fetchDetails,
        SyncChannelVideosService $sync,
    ): void {
        $channel = $this->youtubeChannel;

        try {
            $pageToken = $channel->oldest_page_token;
            $page      = $fetchUploaded->fetchPage($channel, $pageToken);

            if (!empty($page->videoIds)) {
                $resolved = $fetchDetails->fetchBatch($page->videoIds);
                $sync->upsert($channel, $resolved);
            }

            $channel->update([
                'oldest_page_token'  => $page->nextPageToken,
                'oldest_fetched_at'  => now(),
                'is_fetching_older'  => false,
            ]);

            Log::info('過去動画取得完了', [
                'channel_id'       => $channel->youtube_channel_id,
                'count'            => count($page->videoIds),
                'next_page_token'  => $page->nextPageToken,
            ]);
        } catch (YouTubeApiException $e) {
            if ($e->getCode() >= 400 && $e->getCode() < 500 && $e->getCode() !== 429) {
                $this->fail($e);

                return;
            }

            Log::warning('過去動画取得エラー（リトライ予定）', [
                'channel_id' => $channel->youtube_channel_id,
                'status'     => $e->getCode(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->youtubeChannel->update(['is_fetching_older' => false]);

        Log::error('過去動画取得 全リトライ失敗', [
            'channel_id' => $this->youtubeChannel->youtube_channel_id,
        ]);
    }
}

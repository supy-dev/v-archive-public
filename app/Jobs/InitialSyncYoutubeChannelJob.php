<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ChannelSyncStatus;
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
 * チャンネル登録後の初回動画同期Job。
 *
 * 最新 50 件を取得して youtube_videos へ upsert する。
 * ShouldBeUnique により同一チャンネルの重複 Job を防止する。
 * 登録画面をブロックせず非同期で実行する（FR-005）。
 */
class InitialSyncYoutubeChannelJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** 最大リトライ回数（429/5xx 用） */
    public int $tries = 3;

    /** リトライ間隔（秒）: exponential backoff */
    public array $backoff = [60, 120, 240];

    public function __construct(
        public readonly YoutubeChannel $youtubeChannel,
    ) {}

    /**
     * 同一チャンネルの重複 Job をキューから排除するキー。
     */
    public function uniqueId(): string
    {
        return $this->youtubeChannel->id;
    }

    /**
     * 初回同期を実行する。
     *
     * 1. uploads playlist から最新 50 件の video ID を取得
     * 2. videos.list で動画詳細をバッチ取得
     * 3. youtube_videos へ upsert
     * 4. sync_status を更新
     */
    public function handle(
        FetchUploadedVideosService $fetchUploaded,
        FetchVideoDetailsService $fetchDetails,
        SyncChannelVideosService $sync,
    ): void {
        $channel = $this->youtubeChannel;

        try {
            // playlistItems.list で最新 50 件取得
            $page     = $fetchUploaded->fetchLatest($channel, maxPages: 1);
            $videoIds = $page->videoIds;

            if (!empty($videoIds)) {
                // videos.list でバッチ詳細取得 → upsert
                $resolved = $fetchDetails->fetchBatch($videoIds);
                $sync->upsert($channel, $resolved);
            }

            // 同期完了を記録。oldest_page_token を保存して過去動画の続き取得を可能にする
            $channel->update([
                'sync_status'        => ChannelSyncStatus::Synced->value,
                'last_synced_at'     => now(),
                'oldest_page_token'  => $page->nextPageToken,
                'sync_error_message' => null,
            ]);

            Log::info('初回同期完了', [
                'channel_id' => $channel->youtube_channel_id,
                'count'      => count($videoIds),
            ]);
        } catch (YouTubeApiException $e) {
            // 4xx（リトライ不要なクライアントエラー）は即座に error 記録
            if ($e->getCode() >= 400 && $e->getCode() < 500 && $e->getCode() !== 429) {
                $channel->update([
                    'sync_status'        => ChannelSyncStatus::Error->value,
                    'sync_error_message' => $e->getMessage(),
                ]);
                $this->fail($e);

                return;
            }

            // 429 / 5xx はリトライに委ねる
            Log::warning('初回同期エラー（リトライ予定）', [
                'channel_id' => $channel->youtube_channel_id,
                'status'     => $e->getCode(),
            ]);

            throw $e;
        }
    }

    /**
     * 全リトライ失敗時の処理。
     */
    public function failed(\Throwable $e): void
    {
        $this->youtubeChannel->update([
            'sync_status'        => ChannelSyncStatus::Error->value,
            'sync_error_message' => $e->getMessage(),
        ]);

        Log::error('初回同期 全リトライ失敗', [
            'channel_id' => $this->youtubeChannel->youtube_channel_id,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ChannelSyncStatus;
use App\Enums\LiveStatus;
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
 * 定期同期 Job。
 *
 * 30 分毎に DispatchVideoSyncsCommand が発行する。
 * fetchUntilKnown() で既存 ID に到達した時点で取得を打ち切り、
 * 新着分のみ upsert する。
 * ライブ中の動画は RefreshYoutubeVideoDetailsJob を別途発行して詳細を更新する。
 */
class SyncYoutubeChannelJob implements ShouldQueue, ShouldBeUnique
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
     * 定期同期を実行する。
     *
     * 1. fetchUntilKnown() で新着 video ID を収集
     * 2. videos.list で詳細取得 → upsert
     * 3. ライブ中動画に RefreshJob を dispatch
     * 4. last_synced_at / sync_status を更新
     */
    public function handle(
        FetchUploadedVideosService $fetchUploaded,
        FetchVideoDetailsService $fetchDetails,
        SyncChannelVideosService $sync,
    ): void {
        $channel = $this->youtubeChannel;

        try {
            $page     = $fetchUploaded->fetchUntilKnown($channel);
            $videoIds = $page->videoIds;

            if (!empty($videoIds)) {
                $resolved = $fetchDetails->fetchBatch($videoIds);
                $sync->upsert($channel, $resolved);
            }

            // ライブ中の動画を再取得して実際の終了時刻・live_status を更新
            $channel->youtubeVideos()
                ->where('live_status', LiveStatus::Live->value)
                ->each(function ($video): void {
                    RefreshYoutubeVideoDetailsJob::dispatch($video);
                });

            $channel->update([
                'sync_status'        => ChannelSyncStatus::Synced->value,
                'last_synced_at'     => now(),
                'sync_error_message' => null,
            ]);

            Log::info('定期同期完了', [
                'channel_id' => $channel->youtube_channel_id,
                'new_count'  => count($videoIds),
            ]);
        } catch (YouTubeApiException $e) {
            if ($e->getCode() >= 400 && $e->getCode() < 500 && $e->getCode() !== 429) {
                $channel->update([
                    'sync_status'        => ChannelSyncStatus::Error->value,
                    'sync_error_message' => $e->getMessage(),
                ]);
                $this->fail($e);

                return;
            }

            Log::warning('定期同期エラー（リトライ予定）', [
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

        Log::error('定期同期 全リトライ失敗', [
            'channel_id' => $this->youtubeChannel->youtube_channel_id,
        ]);
    }
}

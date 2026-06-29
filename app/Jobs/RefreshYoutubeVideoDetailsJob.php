<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\YoutubeVideo;
use App\Services\YouTube\FetchVideoDetailsService;
use App\Services\YouTube\YouTubeApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ライブ中動画の詳細を再取得する Job。
 *
 * SyncYoutubeChannelJob が live_status=live の動画を検出した際に dispatch する。
 * videos.list で 1 件取得し、live_status / duration_seconds / actual_end_at / video_type を更新する。
 */
class RefreshYoutubeVideoDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** 最大リトライ回数 */
    public int $tries = 3;

    /** リトライ間隔（秒） */
    public array $backoff = [60, 120, 240];

    public function __construct(
        public readonly YoutubeVideo $video,
    ) {}

    /**
     * 動画詳細を再取得して更新する。
     */
    public function handle(FetchVideoDetailsService $fetchDetails): void
    {
        try {
            $resolved = $fetchDetails->fetchBatch([$this->video->youtube_video_id]);

            if (empty($resolved)) {
                // API が返さない場合は利用不可としてマーク
                $this->video->update(['is_available' => false]);

                return;
            }

            $detail = $resolved[0];

            $this->video->update([
                'live_status'      => $detail->liveStatus,
                'video_type'       => $detail->videoType,
                'duration_seconds' => $detail->durationSeconds,
                'actual_end_at'    => $detail->actualEndAt,
                'last_fetched_at'  => now(),
            ]);

            Log::info('動画詳細更新完了', [
                'youtube_video_id' => $this->video->youtube_video_id,
                'live_status'      => $detail->liveStatus,
            ]);
        } catch (YouTubeApiException $e) {
            if ($e->getCode() >= 400 && $e->getCode() < 500 && $e->getCode() !== 429) {
                $this->fail($e);

                return;
            }

            Log::warning('動画詳細更新エラー（リトライ予定）', [
                'youtube_video_id' => $this->video->youtube_video_id,
                'status'           => $e->getCode(),
            ]);

            throw $e;
        }
    }
}

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
 * 削除・非公開動画を is_available=false にマークする Job。
 *
 * MarkUnavailableVideosCommand が 50 件ずつ chunk して dispatch する。
 * API が返さない ID または privacyStatus != public のレコードを更新する。
 */
class MarkUnavailableYoutubeVideosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** 最大リトライ回数 */
    public int $tries = 3;

    /** リトライ間隔（秒） */
    public array $backoff = [60, 120, 240];

    /**
     * @param string[] $youtubeVideoIds チェック対象の youtube_video_id（最大50件）
     */
    public function __construct(
        public readonly array $youtubeVideoIds,
    ) {}

    /**
     * videos.list で対象 ID を確認し、利用不可な動画を更新する。
     *
     * APIが返さなかった ID → is_available=false
     * privacyStatus が public 以外 → is_available=false
     */
    public function handle(FetchVideoDetailsService $fetchDetails): void
    {
        if (empty($this->youtubeVideoIds)) {
            return;
        }

        try {
            $resolved = $fetchDetails->fetchBatch($this->youtubeVideoIds);

            // API が返した ID のうち public なものだけを利用可能とみなす
            $availableIds = array_map(
                fn ($v) => $v->youtubeVideoId,
                array_filter($resolved, fn ($v) => $v->privacyStatus === 'public'),
            );

            // 返されなかった ID、または非公開の ID を利用不可にマーク
            $unavailableIds = array_values(array_diff($this->youtubeVideoIds, $availableIds));

            if (!empty($unavailableIds)) {
                YoutubeVideo::whereIn('youtube_video_id', $unavailableIds)
                    ->update(['is_available' => false]);

                Log::info('動画利用不可マーク完了', ['count' => count($unavailableIds)]);
            }
        } catch (YouTubeApiException $e) {
            if ($e->getCode() >= 400 && $e->getCode() < 500 && $e->getCode() !== 429) {
                $this->fail($e);

                return;
            }

            Log::warning('利用不可マークエラー（リトライ予定）', ['status' => $e->getCode()]);

            throw $e;
        }
    }
}

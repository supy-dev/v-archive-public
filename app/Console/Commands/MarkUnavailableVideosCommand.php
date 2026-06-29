<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\MarkUnavailableYoutubeVideosJob;
use App\Models\YoutubeVideo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * is_available=true の動画を 50 件ずつ chunk して
 * MarkUnavailableYoutubeVideosJob を dispatch する深夜バッチコマンド。
 */
class MarkUnavailableVideosCommand extends Command
{
    protected $signature = 'youtube:mark-unavailable';

    protected $description = '削除・非公開動画の is_available フラグを更新する';

    public function handle(): int
    {
        $dispatched = 0;

        YoutubeVideo::where('is_available', true)
            ->select('id', 'youtube_video_id')
            ->chunkById(50, function ($videos) use (&$dispatched): void {
                $ids = $videos->pluck('youtube_video_id')->all();
                MarkUnavailableYoutubeVideosJob::dispatch($ids);
                $dispatched++;
            }, 'id');

        Log::info('利用不可チェック Job dispatch 完了', ['chunks' => $dispatched]);
        $this->line("dispatched: {$dispatched} chunks");

        return self::SUCCESS;
    }
}

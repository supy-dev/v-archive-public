<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncYoutubeChannelJob;
use App\Models\YoutubeChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * sync_enabled=true のチャンネルに SyncYoutubeChannelJob を dispatch する。
 *
 * Scheduler から 30 分毎に呼び出される。
 * APIキーをログに含めない（憲法 IV）。
 */
class DispatchVideoSyncsCommand extends Command
{
    protected $signature = 'youtube:dispatch-syncs';

    protected $description = '同期有効チャンネルの定期動画同期 Job を dispatch する';

    public function handle(): int
    {
        // sync_enabled=true の user_channels に紐づく youtube_channels を抽出
        $channels = YoutubeChannel::whereHas('userChannels', function ($q): void {
            $q->where('sync_enabled', true);
        })->get();

        $count = 0;
        foreach ($channels as $channel) {
            SyncYoutubeChannelJob::dispatch($channel);
            $count++;
        }

        Log::info('定期同期 Job dispatch 完了', ['dispatched' => $count]);
        $this->line("dispatched: {$count} channels");

        return self::SUCCESS;
    }
}

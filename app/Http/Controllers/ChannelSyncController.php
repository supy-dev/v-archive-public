<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\FetchOlderYoutubeVideosJob;
use App\Models\Oshi;
use App\Models\UserChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * 手動同期操作のコントローラー。
 *
 * 憲法 I: Controller は検証 → Action/Job 呼び出し → Response のみ。
 * ユーザー識別はサーバー側の Auth::user() から取得し、リクエスト値は信用しない（憲法 III）。
 */
class ChannelSyncController extends Controller
{
    /**
     * 過去動画の追加取得 Job を dispatch する。
     *
     * oldest_page_token が null の場合は全件取得済みのためスキップ。
     */
    public function fetchOlder(Oshi $oshi, UserChannel $userChannel): RedirectResponse
    {
        $this->authorize('update', $userChannel);

        if ($userChannel->youtubeChannel->oldest_page_token === null
            && $userChannel->youtubeChannel->oldest_fetched_at !== null
        ) {
            return redirect()->route('oshis.show', $oshi)
                ->with('info', 'すべての動画を取得済みです。');
        }

        $userChannel->youtubeChannel->update(['is_fetching_older' => true]);

        FetchOlderYoutubeVideosJob::dispatch($userChannel->youtubeChannel);

        return redirect()->route('oshis.show', $oshi)
            ->with('success', '過去の動画を取得中です。しばらくお待ちください。');
    }
}

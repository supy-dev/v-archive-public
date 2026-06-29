<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\WatchStatus;
use App\Models\Profile;
use App\Models\TimestampMemo;
use App\Models\UserChannel;
use App\Models\UserWatchItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ホーム画面コントローラー。
 * 憲法 I: データ取得は最小限のクエリに集約し、Controller は View へ渡すのみ。
 */
class HomeController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $homeStats = $this->buildHomeStats($profile->id);
        $registeredChannelCount = UserChannel::where('profile_id', $profile->id)->count();
        $registeredChannels = UserChannel::query()
            ->with('youtubeChannel')
            ->where('profile_id', $profile->id)
            ->latest('registered_at')
            ->limit(5)
            ->get();
        $homeWatchItems = UserWatchItem::query()
            ->join('youtube_videos as yv', 'yv.id', '=', 'user_watch_items.youtube_video_id')
            ->join('youtube_channels as yc', 'yc.id', '=', 'yv.youtube_channel_id')
            ->leftJoin('user_channels as uc', function ($join) use ($profile): void {
                $join->on('uc.youtube_channel_id', '=', 'yc.id')
                    ->where('uc.profile_id', $profile->id);
            })
            ->leftJoin('oshis as o', 'o.id', '=', 'uc.oshi_id')
            ->where('user_watch_items.profile_id', $profile->id)
            ->where('user_watch_items.status', WatchStatus::WantToWatch->value)
            ->select([
                'user_watch_items.id',
                'yv.title as video_title',
                'yv.thumbnail_url as video_thumbnail_url',
                'yv.duration_seconds as video_duration_seconds',
                'o.color_id as oshi_color_id',
            ])
            ->orderByDesc('user_watch_items.added_at')
            ->limit(4)
            ->get();

        // 最近のタイムスタンプメモ（Feature 007 US3）
        $recentMemos = TimestampMemo::with('youtubeVideo')
            ->where('profile_id', $profile->id)
            ->latest()
            ->limit(3)
            ->get();

        // 最近追加されたアーカイブ（未整理・最新4件）
        $recentArchives = DB::table('youtube_videos as yv')
            ->join('youtube_channels as yc', 'yc.id', '=', 'yv.youtube_channel_id')
            ->join('user_channels as uc', function ($join) use ($profile): void {
                $join->on('uc.youtube_channel_id', '=', 'yc.id')
                    ->where('uc.profile_id', $profile->id);
            })
            ->join('oshis as o', 'o.id', '=', 'uc.oshi_id')
            ->leftJoin('user_watch_items as uwi', function ($join) use ($profile): void {
                $join->on('uwi.youtube_video_id', '=', 'yv.id')
                    ->where('uwi.profile_id', $profile->id);
            })
            ->whereNull('uwi.id')
            ->where('yv.is_available', true)
            ->select([
                'yv.id',
                'yv.title',
                'yv.thumbnail_url',
                'yv.published_at',
                'yv.duration_seconds',
                'yv.video_type',
                'o.name as oshi_name',
                'o.color_id as oshi_color_id',
            ])
            ->orderByDesc('yv.published_at')
            ->limit(4)
            ->get();

        return view('home', [
            'profile' => $profile,
            'homeStats' => $homeStats,
            'homeWatchItems' => $homeWatchItems,
            'registeredChannelCount' => $registeredChannelCount,
            'registeredChannels' => $registeredChannels,
            'recentMemos' => $recentMemos,
            'recentArchives' => $recentArchives,
        ]);
    }

    /**
     * ホームサマリー件数を 2 クエリで算出する（FR-010）。
     *
     * 未整理件数 = ユーザーの登録チャンネルの is_available=true 動画数 - ユーザーの user_watch_items 総数。
     * ステータス別件数は GROUP BY 1 クエリで集計する（N+1 なし）。
     *
     * @return array<string, int>
     */
    private function buildHomeStats(string $profileId): array
    {
        // ユーザーの登録チャンネルに紐づく利用可能な動画の総数
        $totalAvailableVideos = DB::table('youtube_videos as yv')
            ->join('youtube_channels as yc', 'yc.id', '=', 'yv.youtube_channel_id')
            ->join('user_channels as uc', function ($join) use ($profileId): void {
                $join->on('uc.youtube_channel_id', '=', 'yc.id')
                    ->where('uc.profile_id', $profileId);
            })
            ->where('yv.is_available', true)
            ->count();

        // ユーザーの user_watch_items 件数（全ステータス）
        $watchItemTotal = UserWatchItem::where('profile_id', $profileId)->count();

        // ステータス別件数（1 クエリ）
        $statusCounts = UserWatchItem::where('profile_id', $profileId)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        return [
            // 未整理件数（全登録チャンネル・全期間）
            'unorganized' => max(0, $totalAvailableVideos - $watchItemTotal),
            'want_to_watch' => (int) ($statusCounts[WatchStatus::WantToWatch->value] ?? 0),
            'watching' => (int) ($statusCounts[WatchStatus::Watching->value] ?? 0),
            'watched' => (int) ($statusCounts[WatchStatus::Watched->value] ?? 0),
        ];
    }
}

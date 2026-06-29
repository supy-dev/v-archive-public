<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Oshi;
use App\Models\Profile;
use App\Models\Tag;
use App\Models\TimestampMemo;
use App\Models\UserWatchItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * タイムスタンプメモ保管庫コントローラー（Feature 007 US3）。
 * 全タイムスタンプメモを★問わず一覧表示し、推し/タグ/年月でフィルタリングする。
 * 憲法 I: 入力受け取り → クエリ構築 → View 返却のみ。
 */
class MemoController extends Controller
{
    /**
     * ログインユーザーの全タイムスタンプメモ一覧を表示する。
     * GET /memos?oshi_id=&tag_id=&month=YYYY-MM → memos/index.blade.php
     */
    public function index(Request $request): View
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $oshiId = $request->query('oshi_id');
        $tagId = $request->query('tag_id');
        $month = $request->query('month');

        // 全メモを N+1 なしで取得（is_favorite 問わず）
        $query = TimestampMemo::with([
            'tags',
            'youtubeVideo.youtubeChannel',
            'youtubeVideo.youtubeChannel.userChannels' => fn ($q) => $q
                ->where('profile_id', $profile->id)
                ->with('oshi'),
        ])
            ->where('profile_id', $profile->id);

        // 推し別フィルタ
        if ($oshiId) {
            $query->whereHas('youtubeVideo.youtubeChannel.userChannels', function ($q) use ($profile, $oshiId): void {
                $q->where('profile_id', $profile->id)
                    ->where('oshi_id', $oshiId);
            });
        }

        // タグ別フィルタ
        if ($tagId) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
        }

        // 年月別フィルタ（YYYY-MM 形式）
        if ($month && preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
            [$year, $mon] = explode('-', (string) $month);
            $query->whereYear('timestamp_memos.created_at', $year)
                ->whereMonth('timestamp_memos.created_at', $mon);
        }

        $memos = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        // アーカイブ詳細リンク生成用: youtube_video_id → user_watch_items.id のマップ
        $watchItemMap = UserWatchItem::where('profile_id', $profile->id)
            ->whereIn('youtube_video_id', $memos->pluck('youtube_video_id')->unique())
            ->pluck('id', 'youtube_video_id');

        // フィルタ選択肢
        $oshis = Oshi::where('profile_id', $profile->id)->orderBy('name')->get();

        $tags = Tag::where(function ($q) use ($profile): void {
            $q->where('is_system', true)
                ->orWhere('profile_id', $profile->id);
        })->orderBy('name')->get();

        // 年月選択肢
        $monthExpr = DB::getDriverName() === 'pgsql'
            ? "TO_CHAR(created_at, 'YYYY-MM')"
            : "strftime('%Y-%m', created_at)";

        $months = TimestampMemo::where('profile_id', $profile->id)
            ->selectRaw("{$monthExpr} as month")
            ->groupBy('month')
            ->orderByDesc('month')
            ->pluck('month');

        return view('memos.index', [
            'memos' => $memos,
            'watchItemMap' => $watchItemMap,
            'oshis' => $oshis,
            'tags' => $tags,
            'months' => $months,
            'filters' => compact('oshiId', 'tagId', 'month'),
        ]);
    }
}

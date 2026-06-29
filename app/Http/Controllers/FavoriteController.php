<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Oshi;
use App\Models\Profile;
use App\Models\Tag;
use App\Models\TimestampMemo;
use App\Models\UserWatchItem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 神回・お気に入り一覧コントローラー（FR-011 / FR-012 / Feature 007 US2）。
 * ?tab=kamikai（デフォルト）: 神回動画一覧
 * ?tab=memos: お気に入りタイムスタンプメモ一覧
 * 憲法 I: 入力受け取り → クエリ構築 → View 返却のみ。
 */
class FavoriteController extends Controller
{
    /**
     * 神回タブまたはお気に入りメモタブを表示する。
     * GET /favorites?tab=kamikai|memos → favorites/index.blade.php
     */
    public function index(Request $request): View
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $tab = $request->query('tab', 'kamikai');
        $oshiId = $request->query('oshi_id');
        $tagId = $request->query('tag_id');
        $month = $request->query('month');

        // 推し選択肢（共通）
        $oshis = Oshi::where('profile_id', $profile->id)->orderBy('name')->get();

        if ($tab === 'memos') {
            return $this->memoTab($profile, $oshiId, $tagId, $month, $oshis);
        }

        return $this->kaimaiTab($profile, $oshiId, $month, $oshis);
    }

    /**
     * 神回タブ: is_favorite=true の UserWatchItem を表示する。
     */
    private function kaimaiTab(
        Profile $profile,
        ?string $oshiId,
        ?string $month,
        Collection $oshis,
    ): View {
        $query = UserWatchItem::with([
            'youtubeVideo.youtubeChannel',
            'youtubeVideo.youtubeChannel.userChannels' => fn ($q) => $q
                ->where('profile_id', $profile->id)
                ->with('oshi'),
        ])
            ->where('profile_id', $profile->id)
            ->where('is_favorite', true);

        // 推し別フィルタ（oshi_id → user_channels → youtube_channel_id）
        if ($oshiId) {
            $query->whereHas('youtubeVideo.youtubeChannel.userChannels', function ($q) use ($profile, $oshiId): void {
                $q->where('profile_id', $profile->id)
                    ->where('oshi_id', $oshiId);
            });
        }

        // 年月別フィルタ（user_watch_items.updated_at 基準）
        if ($month && preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
            [$year, $mon] = explode('-', (string) $month);
            $query->whereYear('user_watch_items.updated_at', $year)
                ->whereMonth('user_watch_items.updated_at', $mon);
        }

        $kamikaiItems = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();

        // 年月選択肢（神回登録済みアイテムの月一覧）
        $monthExpr = DB::getDriverName() === 'pgsql'
            ? "TO_CHAR(updated_at, 'YYYY-MM')"
            : "strftime('%Y-%m', updated_at)";

        $months = UserWatchItem::where('profile_id', $profile->id)
            ->where('is_favorite', true)
            ->selectRaw("{$monthExpr} as month")
            ->groupBy('month')
            ->orderByDesc('month')
            ->pluck('month');

        return view('favorites.index', [
            'tab' => 'kamikai',
            'kamikaiItems' => $kamikaiItems,
            'oshis' => $oshis,
            'months' => $months,
            'filters' => compact('oshiId', 'month'),
        ]);
    }

    /**
     * お気に入りメモタブ: is_favorite=true の TimestampMemo を表示する。
     */
    private function memoTab(
        Profile $profile,
        ?string $oshiId,
        ?string $tagId,
        ?string $month,
        Collection $oshis,
    ): View {
        $query = TimestampMemo::with([
            'tags',
            'youtubeVideo.youtubeChannel',
            'youtubeVideo.youtubeChannel.userChannels' => fn ($q) => $q
                ->where('profile_id', $profile->id)
                ->with('oshi'),
        ])
            ->where('profile_id', $profile->id)
            ->where('is_favorite', true);

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

        // 年月別フィルタ
        if ($month && preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
            [$year, $mon] = explode('-', (string) $month);
            $query->whereYear('timestamp_memos.created_at', $year)
                ->whereMonth('timestamp_memos.created_at', $mon);
        }

        $favorites = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $watchItemMap = UserWatchItem::where('profile_id', $profile->id)
            ->whereIn('youtube_video_id', $favorites->pluck('youtube_video_id')->unique())
            ->pluck('id', 'youtube_video_id');

        $tags = Tag::where(function ($q) use ($profile): void {
            $q->where('is_system', true)
                ->orWhere('profile_id', $profile->id);
        })->orderBy('name')->get();

        // 年月選択肢
        $monthExpr = DB::getDriverName() === 'pgsql'
            ? "TO_CHAR(created_at, 'YYYY-MM')"
            : "strftime('%Y-%m', created_at)";

        $months = TimestampMemo::where('profile_id', $profile->id)
            ->where('is_favorite', true)
            ->selectRaw("{$monthExpr} as month")
            ->groupBy('month')
            ->orderByDesc('month')
            ->pluck('month');

        return view('favorites.index', [
            'tab' => 'memos',
            'favorites' => $favorites,
            'watchItemMap' => $watchItemMap,
            'oshis' => $oshis,
            'tags' => $tags,
            'months' => $months,
            'filters' => compact('oshiId', 'tagId', 'month'),
        ]);
    }
}

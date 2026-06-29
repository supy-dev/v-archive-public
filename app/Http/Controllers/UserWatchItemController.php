<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\WatchItem\AddToWatchListAction;
use App\Actions\WatchItem\DeleteWatchItemAction;
use App\Actions\WatchItem\UpdateWatchStatusAction;
use App\Enums\WatchStatus;
use App\Http\Requests\StoreUserWatchItemRequest;
use App\Http\Requests\UpdateWatchStatusRequest;
use App\Models\UserWatchItem;
use App\Models\YoutubeVideo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 見るリスト（UserWatchItem）の CRUD コントローラー。
 * 憲法 I: 入力受け取り → Policy 確認 → Action 呼び出し → Redirect/View のみ。
 */
class UserWatchItemController extends Controller
{
    /**
     * 見るリスト一覧（タブ切替）を表示する。
     *
     * クエリパラメータ ?status= でタブを切り替える（サーバーサイドレンダリング）。
     * デフォルトタブは未視聴（want_to_watch）。
     */
    public function index(Request $request): View
    {
        /** @var \App\Models\Profile $profile */
        $profile = $request->user();

        // タブ選択（不正な値はデフォルトに戻す）
        $statusValue   = $request->query('status', WatchStatus::WantToWatch->value);
        $currentStatus = WatchStatus::tryFrom((string) $statusValue) ?? WatchStatus::WantToWatch;

        // 選択タブのページネーション（JOIN で動画・チャンネル・推し情報を 1 クエリ取得）
        $watchItems = UserWatchItem::query()
            ->join('youtube_videos as yv', 'yv.id', '=', 'user_watch_items.youtube_video_id')
            ->join('youtube_channels as yc', 'yc.id', '=', 'yv.youtube_channel_id')
            ->leftJoin('user_channels as uc', function ($join) use ($profile): void {
                $join->on('uc.youtube_channel_id', '=', 'yc.id')
                     ->where('uc.profile_id', '=', $profile->id);
            })
            ->leftJoin('oshis as o', 'o.id', '=', 'uc.oshi_id')
            ->where('user_watch_items.profile_id', $profile->id)
            ->where('user_watch_items.status', $currentStatus->value)
            ->select([
                'user_watch_items.*',
                'yv.title as video_title',
                'yv.thumbnail_url as video_thumbnail_url',
                'yv.published_at as video_published_at',
                'yv.duration_seconds as video_duration_seconds',
                'yv.video_type as video_type_value',
                'yc.title as channel_title',
                'o.name as oshi_name',
                'o.color_id as oshi_color_id',
            ])
            ->orderByDesc('user_watch_items.added_at')
            ->paginate(20)
            ->withQueryString();

        // タブカウント（4 ステータス分を 1 クエリで集計）
        $counts = UserWatchItem::where('profile_id', $profile->id)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $tabCounts = [
            WatchStatus::WantToWatch->value => (int) ($counts[WatchStatus::WantToWatch->value] ?? 0),
            WatchStatus::Watching->value    => (int) ($counts[WatchStatus::Watching->value]    ?? 0),
            WatchStatus::Watched->value     => (int) ($counts[WatchStatus::Watched->value]     ?? 0),
            WatchStatus::Skipped->value     => (int) ($counts[WatchStatus::Skipped->value]     ?? 0),
        ];

        return view('watchlist.index', [
            'watchItems'    => $watchItems,
            'currentStatus' => $currentStatus,
            'tabCounts'     => $tabCounts,
            'profile'       => $profile,
        ]);
    }

    /**
     * 動画を見るリストに追加 / 見送り登録する。
     *
     * Policy create: 動画のチャンネルがユーザーの登録チャンネルに含まれるか（FR-009）。
     * updateOrCreate で重複作成を防ぐ（FR-007）。
     */
    public function store(
        StoreUserWatchItemRequest $request,
        YoutubeVideo $video,
        AddToWatchListAction $action,
    ): RedirectResponse {
        $this->authorize('create', [UserWatchItem::class, $video]);

        /** @var \App\Models\Profile $profile */
        $profile = Auth::user();
        $status  = WatchStatus::from($request->validated('status'));

        $action->execute($profile, $video, $status);

        return redirect()->back()->with(
            'success',
            $status === WatchStatus::WantToWatch ? '見るリストに追加しました。' : '見送りに設定しました。',
        );
    }

    /**
     * 視聴ステータスを変更する。
     *
     * Policy update: 自身の視聴アイテムのみ（FR-009）。
     * タイムスタンプは UpdateWatchStatusAction で自動設定（FR-008）。
     */
    public function update(
        UpdateWatchStatusRequest $request,
        UserWatchItem $userWatchItem,
        UpdateWatchStatusAction $action,
    ): RedirectResponse {
        $this->authorize('update', $userWatchItem);

        $validated  = $request->validated();
        $newStatus  = WatchStatus::from($validated['status']);
        $action->execute($userWatchItem, $newStatus);

        // redirect_to が指定されていれば同一ホストのみ許可（open redirect 対策）
        $redirectTo   = $validated['redirect_to'] ?? null;
        $redirectHost = $redirectTo ? parse_url($redirectTo, PHP_URL_HOST) : null;
        if ($redirectTo && ($redirectHost === null || $redirectHost === $request->getHost())) {
            return redirect()->to($redirectTo)->with('success', 'ステータスを更新しました。');
        }

        return redirect()->back()->with('success', 'ステータスを更新しました。');
    }

    /**
     * 視聴アイテムを削除する（未整理に戻す）。
     *
     * Policy delete: 自身の視聴アイテムのみ（FR-009）。
     * 削除後、動画は新着アーカイブ一覧に再表示される（FR-015）。
     */
    public function destroy(
        UserWatchItem $userWatchItem,
        DeleteWatchItemAction $action,
    ): RedirectResponse {
        $this->authorize('delete', $userWatchItem);
        $action->execute($userWatchItem);

        return redirect()->route('watchlist.index')->with('success', 'アイテムを削除しました。');
    }
}

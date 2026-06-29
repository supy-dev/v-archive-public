<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\VideoType;
use App\Models\Oshi;
use App\Models\Profile;
use App\Models\Tag;
use App\Models\TimestampMemo;
use App\Models\UserWatchItem;
use App\Models\VideoNote;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * アーカイブ（新着一覧・配信詳細）コントローラー。
 * 憲法 I: 入力受け取り → Policy 確認 → View 返却のみ。
 */
class ArchiveController extends Controller
{
    /**
     * 新着アーカイブ一覧（未整理動画）を表示する。
     *
     * - ユーザーの登録チャンネルの動画のみ表示（user_channels JOIN）
     * - user_watch_items が存在しない動画のみ（未整理、FR-002）
     * - is_available=true の動画のみ（FR-011）
     * - published_at 降順（FR-001）
     * - paginate(20)（FR-012）
     * - N+1 防止（憲法 技術制約）
     */
    public function index(Request $request): View
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $oshiId = $request->query('oshi_id');
        $videoType = $request->query('video_type');
        $query = trim((string) $request->query('q', ''));

        $videos = DB::table('youtube_videos as yv')
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
            ->whereNull('uwi.id')        // 未整理のみ（FR-002）
            ->where('yv.is_available', true)  // 利用可能な動画のみ（FR-011）
            ->select([
                'yv.id',
                'yv.title',
                'yv.thumbnail_url',
                'yv.published_at',
                'yv.duration_seconds',
                'yv.video_type',
                'o.name as oshi_name',
                'o.color_id as oshi_color',
            ])
            ->when($oshiId, fn ($q) => $q->where('uc.oshi_id', $oshiId))
            ->when($videoType, fn ($q) => $q->where('yv.video_type', $videoType))
            ->when($query !== '', function ($q) use ($query): void {
                $term = '%'.mb_strtolower($query).'%';
                $q->where(function ($nested) use ($term): void {
                    $nested->whereRaw('LOWER(yv.title) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(o.name) LIKE ?', [$term]);
                });
            })
            ->orderByDesc('yv.published_at')
            ->paginate(20)
            ->withQueryString();

        // フィルタ用推し一覧（ユーザーの登録推しのみ）
        $oshis = Oshi::where('profile_id', $profile->id)
            ->orderBy('name')
            ->get();

        // 動画種別フィルタ選択肢
        $videoTypes = collect(VideoType::cases())
            ->reject(fn ($vt) => $vt === VideoType::Unknown)
            ->mapWithKeys(fn ($vt) => [$vt->value => $vt->label()])
            ->all();

        return view('archive.index', [
            'videos' => $videos,
            'oshis' => $oshis,
            'videoTypes' => $videoTypes,
            'filters' => [
                'oshi_id' => $oshiId,
                'video_type' => $videoType,
                'q' => $query,
            ],
            'profile' => $profile,
        ]);
    }

    /**
     * 配信詳細ページを表示する（FR-001）。
     *
     * watch_item の所有権を Policy で確認し、関連データを eager load して渡す。
     * 配信詳細ページは /archives/{watchItem} ルートで提供する（仕様 Q3）。
     */
    public function show(Request $request, UserWatchItem $watchItem): View
    {
        $this->authorize('view', $watchItem);

        $watchItem->load('youtubeVideo.youtubeChannel');

        /** @var Profile $profile */
        $profile = $request->user();

        // タイムスタンプメモ一覧（秒数昇順、タグ eager load）（FR-001 / FR-003）
        $memos = TimestampMemo::with('tags')
            ->where('profile_id', $profile->id)
            ->where('youtube_video_id', $watchItem->youtube_video_id)
            ->orderBy('seconds')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'seconds' => $m->seconds,
                'seconds_label' => $m->seconds_label,
                'body' => $m->body,
                'is_favorite' => $m->is_favorite,
                'tags' => $m->tags->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'color' => $t->color,
                ])->values()->all(),
                'youtube_url' => "https://www.youtube.com/watch?v={$watchItem->youtubeVideo?->youtube_video_id}&t={$m->seconds}s",
            ])
            ->all();

        // 動画ノート（ユーザーの当該動画分、存在しない場合は null）（FR-005）
        $videoNote = VideoNote::where('profile_id', $profile->id)
            ->where('youtube_video_id', $watchItem->youtube_video_id)
            ->first();

        // システムタグ + ユーザー固有タグ（タグ選択UI向け）（FR-008 / FR-009）
        $systemTags = Tag::system()->orderBy('name')->get();
        $userTags = Tag::forUser($profile->id)->orderBy('name')->get();

        return view('archives.show', compact('watchItem', 'profile', 'memos', 'videoNote', 'systemTags', 'userTags'));
    }
}

@php use App\Enums\VideoType; @endphp
@extends('layouts.app', ['title' => '見るリスト | V-アーカイブ', 'pageTitle' => '見るリスト'])

@section('content')
<div class="watchlist-page">
    <x-page-heading
        eyebrow="WATCHLIST"
        title="見るリスト"
        description="あとで見たい配信と視聴中・視聴済みのアーカイブを管理します。"
    >
        <x-slot:actions><span class="archive-sort-label">追加が新しい順</span></x-slot:actions>
    </x-page-heading>

    {{-- フラッシュメッセージ --}}
    @if(session('success'))
        <div class="flash flash-success" role="alert">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="flash flash-error" role="alert">{{ session('error') }}</div>
    @endif

    {{-- ステータスタブ --}}
    <nav class="watch-tabs" aria-label="視聴ステータス切替">
        @foreach(\App\Enums\WatchStatus::cases() as $st)
            <a
                href="{{ route('watchlist.index', ['status' => $st->value]) }}"
                class="watch-tab {{ $currentStatus === $st ? 'active' : '' }}"
                aria-current="{{ $currentStatus === $st ? 'page' : 'false' }}"
            >
                {{ $st->label() }}
                @if($tabCounts[$st->value] > 0)
                    <span class="tab-badge">{{ $tabCounts[$st->value] }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="section-panel">
    <div class="watchlist-meta">
        <span>{{ $currentStatus->label() }} <b>{{ $tabCounts[$currentStatus->value] }}</b> 本</span>
        <a href="{{ route('archive.index') }}">新着から追加 <x-icon name="chevron-right" /></a>
    </div>

    {{-- 動画一覧 --}}
    @if($watchItems->isEmpty())
        <div class="archive-empty">
            <x-icon name="list" />
            <p>{{ $currentStatus->label() }}の動画はありません。</p>
        </div>
    @else
        <div class="watchlist-video-list">
            @foreach($watchItems as $item)
                @php
                    /** @var \App\Models\UserWatchItem $item */
                    $colorClass  = $item->oshi_color_id ? 'oshi-color-'.$item->oshi_color_id : 'oshi-color-default';
                    $duration    = $item->video_duration_seconds
                        ? sprintf('%d:%02d:%02d',
                            intdiv((int)$item->video_duration_seconds, 3600),
                            intdiv((int)$item->video_duration_seconds % 3600, 60),
                            (int)$item->video_duration_seconds % 60)
                        : '';
                    $publishedAt = $item->video_published_at
                        ? \Illuminate\Support\Carbon::parse($item->video_published_at)->format('Y/m/d H:i')
                        : '';
                    $videoType   = $item->video_type_value
                        ? VideoType::tryFrom((string)$item->video_type_value)
                        : null;
                    $positionSeconds = (int) ($item->last_position_seconds ?? 0);
                    $durationSeconds = (int) ($item->video_duration_seconds ?? 0);
                    $progressPercent = $durationSeconds > 0
                        ? min(100, max(0, (int) round($positionSeconds / $durationSeconds * 100)))
                        : 0;
                    $positionLabel = $positionSeconds > 0
                        ? sprintf('%d:%02d:%02d', intdiv($positionSeconds, 3600), intdiv($positionSeconds % 3600, 60), $positionSeconds % 60)
                        : '0:00';
                @endphp
                <article
                    class="archive-card watchlist-card oshi-accent-card {{ $colorClass }}"
                    x-data="{ menuOpen: false }"
                    @keydown.escape.window="menuOpen = false"
                >
                    {{-- カード全面を覆うリンク。アクション列はz-indexで上に出す --}}
                    <a
                        href="{{ route('archives.show', $item) }}"
                        class="watchlist-card-link"
                        aria-label="{{ $item->video_title ?? '配信詳細を開く' }}"
                    ></a>
                    <div class="archive-thumb">
                        <img
                            src="{{ $item->video_thumbnail_url ?: asset('images/archive-note/placeholder.png') }}"
                            alt="{{ $item->video_title ?? '' }}"
                            loading="lazy"
                        >
                        @if($duration)
                            <span class="duration">{{ $duration }}</span>
                        @endif
                    </div>
                    <div class="archive-copy">
                        <h3>{{ $item->video_title ?? '（削除済み）' }}</h3>
                        @if($item->oshi_name)
                            <p class="oshi-identity">
                                <span class="oshi-color-dot" aria-hidden="true"></span>
                                <span>{{ $item->oshi_name }}</span>
                            </p>
                        @endif
                        @if($item->channel_title)
                            <p class="channel-name">{{ $item->channel_title }}</p>
                        @endif
                        <time datetime="{{ $item->video_published_at ?? '' }}">{{ $publishedAt }}</time>
                        @if($videoType)
                            <div class="tag-list">
                                <span class="tag tag-mint">{{ $videoType->label() }}</span>
                            </div>
                        @endif
                        @if($item->status === \App\Enums\WatchStatus::Watching && $durationSeconds > 0)
                            <div class="watch-progress" aria-label="視聴進捗 {{ $progressPercent }}パーセント">
                                <div class="watch-progress-meta">
                                    <span>途中まで（{{ $progressPercent }}%）</span>
                                    <small>{{ $positionLabel }} / {{ $duration }}</small>
                                </div>
                                <div class="watch-progress-track"><span style="width: {{ $progressPercent }}%"></span></div>
                            </div>
                        @endif
                    </div>

                    {{-- モバイル用の副次操作メニュー --}}
                    <div class="watchlist-mobile-menu">
                        <button
                            type="button"
                            class="watchlist-menu-trigger"
                            aria-label="その他の操作"
                            :aria-expanded="menuOpen"
                            @click.stop="menuOpen = !menuOpen"
                        >
                            <x-icon name="dots" />
                        </button>
                        <div
                            class="watchlist-menu-popover"
                            x-cloak
                            x-show="menuOpen"
                            @click.outside="menuOpen = false"
                        >
                            <form method="POST" action="{{ route('watchlist.destroy', $item) }}">
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    onclick="return confirm('このアイテムを削除してもよいですか？削除すると新着アーカイブに戻ります。')"
                                >
                                    <x-icon name="trash" />
                                    削除して未整理に戻す
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- ステータス変更・削除アクション --}}
                    <div class="watchlist-actions">
                        {{-- ステータス変更 --}}
                        <form method="POST" action="{{ route('watchlist.update', $item) }}" class="watchlist-status-form" data-auto-submit>
                            @csrf
                            @method('PATCH')
                            <span class="watchlist-mobile-status-label">ステータス</span>
                            <select
                                name="status"
                                class="status-select"
                                aria-label="ステータスを変更"
                            >
                                @foreach(\App\Enums\WatchStatus::cases() as $st)
                                    @if($st !== \App\Enums\WatchStatus::Watching)
                                        <option
                                            value="{{ $st->value }}"
                                            @selected($item->status === $st)
                                        >{{ $st->label() }}</option>
                                    @else
                                        {{-- watching は手動変更対象外（FR-005）--}}
                                        @if($item->status === $st)
                                            <option value="{{ $st->value }}" selected>{{ $st->label() }}</option>
                                        @endif
                                    @endif
                                @endforeach
                            </select>
                            <span class="inline-submit-status" data-submit-status role="status" aria-live="polite"></span>
                        </form>

                        {{-- 削除（未整理に戻す） --}}
                        <form method="POST" action="{{ route('watchlist.destroy', $item) }}" class="watchlist-desktop-delete">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="btn-icon btn-danger"
                                aria-label="削除して未整理に戻す"
                                onclick="return confirm('このアイテムを削除してもよいですか？削除すると新着アーカイブに戻ります。')"
                            >
                                <x-icon name="trash" />
                            </button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- ページネーション --}}
        @if($watchItems->hasPages())
            <div class="pagination-wrap">
                {{ $watchItems->links() }}
            </div>
        @endif
    @endif
    </div>{{-- /.section-panel --}}
</div>
@endsection

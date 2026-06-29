@extends('layouts.app', ['title' => '新着アーカイブ | V-アーカイブ', 'pageTitle' => '新着アーカイブ'])

@section('content')
<div class="archive-index-page">
    <x-page-heading
        eyebrow="ARCHIVES"
        title="新着アーカイブ"
        description="推しのチャンネルから届いた新しい配信を整理できます。"
    >
        <x-slot:actions><span class="archive-sort-label">新しい順</span></x-slot:actions>
    </x-page-heading>

    {{-- フラッシュメッセージ --}}
    @if(session('success'))
        <div class="flash flash-success" role="alert">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="flash flash-error" role="alert">{{ session('error') }}</div>
    @endif

    <div class="section-panel">
    {{-- フィルターバー --}}
    <form
        method="GET"
        action="{{ route('archive.index') }}"
        x-data="{}"
        class="archive-filter-bar"
    >
        @if($filters['q'])
            <input type="hidden" name="q" value="{{ $filters['q'] }}">
        @endif
        <div class="oshi-filter-scroll">
            <div class="oshi-filter-list" aria-label="推しで絞り込み" tabindex="0">
                <a
                    href="{{ route('archive.index', array_filter(['video_type' => $filters['video_type'], 'q' => $filters['q']])) }}"
                    class="oshi-filter-chip oshi-filter-all {{ $filters['oshi_id'] ? '' : 'active' }}"
                    @if(! $filters['oshi_id']) aria-current="page" @endif
                >すべて</a>
                @foreach($oshis as $oshi)
                    <a
                        href="{{ route('archive.index', array_filter(['oshi_id' => $oshi->id, 'video_type' => $filters['video_type'], 'q' => $filters['q']])) }}"
                        class="oshi-filter-chip {{ $oshi->color_id?->cssClass() ?? 'oshi-color-default' }} {{ $filters['oshi_id'] === $oshi->id ? 'active' : '' }}"
                        @if($filters['oshi_id'] === $oshi->id) aria-current="page" @endif
                    >
                        <span class="oshi-color-dot" aria-hidden="true"></span>
                        {{ $oshi->name }}
                    </a>
                @endforeach
            </div>
            <span class="oshi-filter-scroll-hint" aria-hidden="true"><x-icon name="chevron-right" /></span>
        </div>

        <select
            name="video_type"
            class="filter-select"
            aria-label="動画種別で絞り込み"
            @change="$el.form.submit()"
        >
            <option value="">すべての種別</option>
            @foreach($videoTypes as $value => $label)
                <option
                    value="{{ $value }}"
                    @selected($filters['video_type'] === $value)
                >{{ $label }}</option>
            @endforeach
        </select>

        @if($filters['oshi_id'] || $filters['video_type'] || $filters['q'])
            <a href="{{ route('archive.index') }}" class="filter-clear">条件を解除</a>
        @endif
    </form>

    {{-- 動画一覧 --}}
    @if($videos->isEmpty())
        <div class="archive-empty">
            <x-icon name="archive" />
            <p>新着の未整理動画はありません。</p>
            @if($filters['oshi_id'] || $filters['video_type'] || $filters['q'])
                <a href="{{ route('archive.index') }}" class="link-brand">フィルターを解除して全件表示</a>
            @endif
        </div>
    @else
        <div class="archive-video-grid">
            @foreach($videos as $video)
                @php
                    /** @var \stdClass $video */
                    $colorClass = $video->oshi_color ? 'oshi-color-'.$video->oshi_color : 'oshi-color-default';
                    $duration = $video->duration_seconds
                        ? sprintf('%d:%02d:%02d',
                            intdiv((int)$video->duration_seconds, 3600),
                            intdiv((int)$video->duration_seconds % 3600, 60),
                            (int)$video->duration_seconds % 60)
                        : '';
                    $publishedAt = $video->published_at
                        ? \Illuminate\Support\Carbon::parse($video->published_at)->format('Y/m/d H:i')
                        : '';
                @endphp
                <article class="archive-card archive-list-card oshi-accent-card {{ $colorClass }}">
                    <div class="archive-thumb">
                        <img
                            src="{{ $video->thumbnail_url ?: asset('images/archive-note/placeholder.png') }}"
                            alt="{{ $video->title }}"
                            loading="lazy"
                        >
                        @if($duration)
                            <span class="duration">{{ $duration }}</span>
                        @endif
                    </div>
                    <div class="archive-copy">
                        <h3>{{ $video->title }}</h3>
                        <p class="oshi-identity">
                            <span class="oshi-color-dot" aria-hidden="true"></span>
                            <span>{{ $video->oshi_name }}</span>
                        </p>
                        <time datetime="{{ $video->published_at }}">{{ $publishedAt }}</time>
                        @if($video->video_type)
                            <div class="tag-list">
                                <span class="tag tag-mint">
                                    {{ \App\Enums\VideoType::tryFrom($video->video_type)?->label() ?? $video->video_type }}
                                </span>
                            </div>
                        @endif
                    </div>

                    {{-- 見るリスト追加・見送りボタン --}}
                    <div class="archive-actions">
                        <form method="POST" action="{{ route('archive.watch-item.store', $video->id) }}">
                            @csrf
                            <input type="hidden" name="status" value="want_to_watch">
                            <button
                                type="submit"
                                class="btn-action btn-add"
                                aria-label="見るリストに追加"
                                title="見るリストに追加"
                            >
                                <x-icon name="bookmark" />
                                <span class="action-label">見るリスト</span>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('archive.watch-item.store', $video->id) }}">
                            @csrf
                            <input type="hidden" name="status" value="skipped">
                            <button
                                type="submit"
                                class="btn-action btn-skip"
                                aria-label="見送る"
                                title="この配信を見送る"
                            >
                                <x-icon name="x" />
                                <span class="action-label">見送る</span>
                            </button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- ページネーション --}}
        @if($videos->hasPages())
            <div class="pagination-wrap">
                {{ $videos->links() }}
            </div>
        @endif
    @endif
    </div>{{-- /.section-panel --}}
</div>
@endsection

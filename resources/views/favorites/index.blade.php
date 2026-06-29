@extends('layouts.app', ['title' => '神回・お気に入り | V-アーカイブ', 'pageTitle' => 'コレクション'])

@section('content')
<div class="favorites-page">
    <x-page-heading
        eyebrow="COLLECTION"
        title="神回・お気に入り"
        description="心に残した配信と、とっておきの場面を振り返れます。"
    />

    {{-- タブ切り替え --}}
    <nav class="fav-tabs" aria-label="コレクション表示切替">
        <a
            href="{{ route('favorites.index', ['tab' => 'kamikai']) }}"
            class="fav-tab {{ $tab === 'kamikai' ? 'active' : '' }}"
            @if($tab === 'kamikai') aria-current="page" @endif
        >
            <x-icon name="crown" />神回
        </a>
        <a
            href="{{ route('favorites.index', ['tab' => 'memos']) }}"
            class="fav-tab {{ $tab === 'memos' ? 'active' : '' }}"
            @if($tab === 'memos') aria-current="page" @endif
        >
            <x-icon name="star" />お気に入りメモ
        </a>
    </nav>

    <div class="section-panel">
    @if($tab === 'kamikai')
        {{-- 神回タブ --}}
        <div class="library-filter-panel">
            <x-oshi-filter-strip :oshis="$oshis" route-name="favorites.index" />
            <form class="library-select-filters" method="GET" action="{{ route('favorites.index') }}">
                <input type="hidden" name="tab" value="kamikai">
                @if($filters['oshiId'] ?? null)<input type="hidden" name="oshi_id" value="{{ $filters['oshiId'] }}">@endif
                <div class="fav-filter-group">
                    <label class="fav-filter-label" for="filter-month">年月</label>
                    <select id="filter-month" name="month" class="fav-filter-select" onchange="this.form.submit()">
                        <option value="">すべて</option>
                        @foreach($months as $m)
                            <option value="{{ $m }}" {{ ($filters['month'] ?? '') === $m ? 'selected' : '' }}>{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                @if(($filters['oshiId'] ?? '') || ($filters['month'] ?? ''))
                    <a href="{{ route('favorites.index', ['tab' => 'kamikai']) }}" class="fav-filter-reset">リセット</a>
                @endif
            </form>
        </div>

        <p class="fav-count">{{ number_format($kamikaiItems->total()) }} 件の神回</p>

        @if($kamikaiItems->isEmpty())
            <div class="fav-empty">
                <x-icon name="crown" />
                <p>神回登録した動画がありません。</p>
                <p class="fav-empty-sub">配信詳細ページの「神回に登録」ボタンで追加すると、ここに表示されます。</p>
                <a href="{{ route('watchlist.index', ['status' => 'watching']) }}" class="fav-empty-action">
                    <x-icon name="play" />
                    視聴中の配信を見る
                </a>
            </div>
        @else
            <div class="kamikai-grid {{ $kamikaiItems->total() === 1 ? 'is-sparse' : '' }}">
                @foreach($kamikaiItems as $item)
                    @php
                        /** @var \App\Models\UserWatchItem $item */
                        $video   = $item->youtubeVideo;
                        $channel = $video?->youtubeChannel;
                        $userChannel = $channel?->userChannels?->first();
                        $oshi = $userChannel?->oshi;
                        $oshiClass = $oshi?->color_id?->cssClass() ?? 'oshi-color-default';
                    @endphp
                    <article class="kamikai-card {{ $oshiClass }}">
                        <a href="{{ route('archives.show', $item) }}" class="kamikai-thumb-link" tabindex="-1" aria-hidden="true">
                            <div class="kamikai-thumb">
                                <img
                                    src="{{ $video?->thumbnail_url ?: asset('images/archive-note/placeholder.png') }}"
                                    alt="{{ $video?->title ?? '' }}"
                                    loading="lazy"
                                >
                                <span class="kamikai-status-badge"><x-icon name="crown" />神回</span>
                            </div>
                        </a>
                        <div class="kamikai-info">
                            <a href="{{ route('archives.show', $item) }}" class="kamikai-title-link">
                                <h3 class="kamikai-title">{{ $video?->title ?? '（削除済み）' }}</h3>
                            </a>
                            @if($channel)
                                <p class="kamikai-channel">{{ $channel->title }}</p>
                            @endif
                            @if($oshi)
                                <span class="memo-oshi-chip"><span class="oshi-color-dot" aria-hidden="true"></span>{{ $oshi->name }}</span>
                            @endif
                            <p class="kamikai-date">
                                <x-icon name="calendar" />
                                {{ $item->updated_at?->format('Y年n月j日') }}
                            </p>
                        </div>
                    </article>
                @endforeach
                @if($kamikaiItems->total() === 1)
                    <aside class="kamikai-guide">
                        <span class="kamikai-guide-icon"><x-icon name="crown" /></span>
                        <div>
                            <h2>心に残った配信を集めましょう</h2>
                            <p>配信詳細の「神回に登録」から追加できます。あとで見返したい配信を、自分だけのコレクションに。</p>
                            <a href="{{ route('watchlist.index') }}">見るリストを開く <x-icon name="chevron-right" /></a>
                        </div>
                    </aside>
                @endif
            </div>

            {{ $kamikaiItems->links() }}
        @endif

    @else
        {{-- お気に入りメモタブ --}}
        <div class="library-filter-panel">
            <x-oshi-filter-strip :oshis="$oshis" route-name="favorites.index" />
            <form class="library-select-filters" method="GET" action="{{ route('favorites.index') }}">
                <input type="hidden" name="tab" value="memos">
                @if($filters['oshiId'] ?? null)<input type="hidden" name="oshi_id" value="{{ $filters['oshiId'] }}">@endif
                <div class="fav-filter-group">
                    <label class="fav-filter-label" for="filter-tag">タグ</label>
                    <select id="filter-tag" name="tag_id" class="fav-filter-select" onchange="this.form.submit()">
                        <option value="">すべて</option>
                        @foreach($tags as $tag)
                            <option value="{{ $tag->id }}" {{ ($filters['tagId'] ?? '') === $tag->id ? 'selected' : '' }}>{{ $tag->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fav-filter-group">
                    <label class="fav-filter-label" for="filter-month">年月</label>
                    <select id="filter-month" name="month" class="fav-filter-select" onchange="this.form.submit()">
                        <option value="">すべて</option>
                        @foreach($months as $m)
                            <option value="{{ $m }}" {{ ($filters['month'] ?? '') === $m ? 'selected' : '' }}>{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                @if(($filters['oshiId'] ?? '') || ($filters['tagId'] ?? '') || ($filters['month'] ?? ''))
                    <a href="{{ route('favorites.index', ['tab' => 'memos']) }}" class="fav-filter-reset">リセット</a>
                @endif
            </form>
        </div>

        <p class="fav-count">{{ number_format($favorites->total()) }} 件のお気に入りメモ</p>

        @if($favorites->isEmpty())
            <div class="fav-empty">
                <x-icon name="star" />
                <p>お気に入りのタイムスタンプメモがありません。</p>
                <p class="fav-empty-sub">配信詳細ページのメモ一覧で ★ を押すとここに表示されます。</p>
                <a href="{{ route('watchlist.index', ['status' => 'watching']) }}" class="fav-empty-action">
                    <x-icon name="play" />
                    視聴中の配信を見る
                </a>
            </div>
        @else
            <div class="fav-list">
                @foreach($favorites as $memo)
                    <x-memo-library-card :memo="$memo" :archive-id="$watchItemMap[$memo->youtube_video_id] ?? null" />
                @endforeach
            </div>

            {{ $favorites->links() }}
        @endif
    @endif
    </div>{{-- /.section-panel --}}

</div>
@endsection

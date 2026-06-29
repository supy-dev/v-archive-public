@extends('layouts.app', ['title' => 'ホーム | V-アーカイブ', 'pageTitle' => 'ホーム'])

@section('content')
<div class="home-grid">
    <div class="home-main">
        <div class="mobile-brand"><a class="brand" href="{{ route('home') }}"><x-icon name="sparkle" weight="fill" /><strong>V-アーカイブ</strong></a><span>推しとの思い出を、もっと特別に。</span></div>
        <label class="mobile-search search-box"><span class="sr-only">アーカイブを検索</span><x-icon name="search" /><input type="search" placeholder="アーカイブを検索..." x-model="query" aria-label="アーカイブを検索"><x-icon name="sliders" /></label>

        <x-channel-strip :channels="$registeredChannels" :count="$registeredChannelCount" />

        <section class="section-panel">
            <x-section-title action="すべて見る" :href="route('archive.index')">最近追加されたアーカイブ</x-section-title>
            @if($recentArchives->isEmpty())
                <a href="{{ route('oshis.index') }}" class="home-watch-empty">
                    <span class="home-watch-empty-icon"><x-icon name="archive" /></span>
                    <span><b>アーカイブがまだありません</b><small>推しを登録すると動画が同期されます</small></span>
                    <x-icon name="chevron-right" />
                </a>
            @else
                <div class="featured-grid">
                    @foreach($recentArchives as $video)
                        @php
                            $duration = $video->duration_seconds
                                ? sprintf('%d:%02d:%02d', intdiv((int)$video->duration_seconds, 3600), intdiv((int)$video->duration_seconds % 3600, 60), (int)$video->duration_seconds % 60)
                                : '';
                            $colorClass = $video->oshi_color_id ? 'oshi-color-'.$video->oshi_color_id : 'oshi-color-default';
                            $publishedAt = \Illuminate\Support\Carbon::parse($video->published_at)->format('Y/m/d H:i');
                            $isNew = \Illuminate\Support\Carbon::parse($video->published_at)->gte(now()->subDays(3));
                        @endphp
                        <article class="archive-card oshi-accent-card {{ $colorClass }}" x-show="matches({{ Illuminate\Support\Js::from($video->title.' '.$video->oshi_name) }})" x-transition.opacity>
                            <div class="archive-thumb">
                                <img src="{{ $video->thumbnail_url ?: asset('images/archive-note/placeholder.png') }}" alt="{{ $video->title }}" loading="lazy">
                                @if($duration)<span class="duration">{{ $duration }}</span>@endif
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
                                        <span class="tag tag-mint">{{ \App\Enums\VideoType::tryFrom($video->video_type)?->label() ?? $video->video_type }}</span>
                                    </div>
                                @endif
                            </div>
                            @if($isNew)<span class="new-pill">NEW</span>@endif
                            <form method="POST" action="{{ route('archive.watch-item.store', $video->id) }}">
                                @csrf
                                <input type="hidden" name="status" value="want_to_watch">
                                <button type="submit" class="save-button" aria-label="見るリストに追加"><x-icon name="bookmark" /></button>
                            </form>
                        </article>
                    @endforeach
                </div>
                @if($recentArchives->count() > 2)
                    <a href="{{ route('archive.index') }}" class="mobile-archive-more">
                        <span>2件表示中・残りのアーカイブを見る</span>
                        <x-icon name="chevron-right" />
                    </a>
                @endif
                <p class="empty-search" x-cloak x-show="query && !hasResults()">該当するアーカイブがありません。</p>
            @endif
        </section>

        <section id="watch-list" class="section-panel">
            <x-section-title action="すべて見る" :href="route('watchlist.index')">見るリスト（未視聴）</x-section-title>
            @if($homeWatchItems->isEmpty())
                <a href="{{ route('archive.index') }}" class="home-watch-empty">
                    <span class="home-watch-empty-icon"><x-icon name="bookmark" /></span>
                    <span><b>あとで見たい配信を追加しましょう</b><small>新着アーカイブから選べます</small></span>
                    <x-icon name="chevron-right" />
                </a>
            @else
                <div class="watch-row">
                    @foreach($homeWatchItems as $item)
                        @php
                            $duration = $item->video_duration_seconds
                                ? sprintf('%d:%02d:%02d', intdiv((int)$item->video_duration_seconds, 3600), intdiv((int)$item->video_duration_seconds % 3600, 60), (int)$item->video_duration_seconds % 60)
                                : '';
                        @endphp
                        <a href="{{ route('archives.show', $item->id) }}" class="watch-thumb {{ $item->oshi_color_id ? 'oshi-color-'.$item->oshi_color_id : 'oshi-color-default' }}" aria-label="{{ $item->video_title }}">
                            <img src="{{ $item->video_thumbnail_url ?: asset('images/archive-note/placeholder.png') }}" alt="">
                            <span class="watch-oshi-marker" aria-hidden="true"></span>
                            @if($duration)<span class="duration">{{ $duration }}</span>@endif
                        </a>
                    @endforeach
                    @if($homeWatchItems->count() < 4)
                        <a href="{{ route('watchlist.index') }}" class="watch-more">すべて見る</a>
                    @endif
                </div>
            @endif
        </section>

        <div class="mobile-only">
            <section><x-section-title>あなたの視聴状況</x-section-title><x-home-stats :homeStats="$homeStats" /></section>
            <x-crown-banner />
        </div>
    </div>

    <aside class="home-side">
        <x-channel-strip :channels="$registeredChannels" :count="$registeredChannelCount" />
        <x-crown-banner />
        <section><x-section-title>あなたの視聴状況</x-section-title><x-home-stats :homeStats="$homeStats" /></section>
        <section class="recent-notes" id="timestamps">
            <x-section-title action="もっと見る" :href="route('memos.index')">最近のタイムスタンプ</x-section-title>
            @forelse($recentMemos as $recentMemo)
                <div>
                    <span>{{ $recentMemo->seconds_label }}</span>
                    <p>{{ $recentMemo->body }}</p>
                </div>
            @empty
                <p class="recent-notes-empty">タイムスタンプメモがまだありません。</p>
            @endforelse
        </section>
    </aside>
</div>
@endsection

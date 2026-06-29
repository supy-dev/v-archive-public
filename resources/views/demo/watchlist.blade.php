@php
$ph = asset('images/demo/thumb-placeholder.svg');

$wantToWatch = [
    ['title' => '【Minec⚪︎⚪︎⚪︎t】ついに念願のドラゴン討伐！果たしてできるのか？',     'oshi' => '碧海ルカ', 'ch' => 'Ruka Aomi Ch.', 'color' => 'blue',   'dur' => '3:22:48', 'date' => '2026/06/21 19:00', 'type' => 'アーカイブ', 'progress' => null, 'image' => asset('images/demo/ruka-video-1.png')],
    ['title' => '【ポケ⚪︎ン SV】色違いを求めて永遠に旅するよ ✨',                  'oshi' => '紫苑ゆい',   'ch' => 'Yui Shion Official', 'color' => 'violet', 'dur' => '4:05:22', 'date' => '2026/06/16 20:00', 'type' => 'アーカイブ', 'progress' => null, 'image' => asset('images/demo/yui-video-2.png')],
    ['title' => '【3周年直前】みんなにお礼を言いたくて…ありがとう！',             'oshi' => '桜音あいら', 'ch' => 'Aira Sakurane', 'color' => 'pink',   'dur' => '1:30:45', 'date' => '2026/06/14 19:00', 'type' => 'アーカイブ', 'progress' => null, 'image' => asset('images/demo/aira-video-2.png')],
    ['title' => '【朝活】おはようございます☀️ 一緒に作業しよ！',                     'oshi' => '夢見月サナ', 'ch' => 'Sana Ch. 夢見月さな', 'color' => 'rose',   'dur' => '2:00:00', 'date' => '2026/06/18 09:00', 'type' => 'アーカイブ', 'progress' => null, 'image' => asset('images/demo/sana-video-1.png')],
    ['title' => '【歌枠】感謝✨100万人記念 リクエスト歌枠！',                       'oshi' => '紫苑ゆい',   'ch' => 'Yui Shion Official', 'color' => 'violet', 'dur' => '1:44:02', 'date' => '2026/06/20 21:00', 'type' => 'アーカイブ',    'progress' => null, 'image' => asset('images/demo/yui-video-2.png')],
    ['title' => '【お絵描き】新しい立ち絵のラフを描くよ～！じっくり見てね',        'oshi' => '桜音あいら', 'ch' => 'Aira Sakurane', 'color' => 'pink',   'dur' => '1:10:55', 'date' => '2026/06/19 18:30', 'type' => 'アーカイブ', 'progress' => null, 'image' => asset('images/demo/aira-video-2.png')],
    ['title' => '【The ⚪︎⚪︎rest】サバイバル！一緒に生き延びよう #3',                  'oshi' => '碧海ルカ', 'ch' => 'Ruka Aomi Ch.', 'color' => 'blue',   'dur' => '2:55:10', 'date' => '2026/06/17 21:00', 'type' => 'アーカイブ', 'progress' => null, 'image' => asset('images/demo/ruka-video-1.png')],
    ['title' => '【3周年直前】みんなにお礼を言いたくて…ありがとう！',               'oshi' => '桜音あいら', 'ch' => 'Aira Sakurane', 'color' => 'pink',   'dur' => '1:30:45', 'date' => '2026/06/14 19:00', 'type' => 'アーカイブ', 'progress' => null, 'image' => asset('images/demo/aira-video-2.png')],
];

$watching = [
    ['title' => '【バ⚪︎オ RE4】怖すぎて泣きそう…でも頑張る！ #5',  'oshi' => '宵闇ソウ', 'ch' => 'Sou Yoiyami Ch.', 'color' => 'teal',   'dur' => '2:18:30', 'date' => '2026/06/19 22:00', 'type' => 'アーカイブ', 'pct' => 67, 'pos' => '1:32:16', 'image' => asset('images/demo/sou-video-1.png')],
    ['title' => '【ポ⚪︎モン SV】色違いを求めて永遠に旅するよ ✨',   'oshi' => '紫苑ゆい',   'ch' => 'Yui Shion Official', 'color' => 'violet', 'dur' => '4:05:22', 'date' => '2026/06/16 20:00', 'type' => 'アーカイブ', 'pct' => 28, 'pos' => '1:08:47', 'image' => asset('images/demo/yui-video-2.png')],
];

$tabCounts = ['want_to_watch' => count($wantToWatch), 'watching' => count($watching), 'watched' => 147, 'skipped' => 4];
@endphp
@extends('layouts.app', ['title' => '見るリスト | V-アーカイブ', 'pageTitle' => '見るリスト'])

@section('content')
<div class="watchlist-page" x-data="{ tab: 'want_to_watch' }">
    <x-page-heading
        eyebrow="WATCHLIST"
        title="見るリスト"
        description="あとで見たい配信と視聴中・視聴済みのアーカイブを管理します。"
    >
        <x-slot:actions><span class="archive-sort-label">追加が新しい順</span></x-slot:actions>
    </x-page-heading>

    {{-- ステータスタブ --}}
    <nav class="watch-tabs" aria-label="視聴ステータス切替">
        <a href="#" class="watch-tab" :class="{ active: tab === 'want_to_watch' }" @click.prevent="tab = 'want_to_watch'" :aria-current="tab === 'want_to_watch' ? 'page' : 'false'">
            未視聴<span class="tab-badge">{{ $tabCounts['want_to_watch'] }}</span>
        </a>
        <a href="#" class="watch-tab" :class="{ active: tab === 'watching' }" @click.prevent="tab = 'watching'" :aria-current="tab === 'watching' ? 'page' : 'false'">
            視聴中<span class="tab-badge">{{ $tabCounts['watching'] }}</span>
        </a>
        <a href="#" class="watch-tab" :class="{ active: tab === 'watched' }" @click.prevent="tab = 'watched'" :aria-current="tab === 'watched' ? 'page' : 'false'">
            視聴済み<span class="tab-badge">{{ $tabCounts['watched'] }}</span>
        </a>
        <a href="#" class="watch-tab" :class="{ active: tab === 'skipped' }" @click.prevent="tab = 'skipped'" :aria-current="tab === 'skipped' ? 'page' : 'false'">
            見送り<span class="tab-badge">{{ $tabCounts['skipped'] }}</span>
        </a>
    </nav>

    {{-- 未視聴 --}}
    <div class="section-panel" x-show="tab === 'want_to_watch'" x-transition.opacity>
        <div class="watchlist-meta">
            <span>未視聴 <b>{{ $tabCounts['want_to_watch'] }}</b> 本</span>
            <a href="{{ route('archive.index') }}">新着から追加 <x-icon name="chevron-right" /></a>
        </div>
        <div class="watchlist-video-list">
            @foreach($wantToWatch as $item)
            <article class="archive-card watchlist-card oshi-accent-card oshi-color-{{ $item['color'] }}">
                <a href="#" class="watchlist-card-link" aria-label="{{ $item['title'] }}"></a>
                <div class="archive-thumb" style="background:#FFFFFF">
                    <img src="{{ $item['image'] }}" alt="" loading="lazy">
                    <span class="duration">{{ $item['dur'] }}</span>
                </div>
                <div class="archive-copy">
                    <h3>{{ $item['title'] }}</h3>
                    <p class="oshi-identity">
                        <span class="oshi-color-dot" aria-hidden="true"></span>
                        <span>{{ $item['oshi'] }}</span>
                    </p>
                    <p class="channel-name">{{ $item['ch'] }}</p>
                    <time>{{ $item['date'] }}</time>
                    <div class="tag-list"><span class="tag tag-mint">{{ $item['type'] }}</span></div>
                </div>
                <div class="watchlist-actions">
                    <select class="status-select" aria-label="ステータスを変更">
                        <option selected>未視聴</option>
                        <option>視聴済み</option>
                        <option>見送り</option>
                    </select>
                    <button type="button" class="btn-icon btn-danger" aria-label="削除">
                        <x-icon name="trash" />
                    </button>
                </div>
            </article>
            @endforeach
        </div>
    </div>{{-- /.section-panel 未視聴 --}}

    {{-- 視聴中 --}}
    <div class="section-panel" x-show="tab === 'watching'" x-transition.opacity>
        <div class="watchlist-meta">
            <span>視聴中 <b>{{ $tabCounts['watching'] }}</b> 本</span>
        </div>
        <div class="watchlist-video-list">
            @foreach($watching as $item)
            <article class="archive-card watchlist-card oshi-accent-card oshi-color-{{ $item['color'] }}">
                <a href="#" class="watchlist-card-link" aria-label="{{ $item['title'] }}"></a>
                <div class="archive-thumb">
                    <img src="{{ $item['image'] }}" alt="" loading="lazy">
                    <span class="duration">{{ $item['dur'] }}</span>
                </div>
                <div class="archive-copy">
                    <h3>{{ $item['title'] }}</h3>
                    <p class="oshi-identity">
                        <span class="oshi-color-dot" aria-hidden="true"></span>
                        <span>{{ $item['oshi'] }}</span>
                    </p>
                    <p class="channel-name">{{ $item['ch'] }}</p>
                    <time>{{ $item['date'] }}</time>
                    <div class="tag-list"><span class="tag tag-mint">{{ $item['type'] }}</span></div>
                    <div class="watch-progress" aria-label="視聴進捗 {{ $item['pct'] }}%">
                        <div class="watch-progress-meta">
                            <span>途中まで（{{ $item['pct'] }}%）</span>
                            <small>{{ $item['pos'] }} / {{ $item['dur'] }}</small>
                        </div>
                        <div class="watch-progress-track">
                            <span style="width: {{ $item['pct'] }}%"></span>
                        </div>
                    </div>
                </div>
                <div class="watchlist-actions">
                    <select class="status-select" aria-label="ステータスを変更">
                        <option selected>視聴中</option>
                        <option>視聴済み</option>
                        <option>見送り</option>
                    </select>
                    <button type="button" class="btn-icon btn-danger" aria-label="削除">
                        <x-icon name="trash" />
                    </button>
                </div>
            </article>
            @endforeach
        </div>
    </div>{{-- /.section-panel 視聴中 --}}

    {{-- 視聴済み --}}
    <div class="section-panel" x-show="tab === 'watched'" x-transition.opacity>
        <div class="watchlist-meta">
            <span>視聴済み <b>{{ $tabCounts['watched'] }}</b> 本</span>
        </div>
        <div class="archive-empty">
            <x-icon name="check" />
            <p>視聴済みのアーカイブは別ページで管理できます。</p>
        </div>
    </div>{{-- /.section-panel 視聴済み --}}

    {{-- 見送り --}}
    <div class="section-panel" x-show="tab === 'skipped'" x-transition.opacity>
        <div class="watchlist-meta">
            <span>見送り <b>{{ $tabCounts['skipped'] }}</b> 本</span>
        </div>
        <div class="archive-empty">
            <x-icon name="x" />
            <p>見送りにした動画はありません。</p>
        </div>
    </div>{{-- /.section-panel 見送り --}}
</div>
@endsection

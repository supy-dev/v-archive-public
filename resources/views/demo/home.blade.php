@php
$ph = asset('images/demo/thumb-placeholder.svg');

$oshis = [
    ['name' => '夢見月サナ',  'color' => 'rose',   'ch' => 'Sana Ch. 夢見月さな', 'image' => asset('images/demo/sana-thumb-1.png')],
    ['name' => '碧海ルカ',  'color' => 'blue',   'ch' => 'Ruka Aomi Ch.', 'image' => asset('images/demo/ruka-thumb-1.png')],
    ['name' => '紫苑ゆい',   'color' => 'violet', 'ch' => 'Yui Shion Official', 'image' => asset('images/demo/yui-thumb-1.png')],
    ['name' => '宵闇ソウ', 'color' => 'teal',   'ch' => 'Sou Yoiyami Ch.', 'image' => asset('images/demo/sou-thumb-1.png')],
];

$recentArchives = [
    ['title' => '【雑談】最近あったこと話す！ Q&Aもやるよ！【夢見月サナ】',         'oshi' => '夢見月サナ', 'color' => 'rose',   'dur' => '2:01:14', 'date' => '2026/06/22 20:00', 'type' => 'アーカイブ', 'is_new' => true, 'image' => asset('images/demo/sana-video-1.png')],
    ['title' => '【Minec⚪︎⚪︎⚪︎t】ついに念願のドラゴン討伐！果たしてできるのか？',      'oshi' => '碧海ルカ', 'color' => 'blue',   'dur' => '3:22:48', 'date' => '2026/06/21 19:00', 'type' => 'アーカイブ', 'is_new' => true, 'image' => asset('images/demo/ruka-video-1.png')],
    ['title' => '【歌枠】感謝✨100万人記念 リクエスト歌枠！',                       'oshi' => '紫苑ゆい',   'color' => 'violet', 'dur' => '1:44:02', 'date' => '2026/06/20 21:00', 'type' => 'アーカイブ',    'is_new' => false, 'image' => asset('images/demo/yui-video-1.png')],
    ['title' => '【バ⚪︎オ RE4】怖すぎて泣きそう…でも頑張る！ #5【宵闇ソウ】',   'oshi' => '宵闇ソウ', 'color' => 'teal',   'dur' => '2:18:30', 'date' => '2026/06/19 22:00', 'type' => 'アーカイブ', 'is_new' => false, 'image' => asset('images/demo/sou-video-1.png')],
];

$watchItems = [
    ['color' => 'rose',   'dur' => '4:05:22', 'image' => asset('images/demo/sana-video-1.png')],
    ['color' => 'blue',   'dur' => '1:38:15', 'image' => asset('images/demo/ruka-video-1.png')],
    ['color' => 'violet', 'dur' => '0:45:30', 'image' => asset('images/demo/yui-video-1.png')],
    ['color' => 'teal',   'dur' => '2:00:00', 'image' => asset('images/demo/sou-video-1.png')],
];

$homeStats = ['unorganized' => 23, 'want_to_watch' => 8, 'watching' => 2, 'watched' => 147];

$recentMemos = [
    ['time' => '1:23:45', 'body' => 'ここのくだり最高すぎる笑 サナちゃんの天然が炸裂してる'],
    ['time' => '0:47:12', 'body' => 'コスモくんの歌声が最高すぎる場面。鳥肌立ちすぎた…'],
    ['time' => '2:05:33', 'body' => 'ゆいちゃんとコラボ発表！！嬉しすぎて叫んだ'],
];
@endphp
@extends('layouts.app', ['title' => 'ホーム | V-アーカイブ', 'pageTitle' => 'ホーム'])

@section('content')
<div class="home-grid">
    <div class="home-main">

        {{-- チャンネルストリップ（モバイル） --}}
        <a href="#" class="channel-strip channel-strip-link" style="display:none" aria-hidden="true"></a>

        {{-- 最近追加されたアーカイブ --}}
        <section class="section-panel">
            <header class="section-title">
                <h2>最近追加されたアーカイブ</h2>
                <a href="{{ route('archive.index') }}">すべて見る<x-icon name="chevron-right" /></a>
            </header>
            <div class="featured-grid">
                @foreach($recentArchives as $v)
                <article class="archive-card oshi-accent-card oshi-color-{{ $v['color'] }}">
                    <div class="archive-thumb" style="background:#FFFFFF">
                        <img src="{{ $v['image'] }}" alt="{{ $v['title'] }}" loading="lazy">
                        <span class="duration">{{ $v['dur'] }}</span>
                    </div>
                    <div class="archive-copy">
                        <h3>{{ $v['title'] }}</h3>
                        <p class="oshi-identity">
                            <span class="oshi-color-dot" aria-hidden="true"></span>
                            <span>{{ $v['oshi'] }}</span>
                        </p>
                        <time>{{ $v['date'] }}</time>
                        <div class="tag-list"><span class="tag tag-mint">{{ $v['type'] }}</span></div>
                    </div>
                    @if($v['is_new'])<span class="new-pill">NEW</span>@endif
                </article>
                @endforeach
            </div>
        </section>

        {{-- 見るリスト --}}
        <section id="watch-list" class="section-panel">
            <header class="section-title">
                <h2>見るリスト（未視聴）</h2>
                <a href="{{ route('watchlist.index') }}">すべて見る<x-icon name="chevron-right" /></a>
            </header>
            <div class="watch-row">
                @foreach($watchItems as $w)
                <div class="watch-thumb oshi-color-{{ $w['color'] }}" style="background:#FFFFFF">
                    <img src="{{ $w['image'] }}" alt="" loading="lazy">
                    <span class="watch-oshi-marker" aria-hidden="true"></span>
                    <span class="duration">{{ $w['dur'] }}</span>
                </div>
                @endforeach
            </div>
        </section>

    </div>

    <aside class="home-side">
        {{-- チャンネルストリップ --}}
        <a href="{{ route('oshis.index') }}" class="channel-strip channel-strip-link" aria-label="推しと登録チャンネルを管理">
            <div class="channel-count">
                <x-icon name="heart" weight="fill" />
                <span>
                    <small>推し・登録チャンネル</small>
                    <b>8<em>チャンネル</em></b>
                </span>
            </div>
            <div class="channel-strip-action">
                <div class="avatar-stack" aria-hidden="true">
                    @foreach($oshis as $o)
                    <img src="{{ $o['image'] }}" alt="">
                    @endforeach
                </div>
                <x-icon name="chevron-right" />
            </div>
        </a>

        {{-- 神回バナー --}}
        <x-crown-banner />

        {{-- 視聴状況 --}}
        <section>
            <header class="section-title"><h2>あなたの視聴状況</h2></header>
            <div class="stats">
                <a href="{{ route('archive.index') }}" class="stat-card stat-card-link">
                    <x-icon name="archive" />
                    <span><small>未整理</small><b>{{ $homeStats['unorganized'] }}<em>本</em></b></span>
                </a>
                <a href="{{ route('watchlist.index') }}" class="stat-card stat-card-link">
                    <x-icon name="list" />
                    <span><small>見るリスト</small><b>{{ $homeStats['want_to_watch'] }}<em>本</em></b></span>
                </a>
                <a href="{{ route('watchlist.index', ['status' => 'watching']) }}" class="stat-card stat-card-link">
                    <x-icon name="play" />
                    <span><small>視聴中</small><b>{{ $homeStats['watching'] }}<em>本</em></b></span>
                </a>
                <a href="{{ route('watchlist.index', ['status' => 'watched']) }}" class="stat-card stat-card-link">
                    <x-icon name="check" />
                    <span><small>視聴済み</small><b>{{ $homeStats['watched'] }}<em>本</em></b></span>
                </a>
            </div>
        </section>

        {{-- 最近のメモ --}}
        <section class="recent-notes" id="timestamps">
            <header class="section-title">
                <h2>最近のタイムスタンプ</h2>
                <a href="{{ route('memos.index') }}">もっと見る<x-icon name="chevron-right" /></a>
            </header>
            @foreach($recentMemos as $m)
            <div>
                <span>{{ $m['time'] }}</span>
                <p>{{ $m['body'] }}</p>
            </div>
            @endforeach
        </section>
    </aside>
</div>
@endsection

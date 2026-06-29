@php
$ph = asset('images/demo/thumb-placeholder.svg');

$oshis = [
    ['name' => '夢見月サナ',  'color' => 'rose'],
    ['name' => '碧海ルカ',  'color' => 'blue'],
    ['name' => '紫苑ゆい',   'color' => 'violet'],
    ['name' => '宵闇ソウ', 'color' => 'teal'],
    ['name' => '桜音あいら', 'color' => 'pink'],
];

$kamikaiItems = [
    ['title' => '【歌枠】感謝✨100万人記念 リクエスト歌枠！',                       'ch' => 'Yui Shion Official',  'oshi' => '紫苑ゆい',   'color' => 'violet', 'date' => '2026年6月20日', 'image' => asset('images/demo/yui-video-1.png')],
    ['title' => '【雑談】最近あったこと話す！ Q&Aもやるよ！',                        'ch' => 'Sana Ch. 夢見月さな', 'oshi' => '夢見月サナ',  'color' => 'rose',   'date' => '2026年6月22日', 'image' => asset('images/demo/sana-video-1.png')],
    ['title' => '【バ⚪︎オ RE4】怖すぎて泣きそう…でも頑張る！ #5',                   'ch' => 'Sou Yoiyami Ch.',      'oshi' => '宵闇ソウ', 'color' => 'teal',   'date' => '2026年6月19日', 'image' => asset('images/demo/sou-video-1.png')],
    ['title' => '【Minec⚪︎⚪︎⚪︎t】ついに念願のドラゴン討伐！果たしてできるのか？',       'ch' => 'Ruka Aomi Ch.',     'oshi' => '碧海ルカ',  'color' => 'blue',   'date' => '2026年6月21日', 'image' => asset('images/demo/ruka-video-1.png')],
    ['title' => '【3周年直前】みんなにお礼を言いたくて…ありがとう！',                'ch' => 'Aira Sakurane',       'oshi' => '桜音あいら', 'color' => 'pink',   'date' => '2026年6月14日', 'image' => asset('images/demo/aira-video-2.png')],
    ['title' => '【ポケ⚪︎ン SV】色違いを求めて永遠に旅するよ ✨',                   'ch' => 'Yui Shion Official',  'oshi' => '紫苑ゆい',   'color' => 'violet', 'date' => '2026年6月16日', 'image' => asset('images/demo/yui-video-2.png')],
];

$favMemos = [
    ['time' => '0:18:47', 'title' => '【歌枠】感謝✨100万人記念 リクエスト歌枠！',    'body' => '「夏夕空」のカバーが完璧すぎた。こんな歌い方ある？', 'oshi' => '紫苑ゆい',   'color' => 'violet', 'ch' => 'Yui Shion Official', 'tags' => [['name' => '神歌唱', 'color' => 'purple'], ['name' => 'カバー', 'color' => 'mint']], 'date' => '2026年6月20日'],
    ['time' => '1:12:55', 'title' => '【歌枠】感謝✨100万人記念 リクエスト歌枠！',    'body' => 'アンコールの「星に願いを」で泣いた。もう無理', 'oshi' => '紫苑ゆい',   'color' => 'violet', 'ch' => 'Yui Shion Official', 'tags' => [['name' => '感動', 'color' => 'pink'], ['name' => '泣ける', 'color' => 'pink']], 'date' => '2026年6月20日'],
    ['time' => '2:05:33', 'title' => '【雑談】最近あったこと話す！ Q&Aもやるよ！',   'body' => 'ゆいちゃんとコラボ発表！！嬉しすぎて叫んだ', 'oshi' => '夢見月サナ',  'color' => 'rose',   'ch' => 'Sana Ch. 夢見月さな', 'tags' => [['name' => 'コラボ', 'color' => 'orange']], 'date' => '2026年6月22日'],
    ['time' => '0:43:12', 'title' => '【雑談】最近あったこと話す！ Q&Aもやるよ！',   'body' => 'ここのくだり最高すぎる笑 サナちゃんの天然が炸裂してる', 'oshi' => '夢見月サナ',  'color' => 'rose',   'ch' => 'Sana Ch. 夢見月さな', 'tags' => [['name' => '面白い', 'color' => 'orange']], 'date' => '2026年6月22日'],
    ['time' => '1:32:16', 'title' => '【バ⚪︎オ RE4】怖すぎて泣きそう…でも頑張る！ #5', 'body' => 'びびりすぎて後ろに飛び退くやつ笑 これはリアクション芸術すぎる', 'oshi' => '宵闇ソウ', 'color' => 'teal',   'ch' => 'Sou Yoiyami Ch.', 'tags' => [['name' => '面白い', 'color' => 'orange'], ['name' => 'リアクション', 'color' => 'blue']], 'date' => '2026年6月19日'],
];

$tagColorMap = ['mint' => 'tag-mint', 'blue' => 'tag-blue', 'purple' => 'tag-purple', 'orange' => 'tag-orange', 'pink' => 'tag-pink', 'green' => 'tag-green'];
@endphp
@extends('layouts.app', ['title' => '神回・お気に入り | V-アーカイブ', 'pageTitle' => '神回・お気に入り'])

@section('content')
<div class="favorites-page">
    <x-page-heading
        eyebrow="COLLECTION"
        title="神回・お気に入り"
        description="心に残した配信と、とっておきの場面を振り返れます。"
    />

    {{-- タブ --}}
    <nav class="fav-tabs" aria-label="コレクション表示切替">
        <a href="#" class="fav-tab active" aria-current="page"><x-icon name="crown" />神回</a>
        <a href="#" class="fav-tab"><x-icon name="star" />お気に入りメモ</a>
    </nav>

    <div class="section-panel">
    {{-- 神回フィルター --}}
    <div class="library-filter-panel">
        <div class="library-oshi-filter">
            <span class="library-filter-label">推し</span>
            <div class="oshi-filter-list" aria-label="推しで絞り込む">
                <a href="#" class="oshi-filter-chip oshi-filter-all active" aria-current="page">すべて</a>
                @foreach($oshis as $o)
                <a href="#" class="oshi-filter-chip oshi-color-{{ $o['color'] }}">
                    <span class="oshi-color-dot" aria-hidden="true"></span>{{ $o['name'] }}
                </a>
                @endforeach
            </div>
        </div>
        <form class="library-select-filters">
            <div class="fav-filter-group">
                <label class="fav-filter-label" for="demo-filter-month">年月</label>
                <select id="demo-filter-month" class="fav-filter-select">
                    <option selected>すべて</option>
                    <option>2026年6月</option>
                    <option>2026年5月</option>
                </select>
            </div>
        </form>
    </div>

    <p class="fav-count">{{ count($kamikaiItems) }} 件の神回</p>

    {{-- 神回グリッド --}}
    <div class="kamikai-grid">
        @foreach($kamikaiItems as $k)
        <article class="kamikai-card oshi-color-{{ $k['color'] }}">
            <a href="#" class="kamikai-thumb-link" tabindex="-1" aria-hidden="true">
                <div class="kamikai-thumb" style="background:#FFFFFF">
                    <img src="{{ $k['image'] }}" alt="{{ $k['title'] }}" loading="lazy">
                    <span class="kamikai-status-badge"><x-icon name="crown" />神回</span>
                </div>
            </a>
            <div class="kamikai-info">
                <a href="#" class="kamikai-title-link">
                    <h3 class="kamikai-title">{{ $k['title'] }}</h3>
                </a>
                <p class="kamikai-channel">{{ $k['ch'] }}</p>
                <span class="memo-oshi-chip">
                    <span class="oshi-color-dot" aria-hidden="true"></span>{{ $k['oshi'] }}
                </span>
                <p class="kamikai-date"><x-icon name="calendar" />{{ $k['date'] }}</p>
            </div>
        </article>
        @endforeach
    </div>
    </div>{{-- /.section-panel 神回 --}}

    {{-- お気に入りメモタブ（参考表示） --}}
    <div class="section-panel" style="margin-top: 14px;">
        <nav class="fav-tabs" aria-label="コレクション表示切替（お気に入りメモ）">
            <a href="#" class="fav-tab"><x-icon name="crown" />神回</a>
            <a href="#" class="fav-tab active" aria-current="page"><x-icon name="star" />お気に入りメモ</a>
        </nav>

        <p class="fav-count">{{ count($favMemos) }} 件のお気に入りメモ</p>

        <div class="fav-list">
            @foreach($favMemos as $m)
            <article class="memo-library-card oshi-color-{{ $m['color'] }}">
                <div class="memo-library-card-top">
                    <span class="memo-library-timestamp">
                        <x-icon name="timer" />{{ $m['time'] }}
                    </span>
                    <div class="memo-library-meta">
                        <a href="#" class="memo-library-title-link">
                            <h3 class="memo-library-title">{{ $m['title'] }}</h3>
                        </a>
                        <div class="memo-library-identities">
                            <span class="memo-oshi-chip">
                                <span class="oshi-color-dot" aria-hidden="true"></span>{{ $m['oshi'] }}
                            </span>
                            <span class="memo-library-channel">{{ $m['ch'] }}</span>
                        </div>
                    </div>
                    <span class="memo-favorite-state" title="お気に入りメモ">
                        <x-icon name="star" weight="fill" /><span>お気に入り</span>
                    </span>
                </div>
                <p class="memo-library-body">{{ $m['body'] }}</p>
                @if(count($m['tags']) > 0)
                <div class="tag-list">
                    @foreach($m['tags'] as $t)
                    <span class="tag {{ $tagColorMap[$t['color']] ?? 'tag-purple' }}">{{ $t['name'] }}</span>
                    @endforeach
                </div>
                @endif
                <footer class="memo-library-footer">
                    <div class="memo-library-actions">
                        <a href="#" class="memo-primary-link"><x-icon name="play" />配信を見る</a>
                        <a href="#" target="_blank" rel="noopener noreferrer" class="memo-youtube-link">
                            <x-icon name="youtube" />YouTubeで開く
                        </a>
                    </div>
                    <time class="memo-library-date">{{ $m['date'] }}</time>
                </footer>
            </article>
            @endforeach
        </div>
    </div>
</div>
@endsection

@php
$ph = asset('images/demo/thumb-placeholder.svg');

$oshis = [
    ['id' => 1, 'name' => '夢見月サナ',  'color' => 'rose'],
    ['id' => 2, 'name' => '碧海ルカ',  'color' => 'blue'],
    ['id' => 3, 'name' => '紫苑ゆい',   'color' => 'violet'],
    ['id' => 4, 'name' => '宵闇ソウ', 'color' => 'teal'],
    ['id' => 5, 'name' => '桜音あいら', 'color' => 'pink'],
];

$videos = [
    ['title' => '【雑談】最近あったこと話す！ Q&Aもやるよ！',                 'oshi' => '夢見月サナ',  'color' => 'rose',   'dur' => '2:01:14', 'date' => '2026/06/22 20:00', 'type' => 'アーカイブ', 'image' => asset('images/demo/sana-video-1.png')],
    ['title' => '【Minec⚪︎t】ついに念願のドラゴン討伐！果たしてできるのか？', 'oshi' => '碧海ルカ',  'color' => 'blue',   'dur' => '3:22:48', 'date' => '2026/06/21 19:00', 'type' => 'アーカイブ', 'image' => asset('images/demo/ruka-video-1.png')],
    ['title' => '【歌枠】感謝✨100万人記念 リクエスト歌枠！',                  'oshi' => '紫苑ゆい',   'color' => 'violet', 'dur' => '1:44:02', 'date' => '2026/06/20 21:00', 'type' => 'アーカイブ', 'image' => asset('images/demo/yui-video-1.png')],
    ['title' => '【バ⚪︎オ RE4】怖すぎて泣きそう…でも頑張る！ #5',             'oshi' => '宵闇ソウ', 'color' => 'teal',   'dur' => '2:18:30', 'date' => '2026/06/19 22:00', 'type' => 'アーカイブ', 'image' => asset('images/demo/sou-video-1.png')],
    ['title' => '【お絵描き】新しい立ち絵のラフを描くよ～！じっくり見てね',   'oshi' => '桜音あいら', 'color' => 'pink',   'dur' => '1:10:55', 'date' => '2026/06/19 18:30', 'type' => 'アーカイブ', 'image' => asset('images/demo/aira-video-1.png')],
    ['title' => '【朝活】おはようございます☀️ 一緒に作業しよ！',              'oshi' => '夢見月サナ',  'color' => 'rose',   'dur' => '2:00:00', 'date' => '2026/06/18 09:00', 'type' => 'アーカイブ', 'image' => asset('images/demo/sana-video-2.png')],
    ['title' => '【The ⚪︎rest】サバイバル！一緒に生き延びよう #3',             'oshi' => '碧海ルカ',  'color' => 'blue',   'dur' => '2:55:10', 'date' => '2026/06/17 21:00', 'type' => 'アーカイブ', 'image' => asset('images/demo/ruka-video-1.png')],
    ['title' => '【ポ⚪︎モン SV】色違いを求めて永遠に旅するよ ✨',              'oshi' => '紫苑ゆい',   'color' => 'violet', 'dur' => '4:05:22', 'date' => '2026/06/16 20:00', 'type' => 'アーカイブ', 'image' => asset('images/demo/yui-video-2.png')],
    ['title' => '【リング⚪︎ィット】ぜったい痩せる配信 Day 7！',                'oshi' => '宵闇ソウ', 'color' => 'teal',   'dur' => '0:45:30', 'date' => '2026/06/15 20:00', 'type' => 'アーカイブ', 'image' => asset('images/demo/sou-video-2.png')],
    ['title' => '【3周年直前】みんなにお礼を言いたくて…ありがとう！',          'oshi' => '桜音あいら', 'color' => 'pink',   'dur' => '1:30:45', 'date' => '2026/06/14 19:00', 'type' => 'アーカイブ', 'image' => asset('images/demo/aira-video-2.png')],
];
@endphp
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

    <div class="section-panel">
    {{-- フィルターバー --}}
    <div class="archive-filter-bar">
        <div class="oshi-filter-list" aria-label="推しで絞り込み">
            <a href="#" class="oshi-filter-chip oshi-filter-all active" aria-current="page">すべて</a>
            @foreach($oshis as $o)
            <a href="#" class="oshi-filter-chip oshi-color-{{ $o['color'] }}">
                <span class="oshi-color-dot" aria-hidden="true"></span>
                {{ $o['name'] }}
            </a>
            @endforeach
        </div>
        <select class="filter-select" aria-label="動画種別で絞り込み">
            <option>すべての種別</option>
            <option>アーカイブ</option>
            <option>ライブ</option>
            <option>配信予定</option>
            <option>ショート</option>
            <option>動画</option>
        </select>
    </div>

    {{-- 動画一覧 --}}
    <div class="archive-video-grid">
        @foreach($videos as $v)
        <article class="archive-card archive-list-card oshi-accent-card oshi-color-{{ $v['color'] }}">
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
            <div class="archive-actions">
                <form>
                    <button type="button" class="btn-action btn-add" aria-label="見るリストに追加">
                        <x-icon name="bookmark" />
                        <span class="action-label">見るリスト</span>
                    </button>
                </form>
                <form>
                    <button type="button" class="btn-action btn-skip" aria-label="見送る">
                        <x-icon name="x" />
                        <span class="action-label">見送る</span>
                    </button>
                </form>
            </div>
        </article>
        @endforeach
    </div>
    </div>{{-- /.section-panel --}}
</div>
@endsection

@php
$ph = asset('images/demo/thumb-placeholder.svg');

$video = [
    'title'       => '【歌枠】感謝✨100万人記念 リクエスト歌枠！',
    'channel'     => 'Yui Shion Official',
    'channel_image' => asset('images/demo/yui-thumb-1.png'),
    'published'   => '2026/06/20 21:00',
    'duration'    => '1:44:02',
    'type'        => 'アーカイブ',
    'pct'         => 42,
    'pos'         => '0:43:41',
    'is_favorite' => true,
    'image'       => asset('images/demo/yui-video-1.png'),
];

$memos = [
    ['time' => '0:03:22', 'body' => 'オープニングのBGMが最高すぎる…毎回鳥肌',              'tags' => [['name' => 'BGM', 'color' => 'blue']], 'is_fav' => false],
    ['time' => '0:18:47', 'body' => '「夏夕空」のカバーが完璧すぎた。こんな歌い方ある？',   'tags' => [['name' => '神歌唱', 'color' => 'purple'], ['name' => 'カバー', 'color' => 'mint']], 'is_fav' => true],
    ['time' => '0:43:12', 'body' => 'リスナーとのやり取りがめちゃくちゃ面白い笑',           'tags' => [['name' => '面白い', 'color' => 'orange']], 'is_fav' => false],
    ['time' => '1:12:55', 'body' => 'アンコールの「星に願いを」で泣いた。もう無理',          'tags' => [['name' => '感動', 'color' => 'pink'], ['name' => '泣ける', 'color' => 'pink']], 'is_fav' => true],
    ['time' => '1:38:30', 'body' => 'エンディングのコメント読みで100万人記念の話してた。大事にとっておく', 'tags' => [['name' => '記念', 'color' => 'purple']], 'is_fav' => false],
];

$tagColorMap = ['mint' => 'tag-mint', 'blue' => 'tag-blue', 'purple' => 'tag-purple', 'orange' => 'tag-orange', 'pink' => 'tag-pink', 'green' => 'tag-green'];
@endphp
@extends('layouts.app', ['title' => '配信詳細 | V-アーカイブ', 'pageTitle' => '配信詳細'])

@section('content')
<div class="archive-show-page">

    <div class="detail-player-stage">
        <header class="show-page-header">
            <a href="{{ route('watchlist.index') }}" class="show-back-link">
                <x-icon name="arrow-left" /><span>配信詳細</span>
            </a>
            <div class="show-page-actions">
                <button type="button" class="btn-detail-action btn-detail-resume">
                    <x-icon name="play" />続きから見る
                </button>
                <a href="#" target="_blank" rel="noopener noreferrer" class="btn-detail-action btn-detail-youtube">
                    <x-icon name="youtube" />YouTubeで開く
                </a>
            </div>
        </header>

        {{-- プレイヤーエリア（デモ用プレースホルダー） --}}
        <div class="player-ratio-box">
            <div class="demo-player-placeholder">
                <img src="{{ $video['image'] }}" alt="{{ $video['title'] }}">
                <div class="demo-player-icon" aria-hidden="true">
                    <x-icon name="play" />
                </div>
            </div>
        </div>

        {{-- 配信情報カード --}}
        <section class="show-info-card oshi-color-violet" aria-labelledby="archive-title">
            <div class="show-info-main">
                <img class="show-channel-avatar" src="{{ $video['channel_image'] }}" alt="{{ $video['channel'] }}">
                <div class="show-copy">
                    <h2 id="archive-title" class="show-title">{{ $video['title'] }}</h2>
                    <div class="show-channel-line">
                        <span>{{ $video['channel'] }}</span>
                        <span class="tag tag-purple">{{ $video['type'] }}</span>
                        <span class="tag tag-blue">アーカイブ</span>
                    </div>
                </div>
                <a href="#" target="_blank" rel="noopener noreferrer" class="btn-channel-link">
                    チャンネルを見る<x-icon name="chevron-right" />
                </a>
                <dl class="show-metrics">
                    <div><dt>配信日</dt><dd>{{ $video['published'] }}</dd></div>
                    <div><dt>メモ</dt><dd>{{ count($memos) }} 件</dd></div>
                    <div><dt>アーカイブ時間</dt><dd>{{ $video['duration'] }}</dd></div>
                </dl>
            </div>
            <div class="show-progress-row">
                <div class="show-status-form">
                    <label>視聴ステータス</label>
                    <select class="show-status-select" aria-label="ステータスを変更">
                        <option>見るリスト</option>
                        <option selected>視聴中</option>
                        <option>視聴済み</option>
                    </select>
                </div>
                <span class="show-progress-pill">途中まで（{{ $video['pct'] }}%）</span>
                <span class="show-progress-time">{{ $video['pos'] }} <i>/</i> {{ $video['duration'] }}</span>
                <div class="kamikai-toggle-wrap">
                    <button
                        type="button"
                        class="btn-best-stream btn-kamikai-toggle {{ $video['is_favorite'] ? 'active' : '' }}"
                        aria-pressed="{{ $video['is_favorite'] ? 'true' : 'false' }}"
                        aria-label="{{ $video['is_favorite'] ? '神回を解除' : '神回に登録' }}"
                    >
                        <x-icon name="crown" />
                        <span>{{ $video['is_favorite'] ? '神回解除' : '神回に登録' }}</span>
                    </button>
                </div>
            </div>
        </section>
    </div>

    {{-- タイムスタンプメモ --}}
    <div class="show-memos">
        <div class="show-section-header">
            <h3 class="show-section-title">
                <x-icon name="timer" />タイムスタンプメモ
                <span class="section-count">{{ count($memos) }} 件</span>
            </h3>
            <button type="button" class="btn-add-memo">
                <x-icon name="plus" />現在位置をメモ
            </button>
        </div>

        <div class="memo-list">
            @foreach($memos as $m)
            <div class="memo-card {{ $m['is_fav'] ? 'memo-card-fav' : '' }}">
                <div class="memo-card-top">
                    <button type="button" class="memo-timestamp-btn" aria-label="{{ $m['time'] }} へシーク">
                        <x-icon name="timer" /><span>{{ $m['time'] }}</span>
                    </button>
                    <div class="memo-card-content">
                        <p class="memo-body">{{ $m['body'] }}</p>
                        @if(count($m['tags']) > 0)
                        <div class="tag-list">
                            @foreach($m['tags'] as $t)
                            <span class="tag {{ $tagColorMap[$t['color']] ?? 'tag-purple' }}">{{ $t['name'] }}</span>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    <div class="memo-card-actions">
                        <button
                            type="button"
                            class="btn-memo-icon {{ $m['is_fav'] ? 'btn-memo-fav-active' : 'btn-memo-fav' }}"
                            aria-label="{{ $m['is_fav'] ? 'お気に入り解除' : 'お気に入り登録' }}"
                            aria-pressed="{{ $m['is_fav'] ? 'true' : 'false' }}"
                        >
                            <x-icon name="star" :weight="$m['is_fav'] ? 'fill' : 'regular'" />
                        </button>
                        <button type="button" class="btn-memo-icon btn-memo-more" aria-label="その他の操作">
                            <x-icon name="dots" />
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- 全体感想メモ --}}
    <div class="show-video-note">
        <h3 class="show-section-title"><x-icon name="note" />感想メモ <small>（この配信を通しての感想）</small></h3>
        <textarea
            class="note-textarea"
            placeholder="動画全体への感想を書く…（最大5000文字）"
            rows="5"
            aria-label="全体感想"
        >100万人記念ということで、いつも以上に気合いが入っていた歌枠でした。選曲も素晴らしくて、特に「夏夕空」のカバーは何度でも聴けるレベル。アンコールの「星に願いを」は本当に心に刺さった。大事な配信なのでタイムスタンプもたくさん残せてよかった。</textarea>
        <div class="note-actions">
            <button type="button" class="btn-note-save" aria-label="全体感想を保存">保存</button>
            <button type="button" class="btn-note-delete" aria-label="全体感想を削除">
                <x-icon name="trash" />削除
            </button>
        </div>
    </div>

</div>
@endsection

@extends('layouts.app', ['title' => 'タイムスタンプメモ | V-アーカイブ', 'pageTitle' => 'メモ保管庫'])

@section('content')
<div class="memos-page">
    <x-page-heading
        eyebrow="MEMOS"
        title="タイムスタンプメモ"
        description="書き留めた場面を、推し・タグ・年月から探せます。"
    />

    <div class="section-panel">
    {{-- フィルターバー --}}
    <div class="library-filter-panel">
        <x-oshi-filter-strip :oshis="$oshis" route-name="memos.index" />
        <form class="library-select-filters" method="GET" action="{{ route('memos.index') }}">
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
                <a href="{{ route('memos.index') }}" class="fav-filter-reset">リセット</a>
            @endif
        </form>
    </div>

    <p class="fav-count">{{ number_format($memos->total()) }} 件のタイムスタンプメモ</p>

    @if($memos->isEmpty())
        <div class="fav-empty">
            <x-icon name="note" />
            <p>タイムスタンプメモがまだありません。</p>
            <p class="fav-empty-sub">配信詳細ページで「現在位置をメモ」ボタンを押すと、ここに記録されます。</p>
            <a href="{{ route('watchlist.index', ['status' => 'watching']) }}" class="fav-empty-action">
                <x-icon name="play" />
                視聴中の配信を見る
            </a>
        </div>
    @else
        <div class="fav-list">
            @foreach($memos as $memo)
                <x-memo-library-card :memo="$memo" :archive-id="$watchItemMap[$memo->youtube_video_id] ?? null" />
            @endforeach
        </div>

        {{ $memos->links() }}
    @endif
    </div>{{-- /.section-panel --}}

</div>
@endsection

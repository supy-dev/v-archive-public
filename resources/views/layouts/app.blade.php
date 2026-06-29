<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'V-アーカイブ' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $isFocusMode = !empty($focusMode);
    $isArchiveDetail = request()->routeIs('archives.show');
@endphp
<body class="archive-body {{ $isFocusMode ? 'focus-mode' : '' }} {{ $isArchiveDetail ? 'archive-detail-view' : '' }}">
@php
    $currentRoute = request()->route()?->getName();
    $isHome = in_array($currentRoute, ['home', 'demo.home']);
    $isOshi = str_starts_with((string) $currentRoute, 'oshis.') || $currentRoute === 'demo.oshi';
    $isProfile = str_starts_with((string) $currentRoute, 'profile.');
    $profile = request()->user();
    $avatar = $profile?->avatar_url;
@endphp
<div class="app-shell" @if($isHome) x-data="archiveHome" @endif>
    @unless($isFocusMode)
    <aside class="sidebar" aria-label="メインナビゲーション">
        <a class="brand" href="{{ route('home') }}"><x-icon name="sparkle" weight="fill" /><strong>V-アーカイブ</strong></a>
        <nav>
            <a class="{{ $isHome ? 'active' : '' }}" href="{{ route('home') }}"><x-icon name="house" />ホーム</a>
            <a class="{{ $isOshi ? 'active' : '' }}" href="{{ route('oshis.index') }}"><x-icon name="heart" weight="fill" />推し管理</a>
            <a class="{{ in_array($currentRoute, ['archive.index', 'demo.archive']) ? 'active' : '' }}" href="{{ route('archive.index') }}"><x-icon name="archive" />新着アーカイブ</a>
            <a class="{{ str_starts_with((string) $currentRoute, 'watchlist.') || $currentRoute === 'demo.watchlist' ? 'active' : '' }}" href="{{ route('watchlist.index') }}"><x-icon name="list" />見るリスト</a>
            <a class="{{ in_array($currentRoute, ['favorites.index', 'demo.favorites']) ? 'active' : '' }}" href="{{ route('favorites.index') }}"><x-icon name="star" />神回・お気に入り</a>
            <a class="{{ $currentRoute === 'memos.index' ? 'active' : '' }}" href="{{ route('memos.index') }}"><x-icon name="note" />タイムスタンプメモ</a>
            <a class="{{ $currentRoute === 'videos.import.create' ? 'active' : '' }}" href="{{ route('videos.import.create') }}"><x-icon name="link" />動画をURLで追加</a>
            <a class="{{ $isProfile ? 'active' : '' }}" href="{{ route('profile.show') }}"><x-icon name="gear" />設定</a>
        </nav>
        <div class="profile-menu" x-data="{ open: false }">
            <button type="button" class="profile-trigger" @click="open = !open" :aria-expanded="open">
                @if($avatar)
                    <img class="avatar" src="{{ $avatar }}" alt="{{ $profile?->display_name ?? 'ユーザー' }}">
                @else
                    <span class="avatar avatar-fallback"><x-icon name="user" /></span>
                @endif
                <span><small>マイページ</small><b>{{ $profile?->display_name ?? '未設定' }}</b></span>
            </button>
            <div class="profile-popover" x-cloak x-show="open" @click.outside="open = false">
                <a href="{{ route('profile.show') }}">プロフィール</a>
                <button type="button" data-logout>ログアウト</button>
            </div>
        </div>
    </aside>
    @endunless

    <div class="app-body">
        @unless($isFocusMode)
        <header class="topbar">
            <p class="mobile-title">{{ $pageTitle ?? ($isHome ? 'ホーム' : 'V-アーカイブ') }}</p>
            @if($isHome)
                <label class="search-box">
                    <span class="sr-only">アーカイブを検索</span>
                    <x-icon name="search" />
                    <input type="search" placeholder="アーカイブを検索..." aria-label="アーカイブを検索" x-model="query">
                    <x-icon name="sliders" />
                </label>
            @else
                <form class="search-box" method="GET" action="{{ route('archive.index') }}" role="search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="アーカイブを検索..." aria-label="アーカイブを検索">
                    <button type="submit" aria-label="検索を実行"><x-icon name="sliders" /></button>
                </form>
            @endif
            <div class="top-actions">
                {{-- NOTIFICATIONS_PAUSED: 通知機能の実装後にボタンを戻す。 --}}
                {{--
                <button type="button" aria-label="通知"><x-icon name="bell" /></button>
                --}}
                <a href="{{ route('profile.show') }}">
                    @if($avatar)
                        <img class="avatar" src="{{ $avatar }}" alt="マイページ">
                    @else
                        <span class="avatar avatar-fallback"><x-icon name="user" /></span>
                    @endif
                </a>
            </div>
        </header>
        @endunless

        <main class="page-main">
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>

    <div
        class="mobile-more"
        x-data="{
            open: false,
            show() {
                this.open = true;
                this.$nextTick(() => this.$refs.closeButton.focus());
            },
            close() {
                this.open = false;
                this.$nextTick(() => this.$refs.trigger.focus());
            }
        }"
        @keydown.escape.window="if (open) close()"
    >
        <nav class="bottom-nav" aria-label="モバイルナビゲーション">
            <a class="{{ $isHome ? 'active' : '' }}" href="{{ route('home') }}"><x-icon name="house" /><span>ホーム</span></a>
            <a class="{{ $currentRoute === 'archive.index' ? 'active' : '' }}" href="{{ route('archive.index') }}"><x-icon name="archive" /><span>新着</span></a>
            <a class="{{ str_starts_with((string) $currentRoute, 'watchlist.') ? 'active' : '' }}" href="{{ route('watchlist.index') }}"><x-icon name="list" /><span>見るリスト</span></a>
            <a class="{{ $currentRoute === 'favorites.index' ? 'active' : '' }}" href="{{ route('favorites.index') }}"><x-icon name="star" /><span>神回</span></a>
            <button
                type="button"
                class="{{ $isOshi || $currentRoute === 'memos.index' || $currentRoute === 'videos.import.create' || $isProfile ? 'active' : '' }}"
                x-ref="trigger"
                @click="show()"
                :aria-expanded="open"
                aria-controls="mobile-more-sheet"
            >
                <x-icon name="dots" /><span>その他</span>
            </button>
        </nav>

        <div
            class="mobile-more-backdrop"
            x-cloak
            x-show="open"
            x-transition.opacity
            @click="close()"
            aria-hidden="true"
        ></div>
        <section
            id="mobile-more-sheet"
            class="mobile-more-sheet"
            x-cloak
            x-show="open"
            x-transition
            role="dialog"
            aria-modal="true"
            aria-label="その他のメニュー"
            @click.outside="close()"
        >
            <header>
                <div>
                    <p>メニュー</p>
                    <h2>その他の機能</h2>
                </div>
                <button type="button" class="mobile-more-close" x-ref="closeButton" @click="close()" aria-label="メニューを閉じる">
                    <x-icon name="x" />
                </button>
            </header>
            <nav aria-label="その他の機能">
                <a href="{{ route('oshis.index') }}"><x-icon name="heart" weight="fill" /><span><b>推し管理</b><small>推しとチャンネルを管理</small></span><x-icon name="chevron-right" /></a>
                <a href="{{ route('memos.index') }}"><x-icon name="note" /><span><b>タイムスタンプメモ</b><small>残した場面を振り返る</small></span><x-icon name="chevron-right" /></a>
                <a href="{{ route('videos.import.create') }}"><x-icon name="link" /><span><b>動画をURLで追加</b><small>特定の動画を直接取り込む</small></span><x-icon name="chevron-right" /></a>
                <a href="{{ route('profile.show') }}"><x-icon name="gear" /><span><b>設定</b><small>プロフィールと同期設定</small></span><x-icon name="chevron-right" /></a>
            </nav>
        </section>
    </div>
    <footer class="app-footer">
        <a href="{{ route('legal.privacy') }}">プライバシーポリシー</a>
        <span aria-hidden="true">·</span>
        <a href="{{ route('legal.terms') }}">利用規約</a>
        <span aria-hidden="true">·</span>
        {{-- お問い合わせURL: Googleフォームに差し替える --}}
        <a href="https://forms.gle/jYcZjbxfyuWqNLUP6" target="_blank" rel="noopener noreferrer">お問い合わせ</a>
    </footer>
</div>
</body>
</html>

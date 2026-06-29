<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'V-アーカイブ' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="guest-page">
    <main class="guest-frame">
        <div class="guest-card">
            <div class="guest-card-hero">
                <div class="guest-hero-glow" aria-hidden="true"></div>
                <x-icon name="sparkle" class="guest-spark guest-spark-a" />
                <x-icon name="sparkle" class="guest-spark guest-spark-b" />
                <div class="guest-hero-brand">
                    <x-icon name="archive" />
                    V-アーカイブ
                </div>
                <p class="guest-hero-tagline">配信の記憶を、自分だけのアーカイブへ。</p>
            </div>
            <div class="guest-card-body">
                {{ $slot ?? '' }}
                @yield('content')
            </div>
        </div>
    </main>
</body>
</html>

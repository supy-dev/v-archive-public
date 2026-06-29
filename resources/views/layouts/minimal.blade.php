<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'V-アーカイブ' }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="archive-body">
<div class="minimal-shell">
    <header class="minimal-header">
        <a class="brand" href="{{ url('/') }}">
            <x-icon name="sparkle" weight="fill" /><strong>V-アーカイブ</strong>
        </a>
    </header>

    <main class="minimal-main">
        @yield('content')
    </main>

    <footer class="minimal-footer">
        <a href="{{ url('/privacy') }}">プライバシーポリシー</a>
        <span aria-hidden="true">·</span>
        <a href="{{ url('/terms') }}">利用規約</a>
        <span aria-hidden="true">·</span>
        {{-- お問い合わせURL: Googleフォームに差し替える --}}
        <a href="https://forms.gle/jYcZjbxfyuWqNLUP6" target="_blank" rel="noopener noreferrer">お問い合わせ</a>
    </footer>
</div>
</body>
</html>

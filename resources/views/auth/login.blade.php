@extends('layouts.guest')

@section('content')
    <h2 class="guest-form-heading">ログイン</h2>
    <p class="guest-form-sub">GoogleアカウントでV-アーカイブを使い始められます。</p>

    <div data-auth-error class="mb-4 hidden rounded bg-red-50 p-3 text-sm text-red-700"></div>
    <div data-auth-notice class="mb-4 hidden rounded bg-blue-50 p-3 text-sm text-blue-700"></div>

    <button type="button" data-google-login class="btn-google">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
        </svg>
        Googleでログイン
    </button>

    <p class="guest-trust-note">
        <a href="{{ route('legal.privacy') }}">プライバシーポリシー</a>
        と
        <a href="{{ route('legal.terms') }}">利用規約</a>
        に同意します。
    </p>

    <p class="guest-only-note">現在はGoogleログインのみご利用いただけます。</p>

    {{--
        GOOGLE_ONLY_LAUNCH:
        初期リリースではメール＋パスワード認証を停止する。
        再開時は routes/web.php の登録・再設定ルートと resources/js/app.js のイベント処理も戻す。

    <div class="my-4 text-center text-xs text-gray-400">または</div>

    <form data-login-form class="space-y-4">
        <div>
            <label class="block text-sm font-medium" for="email">メールアドレス</label>
            <input id="email" name="email" type="email" required autocomplete="email"
                   class="field-control mt-1 w-full rounded border border-gray-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium" for="password">パスワード</label>
            <input id="password" name="password" type="password" required minlength="8" autocomplete="current-password"
                   class="field-control mt-1 w-full rounded border border-gray-300 px-3 py-2">
        </div>
        <button type="submit" class="button-primary w-full px-4 py-2">
            ログイン
        </button>
    </form>

    <div class="mt-4 flex justify-between text-sm">
        <a href="{{ route('register') }}" class="link-brand hover:underline">新規登録</a>
        <a href="{{ route('password.request') }}" class="link-brand hover:underline">パスワードをお忘れですか？</a>
    </div>

    <button type="button" data-resend-confirmation class="link-brand mt-3 hidden text-sm hover:underline">
        確認メールを再送する
    </button>
    --}}
@endsection

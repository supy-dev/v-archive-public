@extends('layouts.guest')

@section('content')
    <h2 class="mb-4 text-lg font-semibold">新規登録</h2>

    <div data-auth-error class="mb-4 hidden rounded bg-red-50 p-3 text-sm text-red-700"></div>
    <div data-auth-notice class="mb-4 hidden rounded bg-blue-50 p-3 text-sm text-blue-700"></div>

    <button type="button" data-google-login
            class="mb-4 flex w-full items-center justify-center gap-2 rounded border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">
        Googleで登録
    </button>

    <div class="my-4 text-center text-xs text-gray-400">または</div>

    {{-- 8文字以上はクライアント検証（UX補助）。最終的な強度チェックは Supabase 側で担保。 --}}
    <form data-register-form class="space-y-4">
        <div>
            <label class="block text-sm font-medium" for="email">メールアドレス</label>
            <input id="email" name="email" type="email" required autocomplete="email"
                   class="field-control mt-1 w-full rounded border border-gray-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium" for="password">パスワード（8文字以上）</label>
            <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"
                   class="field-control mt-1 w-full rounded border border-gray-300 px-3 py-2">
            <p class="mt-1 text-xs text-gray-500">8文字以上で設定してください。</p>
        </div>
        <button type="submit" class="button-primary w-full px-4 py-2">
            登録する
        </button>
    </form>

    <p class="mt-4 text-sm">
        すでにアカウントをお持ちですか？
        <a href="{{ route('login') }}" class="link-brand hover:underline">ログイン</a>
    </p>
@endsection

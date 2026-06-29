@extends('layouts.guest')

@section('content')
    <h2 class="mb-4 text-lg font-semibold">新しいパスワードの設定</h2>

    <div data-auth-error class="mb-4 hidden rounded bg-red-50 p-3 text-sm text-red-700"></div>
    <div data-auth-notice class="mb-4 hidden rounded bg-blue-50 p-3 text-sm text-blue-700"></div>

    {{-- 8文字以上はクライアント検証。最終的な拒否は Supabase が担保（FR-001c）。 --}}
    <form data-reset-form class="space-y-4">
        <div>
            <label class="block text-sm font-medium" for="password">新しいパスワード（8文字以上）</label>
            <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"
                   class="field-control mt-1 w-full rounded border border-gray-300 px-3 py-2">
        </div>
        <button type="submit" class="button-primary w-full px-4 py-2">
            パスワードを更新
        </button>
    </form>

    <p class="mt-4 text-sm">
        <a href="{{ route('login') }}" class="link-brand hover:underline">ログインに戻る</a>
    </p>
@endsection

@extends('layouts.guest')

@section('content')
    <h2 class="mb-4 text-lg font-semibold">パスワードの再設定</h2>

    <div data-auth-error class="mb-4 hidden rounded bg-red-50 p-3 text-sm text-red-700"></div>
    {{-- アカウントの有無を推測させない一様な案内を表示する（FR-001b）。 --}}
    <div data-auth-notice class="mb-4 hidden rounded bg-blue-50 p-3 text-sm text-blue-700"></div>

    <p class="mb-4 text-sm text-gray-600">
        登録メールアドレスを入力してください。再設定用のリンクをお送りします。
    </p>

    <form data-forgot-form class="space-y-4">
        <div>
            <label class="block text-sm font-medium" for="email">メールアドレス</label>
            <input id="email" name="email" type="email" required autocomplete="email"
                   class="field-control mt-1 w-full rounded border border-gray-300 px-3 py-2">
        </div>
        <button type="submit" class="button-primary w-full px-4 py-2">
            再設定メールを送信
        </button>
    </form>

    <p class="mt-4 text-sm">
        <a href="{{ route('login') }}" class="link-brand hover:underline">ログインに戻る</a>
    </p>
@endsection

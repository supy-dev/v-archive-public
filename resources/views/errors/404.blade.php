@extends('layouts.minimal', ['title' => 'ページが見つかりません — V-アーカイブ'])

@section('content')
<div class="error-page">
    <div class="error-icon"><x-icon name="file-x" weight="thin" /></div>
    <h1 class="error-code">404</h1>
    <p class="error-message">お探しのページが見つかりませんでした。</p>
    <p class="error-sub">URLが変更されたか、削除された可能性があります。</p>
    <a class="btn btn-primary" href="{{ url('/') }}">ホームへ戻る</a>
</div>
@endsection

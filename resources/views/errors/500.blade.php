@extends('layouts.minimal', ['title' => 'エラーが発生しました — V-アーカイブ'])

@section('content')
<div class="error-page">
    <div class="error-icon"><x-icon name="warning-circle" weight="thin" /></div>
    <h1 class="error-code">500</h1>
    <p class="error-message">エラーが発生しました。</p>
    <p class="error-sub">時間をおいて再度お試しください。問題が続く場合はお問い合わせください。</p>
    <a class="btn btn-primary" href="{{ url('/') }}">ホームへ戻る</a>
</div>
@endsection

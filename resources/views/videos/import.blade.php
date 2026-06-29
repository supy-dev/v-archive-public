@extends('layouts.app', [
    'title' => '動画をURLで追加 | V-アーカイブ',
    'pageTitle' => '動画をURLで追加',
])

@section('content')
<div class="import-page">
    <div class="import-card">
        <div class="import-header">
            <x-icon name="link" />
            <div>
                <h1>動画をURLで追加</h1>
                <p>チャンネル登録なしで、特定の動画を直接アーカイブに追加できます。切り抜き元の動画管理などにご活用ください。</p>
            </div>
        </div>

        @if($errors->has('video_url'))
            <div class="flash flash-error" role="alert">{{ $errors->first('video_url') }}</div>
        @endif

        <form method="POST" action="{{ route('videos.import.store') }}">
            @csrf
            <div class="import-form">
                <label for="video-url">YouTube 動画 URL</label>
                <div class="import-form-row">
                    <input
                        id="video-url"
                        type="text"
                        name="video_url"
                        value="{{ old('video_url') }}"
                        placeholder="https://www.youtube.com/watch?v=..."
                        autocomplete="off"
                        autofocus
                    >
                    <button type="submit" class="button-primary">追加する</button>
                </div>
                <p class="import-hint">
                    対応形式：通常の動画（watch?v=）、短縮URL（youtu.be/）、ライブ配信（/live/）、ショート（/shorts/）
                </p>
            </div>
        </form>
    </div>
</div>
@endsection

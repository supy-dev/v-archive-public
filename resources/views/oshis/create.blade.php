@extends('layouts.app', ['title' => '推しを追加 | V-アーカイブ', 'pageTitle' => '推しを追加'])

@section('content')
<div class="oshi-form-page">
    <a href="{{ route('oshis.index') }}" class="oshi-detail-back"><x-icon name="arrow-left" />推し一覧</a>
    <x-page-heading
        eyebrow="NEW OSHI"
        title="推しを追加"
        description="推しを追加したあと、YouTubeチャンネルの登録へ進みます。"
    />
    <section class="oshi-form-panel">
        @include('oshis._form')
    </section>
</div>
@endsection

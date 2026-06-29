@extends('layouts.app', ['title' => '推しを編集 | V-アーカイブ', 'pageTitle' => '推しを編集'])

@section('content')
<div class="oshi-form-page">
    <a href="{{ route('oshis.show', $oshi) }}" class="oshi-detail-back"><x-icon name="arrow-left" />推し詳細</a>
    <x-page-heading
        eyebrow="EDIT OSHI"
        title="推しを編集"
        description="推しの名前、グループ、識別カラー、メモを変更できます。"
    />
    <section class="oshi-form-panel">
        @include('oshis._form')
    </section>
</div>
@endsection

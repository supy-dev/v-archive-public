@extends('layouts.app', ['title' => '推し管理 | V-アーカイブ', 'pageTitle' => '推し管理'])

@section('content')
<div class="oshi-index-page">
    <x-page-heading
        eyebrow="OSHIS"
        title="推し管理"
        description="推しとYouTubeチャンネルを登録し、アーカイブの取り込みを管理します。"
    >
        <x-slot:actions>
            <a href="{{ route('oshis.create') }}" class="button-primary page-heading-button">
                <x-icon name="plus" />推しを追加
            </a>
        </x-slot:actions>
    </x-page-heading>

    @if($oshis->isEmpty())
        <div class="oshi-index-empty">
            <span><x-icon name="heart" weight="fill" /></span>
            <h2>まだ推しが登録されていません</h2>
            <p>推しとYouTubeチャンネルを登録すると、新着配信を自動で整理できます。</p>
            <a href="{{ route('oshis.create') }}" class="button-primary">最初の推しを追加する</a>
        </div>
    @else
        <div class="oshi-index-content {{ $oshis->count() === 1 ? 'is-sparse' : '' }}">
            <div class="oshi-index-grid">
                @foreach($oshis as $oshi)
                    @php
                        $mainUserChannel = $oshi->userChannels->first();
                        $mainChannel = $mainUserChannel?->youtubeChannel;
                    @endphp
                    <a
                        href="{{ route('oshis.show', $oshi) }}"
                        class="oshi-index-card oshi-identity-card {{ $oshi->color_id?->cssClass() ?? 'oshi-color-default' }}"
                    >
                        <div class="oshi-index-card-main">
                            @if($mainChannel?->thumbnail_url)
                                <img src="{{ $mainChannel->thumbnail_url }}" alt="" class="oshi-index-avatar">
                            @else
                                <span class="oshi-index-avatar oshi-index-avatar-fallback"><x-icon name="heart" weight="fill" /></span>
                            @endif

                            <div class="oshi-index-copy">
                                <div class="oshi-index-title">
                                    <span class="oshi-color-dot" aria-hidden="true"></span>
                                    <h2 class="oshi-name">{{ $oshi->name }}</h2>
                                </div>
                                @if($oshi->group_name)
                                    <p>{{ $oshi->group_name }}</p>
                                @endif
                            </div>
                            <x-icon name="chevron-right" class="oshi-index-chevron" />
                        </div>

                        <div class="oshi-index-meta">
                            <span><x-icon name="youtube" />{{ $oshi->user_channels_count }} チャンネル</span>
                            @if($mainChannel)
                                <span class="oshi-index-main-channel"><x-icon name="crown" />{{ $mainChannel->title }}</span>
                            @else
                                <span class="oshi-index-unregistered">チャンネル未登録</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            @if($oshis->count() === 1)
                @php
                    $onlyOshi = $oshis->first();
                    $onlyMainUserChannel = $onlyOshi->userChannels->first();
                    $onlyMainChannel = $onlyMainUserChannel?->youtubeChannel;
                @endphp
                <aside class="oshi-index-guide">
                    <div class="oshi-index-guide-heading">
                        <span><x-icon name="sparkle" weight="fill" /></span>
                        <div>
                            <p>次のステップ</p>
                            <h2>推し活の準備を整えましょう</h2>
                        </div>
                    </div>
                    <div class="oshi-index-guide-status">
                        <span>取り込み状況</span>
                        @if($onlyMainChannel)
                            <b class="oshi-index-sync-state {{ $onlyMainChannel->sync_status->badgeClass() }}">
                                {{ $onlyMainChannel->sync_status->label() }}
                            </b>
                        @else
                            <b class="oshi-index-sync-state is-unregistered">チャンネル未登録</b>
                        @endif
                    </div>
                    <div class="oshi-index-guide-links">
                        <a href="{{ route('oshis.show', $onlyOshi) }}">
                            <x-icon name="youtube" />
                            <span><b>チャンネル設定</b><small>同期状況と登録内容を確認</small></span>
                            <x-icon name="chevron-right" />
                        </a>
                        <a href="{{ route('archive.index', ['oshi_id' => $onlyOshi->id]) }}">
                            <x-icon name="archive" />
                            <span><b>新着アーカイブ</b><small>届いた配信を整理する</small></span>
                            <x-icon name="chevron-right" />
                        </a>
                    </div>
                </aside>
            @endif
        </div>
    @endif
</div>
@endsection

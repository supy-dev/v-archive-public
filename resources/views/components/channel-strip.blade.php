@props(['channels', 'count'])
@php
    $remainingCount = max(0, $count - $channels->count());
@endphp
<a href="{{ route('oshis.index') }}" class="channel-strip channel-strip-link" aria-label="推しと登録チャンネルを管理">
    <div class="channel-count">
        <x-icon name="heart" weight="fill" />
        <span>
            <small>推し・登録チャンネル</small>
            @if($count > 0)
                <b>{{ $count }}<em>チャンネル</em></b>
            @else
                <b class="channel-empty-label">推しを登録する</b>
            @endif
        </span>
    </div>
    <div class="channel-strip-action">
        @if($count > 0)
            <div class="avatar-stack" aria-hidden="true">
                @foreach($channels as $userChannel)
                    @if($userChannel->youtubeChannel?->thumbnail_url)
                        <img src="{{ $userChannel->youtubeChannel->thumbnail_url }}" alt="">
                    @endif
                @endforeach
                @if($remainingCount > 0)<span>+{{ $remainingCount }}</span>@endif
            </div>
        @else
            <span class="channel-add-hint"><x-icon name="plus" />追加</span>
        @endif
        <x-icon name="chevron-right" />
    </div>
</a>

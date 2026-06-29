@props(['memo', 'archiveId' => null])

@php
    /** @var \App\Models\TimestampMemo $memo */
    $video = $memo->youtubeVideo;
    $channel = $video?->youtubeChannel;
    $userChannel = $channel?->userChannels?->first();
    $oshi = $userChannel?->oshi;
    $oshiClass = $oshi?->color_id?->cssClass() ?? 'oshi-color-default';
    $videoId = $video?->youtube_video_id ?? '';
    $youtubeUrl = "https://www.youtube.com/watch?v={$videoId}&t={$memo->seconds}s";
    $colorMap = ['mint' => 'tag-mint', 'blue' => 'tag-blue', 'purple' => 'tag-purple', 'orange' => 'tag-orange', 'pink' => 'tag-pink', 'green' => 'tag-green'];
@endphp

<article class="memo-library-card {{ $oshiClass }}">
    <div class="memo-library-card-top">
        <span class="memo-library-timestamp">
            <x-icon name="timer" />{{ $memo->seconds_label }}
        </span>
        <div class="memo-library-meta">
            @if($archiveId)
                <a href="{{ route('archives.show', $archiveId) }}" class="memo-library-title-link">
                    <h3 class="memo-library-title">{{ $video?->title ?? '（削除済み）' }}</h3>
                </a>
            @else
                <h3 class="memo-library-title">{{ $video?->title ?? '（削除済み）' }}</h3>
            @endif
            <div class="memo-library-identities">
                @if($oshi)
                    <span class="memo-oshi-chip">
                        <span class="oshi-color-dot" aria-hidden="true"></span>{{ $oshi->name }}
                    </span>
                @endif
                @if($channel)<span class="memo-library-channel">{{ $channel->title }}</span>@endif
            </div>
        </div>
        @if($memo->is_favorite)
            <span class="memo-favorite-state" title="お気に入りメモ">
                <x-icon name="star" weight="fill" /><span>お気に入り</span>
            </span>
        @endif
    </div>

    <p class="memo-library-body">{{ $memo->body }}</p>

    @if($memo->tags->isNotEmpty())
        <div class="tag-list">
            @foreach($memo->tags as $tag)
                <span class="tag {{ $colorMap[$tag->color] ?? 'tag-purple' }}">{{ $tag->name }}</span>
            @endforeach
        </div>
    @endif

    <footer class="memo-library-footer">
        <div class="memo-library-actions">
            @if($archiveId)
                <a href="{{ route('archives.show', $archiveId) }}" class="memo-primary-link">
                    <x-icon name="play" />配信を見る
                </a>
            @endif
            <a href="{{ $youtubeUrl }}" target="_blank" rel="noopener noreferrer" class="memo-youtube-link">
                <x-icon name="youtube" />YouTubeで開く
            </a>
        </div>
        <time class="memo-library-date">{{ $memo->created_at?->format('Y年n月j日') }}</time>
    </footer>
</article>

@extends('layouts.app', [
    'title' => $oshi->name.' | V-アーカイブ',
    'pageTitle' => '推し詳細',
])

@section('content')
@php
    $oshiColorClass = $oshi->color_id?->cssClass() ?? 'oshi-color-default';
    $mainChannel = $mainUserChannel?->youtubeChannel;
    $channelCount = $oshi->userChannels->count();
@endphp

<div class="oshi-detail-page {{ $oshiColorClass }}">
    @if(session('success'))
        <div class="flash flash-success" role="alert">{{ session('success') }}</div>
    @endif
    @if(session('info'))
        <div class="flash flash-info" role="alert">{{ session('info') }}</div>
    @endif

    <a href="{{ route('oshis.index') }}" class="oshi-detail-back">
        <x-icon name="arrow-left" />
        推し一覧
    </a>

    <section class="oshi-profile-hero" aria-labelledby="oshi-name">
        <div class="oshi-profile-main">
            @if($mainChannel?->thumbnail_url)
                <img
                    class="oshi-profile-avatar"
                    src="{{ $mainChannel->thumbnail_url }}"
                    alt="{{ $mainChannel->title }}"
                >
            @else
                <span class="oshi-profile-avatar oshi-profile-avatar-fallback" aria-hidden="true">
                    <x-icon name="heart" weight="fill" />
                </span>
            @endif

            <div class="oshi-profile-copy">
                <div class="oshi-profile-title-row">
                    <span class="oshi-color-dot" aria-hidden="true"></span>
                    <h1 id="oshi-name">{{ $oshi->name }}</h1>
                </div>
                @if($oshi->group_name)
                    <p class="oshi-profile-group">{{ $oshi->group_name }}</p>
                @endif
                <div class="oshi-profile-stats" aria-label="登録状況">
                    <span><b>{{ $channelCount }}</b> 登録チャンネル</span>
                    <span><b>{{ $syncEnabledCount }}</b> 同期中</span>
                    @if($mainChannel)
                        <span>メイン: <b>{{ $mainChannel->title }}</b></span>
                    @endif
                </div>
                @if($oshi->memo)
                    <p class="oshi-profile-memo">{{ $oshi->memo }}</p>
                @endif
            </div>

            <a href="{{ route('oshis.edit', $oshi) }}" class="oshi-profile-edit">
                <x-icon name="pencil" />
                <span>推し情報を編集</span>
            </a>
        </div>

        <nav class="oshi-value-links" aria-label="{{ $oshi->name }}のコンテンツ">
            <a href="{{ route('archive.index', ['oshi_id' => $oshi->id]) }}">
                <x-icon name="archive" />
                <span><b>アーカイブ</b><small>この推しの配信を見る</small></span>
                <x-icon name="chevron-right" />
            </a>
            <a href="{{ route('memos.index', ['oshi_id' => $oshi->id]) }}">
                <x-icon name="note" />
                <span><b>タイムスタンプメモ</b><small>残したメモを振り返る</small></span>
                <x-icon name="chevron-right" />
            </a>
            <a href="{{ route('favorites.index', ['oshi_id' => $oshi->id]) }}">
                <x-icon name="star" />
                <span><b>神回・お気に入り</b><small>大切な場面をまとめて見る</small></span>
                <x-icon name="chevron-right" />
            </a>
        </nav>
    </section>

    <div class="oshi-detail-layout">
        <section class="oshi-channel-section">
            <div class="oshi-section-heading">
                <div>
                    <p class="page-eyebrow">CHANNELS</p>
                    <h2>登録チャンネル</h2>
                    <p>動画を取り込むチャンネルと同期設定を管理します。</p>
                </div>
                <span class="oshi-section-count">{{ $channelCount }}件</span>
            </div>

            @if($oshi->userChannels->isEmpty())
                <div class="oshi-channel-empty">
                    <x-icon name="youtube" />
                    <p>まだチャンネルが登録されていません。</p>
                    <span>下のフォームから、YouTubeチャンネルを追加してください。</span>
                </div>
            @else
                <div class="oshi-channel-list">
                    @foreach($oshi->userChannels as $userChannel)
                        @php
                            $ch = $userChannel->youtubeChannel;
                            $youtubeUrl = 'https://www.youtube.com/channel/'.$ch->youtube_channel_id;
                        @endphp
                        <article class="oshi-channel-card" x-data="{ menuOpen: false }">
                            <div class="oshi-channel-identity">
                                @if($ch->thumbnail_url)
                                    <img src="{{ $ch->thumbnail_url }}" alt="" class="oshi-channel-avatar">
                                @else
                                    <span class="oshi-channel-avatar oshi-channel-avatar-fallback"><x-icon name="youtube" /></span>
                                @endif

                                <div class="oshi-channel-copy">
                                    <div class="oshi-channel-title-line">
                                        <h3>{{ $ch->title }}</h3>
                                        @if($userChannel->is_main)
                                            <span class="oshi-main-badge"><x-icon name="crown" />メイン</span>
                                        @endif
                                        <span class="oshi-sync-badge {{ $ch->sync_status->badgeClass() }}">
                                            @if($ch->sync_status === \App\Enums\ChannelSyncStatus::Pending)
                                                <x-icon name="spinner" class="animate-spin" />
                                            @endif
                                            {{ $ch->sync_status->label() }}
                                        </span>
                                    </div>
                                    <div class="oshi-channel-subline">
                                        @if($ch->handle)
                                            <span>{{ '@'.$ch->handle }}</span>
                                        @endif
                                        <a href="{{ $youtubeUrl }}" target="_blank" rel="noopener noreferrer">
                                            YouTubeで見る <x-icon name="external" />
                                        </a>
                                    </div>
                                    @if($ch->sync_status === \App\Enums\ChannelSyncStatus::Synced && $ch->last_synced_at)
                                        <p class="oshi-channel-sync-note">最終同期: {{ $ch->last_synced_at->diffForHumans() }}</p>
                                    @elseif($ch->sync_status === \App\Enums\ChannelSyncStatus::Error && $ch->sync_error_message)
                                        <p class="oshi-channel-error">{{ $ch->sync_error_message }}</p>
                                    @endif
                                </div>

                                <div class="oshi-channel-menu-wrap">
                                    <button
                                        type="button"
                                        class="oshi-channel-menu-trigger"
                                        aria-label="{{ $ch->title }}の操作"
                                        :aria-expanded="menuOpen"
                                        @click="menuOpen = !menuOpen"
                                    ><x-icon name="dots" /></button>
                                    <div class="oshi-channel-menu" x-cloak x-show="menuOpen" @click.outside="menuOpen = false">
                                        @unless($userChannel->is_main)
                                            <form method="POST" action="{{ route('oshis.channels.setMain', [$oshi, $userChannel]) }}">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit"><x-icon name="crown" />メインに設定</button>
                                            </form>
                                        @endunless
                                        <form
                                            method="POST"
                                            action="{{ route('oshis.channels.destroy', [$oshi, $userChannel]) }}"
                                            onsubmit="return confirm('「{{ $ch->title }}」の登録を解除しますか？')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="danger"><x-icon name="trash" />登録を解除</button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <form
                                method="POST"
                                action="{{ route('oshis.channels.update', [$oshi, $userChannel]) }}"
                                class="oshi-channel-settings"
                                data-auto-submit
                            >
                                @csrf
                                @method('PATCH')
                                <label class="oshi-toggle-setting">
                                    <span><b>動画を自動同期</b><small>新しい配信をV-アーカイブへ取り込みます</small></span>
                                    <input type="hidden" name="sync_enabled" value="0">
                                    <input type="checkbox" name="sync_enabled" value="1" @checked($userChannel->sync_enabled)>
                                    <i aria-hidden="true"></i>
                                </label>
                                <span class="inline-submit-status" data-submit-status role="status" aria-live="polite"></span>
                                {{-- NOTIFICATIONS_PAUSED: 通知配信の実装後にトグルを戻す。 --}}
                                {{--
                                <label class="oshi-toggle-setting">
                                    <span><b>通知</b><small>通知設定をこのチャンネルに保存します</small></span>
                                    <input type="checkbox" name="notify_enabled" value="1" @checked($userChannel->notify_enabled) onchange="this.form.submit()">
                                    <i aria-hidden="true"></i>
                                </label>
                                --}}
                            </form>

                            @if($ch->is_fetching_older || $ch->oldest_page_token !== null || $ch->oldest_fetched_at !== null)
                                <div class="oshi-channel-history">
                                    @if($ch->is_fetching_older)
                                        <span class="oshi-fetching-badge">
                                            <x-icon name="spinner" class="animate-spin" />取得中です。しばらくお待ちください
                                        </span>
                                    @elseif($ch->oldest_page_token !== null)
                                        <form method="POST" action="{{ route('oshis.channels.fetchOlder', [$oshi, $userChannel]) }}">
                                            @csrf
                                            <button type="submit"><x-icon name="refresh" />過去の動画をさらに取得</button>
                                        </form>
                                    @else
                                        <span><x-icon name="check" />取得できる動画はすべて同期済みです</span>
                                    @endif
                                </div>
                            @endif

                            @if($ch->unavailable_videos_count > 0)
                                <div class="oshi-channel-warning">
                                    <x-icon name="warning" />
                                    <span>{{ $ch->unavailable_videos_count }}件の動画はYouTubeで再生できません。保存済みメモは確認できます。</span>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif

            <details class="oshi-channel-add" @if($oshi->userChannels->isEmpty() || $errors->has('channel_url')) open @endif>
                <summary>
                    <span><x-icon name="plus" /><b>チャンネルを追加</b></span>
                    <x-icon name="chevron-right" class="oshi-add-chevron" />
                </summary>
                <div class="oshi-channel-add-body">
                    <p>YouTubeのチャンネルURL、または <code>@handle</code> を入力してください。</p>
                    @if($errors->has('channel_url'))
                        <p class="oshi-form-error">{{ $errors->first('channel_url') }}</p>
                    @endif
                    <form method="POST" action="{{ route('oshis.channels.store', $oshi) }}">
                        @csrf
                        <label for="channel-url">チャンネルURL / ハンドル</label>
                        <div>
                            <input
                                id="channel-url"
                                type="text"
                                name="channel_url"
                                value="{{ old('channel_url') }}"
                                placeholder="https://youtube.com/@handle"
                                autocomplete="off"
                            >
                            <button type="submit" class="button-primary">登録する</button>
                        </div>
                    </form>
                </div>
            </details>
        </section>

        <aside class="oshi-detail-aside">
            <section class="oshi-aside-card">
                <h2>この推しについて</h2>
                <dl>
                    <div><dt>推し色</dt><dd><span class="oshi-color-dot"></span>{{ $oshi->color_id?->label() ?? '未設定' }}</dd></div>
                    <div><dt>登録チャンネル</dt><dd>{{ $channelCount }}件</dd></div>
                    <div><dt>自動同期</dt><dd>{{ $syncEnabledCount }}件</dd></div>
                </dl>
                <a href="{{ route('oshis.edit', $oshi) }}"><x-icon name="pencil" />プロフィールを編集</a>
            </section>

            <section class="oshi-danger-zone">
                <h2>推しの登録を削除</h2>
                <p>紐づくチャンネル登録も削除されます。この操作は元に戻せません。</p>
                <form
                    method="POST"
                    action="{{ route('oshis.destroy', $oshi) }}"
                    onsubmit="return confirm('「{{ $oshi->name }}」を削除しますか？\n紐づくチャンネル登録もすべて削除されます。')"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit"><x-icon name="trash" />推しを削除</button>
                </form>
            </section>
        </aside>
    </div>
</div>
@endsection

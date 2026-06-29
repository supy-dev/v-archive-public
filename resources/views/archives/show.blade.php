@php
    use App\Enums\WatchStatus;
    /** @var \App\Models\UserWatchItem $watchItem */
    $video       = $watchItem->youtubeVideo;
    $channel     = $video->youtubeChannel ?? null;
    $videoId     = $video->youtube_video_id;
    $startSecs   = (int) ($watchItem->last_position_seconds ?? 0);
    $duration    = $video->duration_seconds;
    $isAvailable = $video->is_available;
    $status      = $watchItem->status;
    $positionUrl = route('watch-items.position.update', $watchItem, false);
    $csrfToken   = csrf_token();

    $durationLabel = $duration
        ? sprintf('%d:%02d:%02d', intdiv($duration, 3600), intdiv($duration % 3600, 60), $duration % 60)
        : '';
    $publishedAtDetail = $video->published_at?->format('Y/m/d H:i');
    $hasPosition = $startSecs > 0;
    $progressPercent = $duration ? min(100, (int) round(($startSecs / $duration) * 100)) : 0;
    $channelUrl = $channel
        ? ($channel->handle
            ? 'https://www.youtube.com/@'.ltrim($channel->handle, '@')
            : 'https://www.youtube.com/channel/'.$channel->youtube_channel_id)
        : null;

@endphp
@extends('layouts.app', [
    'title'     => ($video->title ?? 'アーカイブ') . ' | V-アーカイブ',
    'pageTitle' => '配信詳細',
    'focusMode' => false,
])

@section('content')
<div class="archive-show-page">

    {{-- フラッシュメッセージ --}}
    @if(session('success'))
        <div class="flash flash-success" role="alert">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="flash flash-error" role="alert">{{ session('error') }}</div>
    @endif

    {{-- ========== プレイヤーと配信情報 ========== --}}
    @if($isAvailable)
        <div
            class="detail-player-stage"
            x-data="youtubePlayer({
                watchItemId: '{{ $watchItem->id }}',
                videoId: '{{ $videoId }}',
                startSeconds: {{ $startSecs }},
                positionUrl: '{{ $positionUrl }}',
                csrfToken: '{{ $csrfToken }}'
            })"
        >
            <header class="show-page-header">
                <a href="{{ route('watchlist.index') }}" class="show-back-link">
                    <x-icon name="arrow-left" /><span>配信詳細</span>
                </a>
                <div class="show-page-actions">
                    <button
                        type="button"
                        class="btn-detail-action btn-detail-resume"
                        @click="seekTo({{ $startSecs }})"
                        title="{{ $hasPosition ? gmdate($startSecs >= 3600 ? 'G:i:s' : 'i:s', $startSecs).' から再生' : '最初から再生' }}"
                    >
                        <x-icon name="play" />続きから見る
                    </button>
                    <a
                        href="https://www.youtube.com/watch?v={{ $videoId }}{{ $hasPosition ? '&t='.$startSecs.'s' : '' }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="btn-detail-action btn-detail-youtube"
                    >
                        <x-icon name="youtube" />YouTubeで開く
                    </a>
                </div>
            </header>

            <div class="player-ratio-box">
                <div id="yt-player-{{ $watchItem->id }}" class="player-iframe"></div>

                {{-- ローディング表示（Alpine 管理） --}}
                <div class="player-loading" x-show="!ready" aria-live="polite">
                    <x-icon name="video" />
                    <span>プレイヤーを読み込み中...</span>
                </div>
            </div>

            <section class="show-info-card" aria-labelledby="archive-title">
                <div class="show-info-main">
                    @if($channel?->thumbnail_url)
                        <img class="show-channel-avatar" src="{{ $channel->thumbnail_url }}" alt="{{ $channel->title }}">
                    @endif
                    <div class="show-copy">
                        <h2 id="archive-title" class="show-title">{{ $video->title ?? '（削除済み）' }}</h2>
                        @if($channel)
                            <div class="show-channel-line">
                                <span>{{ $channel->title }}</span>
                                @if($video->video_type)<span class="tag tag-purple">{{ $video->video_type->label() }}</span>@endif
                            </div>
                        @endif
                    </div>

                    @if($channelUrl)
                        <a href="{{ $channelUrl }}" target="_blank" rel="noopener noreferrer" class="btn-channel-link">
                            チャンネルを見る<x-icon name="chevron-right" />
                        </a>
                    @endif

                    <dl class="show-metrics">
                        <div><dt>配信日</dt><dd>{{ $publishedAtDetail ?? '—' }}</dd></div>
                        <div><dt>メモ</dt><dd>{{ count($memos) }}件</dd></div>
                        <div><dt>アーカイブ時間</dt><dd>{{ $durationLabel ?: '—' }}</dd></div>
                    </dl>
                </div>

                <div class="show-progress-row">
                    <form method="POST" action="{{ route('watchlist.update', $watchItem) }}" class="show-status-form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="redirect_to" value="{{ route('archives.show', $watchItem) }}">
                        <label for="watch-status">視聴ステータス</label>
                        <select id="watch-status" name="status" onchange="this.form.submit()" class="show-status-select">
                            @foreach(WatchStatus::cases() as $st)
                                <option value="{{ $st->value }}" @selected($status === $st)>{{ $st->label() }}</option>
                            @endforeach
                        </select>
                    </form>
                    <span class="show-progress-pill">途中まで（{{ $progressPercent }}%）</span>
                    <span class="show-progress-time">{{ gmdate($startSecs >= 3600 ? 'G:i:s' : 'i:s', $startSecs) }} <i>/</i> {{ $durationLabel ?: '--:--' }}</span>
                    <div
                        x-data="{
                            isFavorite: {{ json_encode($watchItem->is_favorite) }},
                            loading: false,
                            error: null,
                            async toggle() {
                                if (this.loading) return;
                                this.loading = true;
                                this.error = null;
                                try {
                                    const res = await fetch('{{ route('archives.watch-item.favorite.update', $watchItem, false) }}', {
                                        method: 'PATCH',
                                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                    });
                                    if (!res.ok) throw new Error();
                                    const data = await res.json();
                                    this.isFavorite = data.is_favorite;
                                } catch {
                                    this.error = '更新に失敗しました';
                                } finally {
                                    this.loading = false;
                                }
                            }
                        }"
                        class="kamikai-toggle-wrap"
                    >
                        <button
                            type="button"
                            class="btn-best-stream btn-kamikai-toggle"
                            :class="{ 'active': isFavorite }"
                            @click="toggle()"
                            :disabled="loading"
                            :aria-pressed="isFavorite"
                            :aria-label="isFavorite ? '神回を解除' : '神回に登録'"
                        >
                            <x-icon name="crown" />
                            <span x-text="isFavorite ? '神回解除' : '神回に登録'"></span>
                        </button>
                        <p x-show="error" x-cloak x-text="error" class="kamikai-error" role="alert"></p>
                    </div>
                </div>
            </section>
        </div>
    @else
        <header class="show-page-header">
            <a href="{{ route('watchlist.index') }}" class="show-back-link"><x-icon name="arrow-left" /><span>配信詳細</span></a>
            <a href="https://www.youtube.com/watch?v={{ $videoId }}" target="_blank" rel="noopener noreferrer" class="btn-detail-action btn-detail-youtube"><x-icon name="youtube" />YouTubeで開く</a>
        </header>
        <div class="player-unavailable">
            <x-icon name="warning" />
            <p>現在 YouTube で再生できません。動画が非公開または削除された可能性があります。</p>
        </div>
    @endif

    {{-- ========== タイムスタンプメモセクション ========== --}}
    @php
        $storeUrl   = route('archives.memos.store', $watchItem, false);
        $videoId    = $watchItem->youtubeVideo?->youtube_video_id ?? '';
        $systemTagsJson = $systemTags->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug, 'color' => $t->color])->values()->toJson();
        $userTagsJson   = $userTags->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug, 'color' => $t->color])->values()->toJson();
    @endphp
    <div
        class="show-memos"
        x-data="memoManager({
            initialMemos: {{ json_encode($memos) }},
            storeUrl: '{{ $storeUrl }}',
            watchItemId: '{{ $watchItem->id }}',
            videoId: '{{ $videoId }}',
            csrfToken: '{{ csrf_token() }}',
            systemTags: {{ $systemTagsJson }},
            userTags: {{ $userTagsJson }}
        })"
    >
        <div class="show-section-header">
            <h3 class="show-section-title"><x-icon name="timer" />タイムスタンプメモ <span class="section-count" x-text="memos.length + '件'"></span></h3>
            <button
                type="button"
                class="btn-add-memo"
                @click="openCreate()"
                x-show="!showCreateForm"
                aria-label="メモを追加"
            >
                <x-icon name="plus" />現在位置をメモ
            </button>
        </div>

        {{-- エラー表示 --}}
        <p class="memo-error" x-show="createError" x-text="createError" role="alert"></p>

        {{-- 新規作成フォーム: まず記録、必要なときだけ詳細設定 --}}
        <template x-if="showCreateForm">
        <div
            class="memo-form-card memo-quick-composer"
            @keydown.meta.enter.prevent="saveCreate()"
            @keydown.ctrl.enter.prevent="saveCreate()"
        >
            <div class="memo-quick-row">
                <button
                    type="button"
                    class="memo-current-time"
                    @click="showAdvanced = !showAdvanced"
                    :aria-expanded="showAdvanced"
                    aria-controls="memo-advanced-settings"
                    title="再生位置を調整"
                >
                    <x-icon name="timer" /><span x-text="formatSeconds(createDraft.seconds)"></span>
                </button>
                <textarea
                    class="memo-input-body memo-quick-input"
                    x-ref="createBody"
                    x-init="$nextTick(() => $el.focus({ preventScroll: true }))"
                    x-model="createDraft.body"
                    placeholder="この場面についてメモ…"
                    maxlength="1000"
                    rows="2"
                    aria-label="メモ本文"
                ></textarea>
                <button
                    type="button"
                    class="btn-memo-save btn-memo-add"
                    @click="saveCreate()"
                    :disabled="submitting || !createDraft.body.trim()"
                    aria-label="メモを追加"
                >
                    <span x-show="submitting"><x-icon name="spinner" /></span>
                    メモを追加
                </button>
            </div>

            <div class="memo-quick-footer">
                <div class="memo-recent-tags" aria-label="最近使ったタグ">
                    <span>最近</span>
                    <template x-for="tag in allTags.slice(0, 3)" :key="tag.id">
                        <button
                            type="button"
                            class="tag"
                            :class="tagColorClass(tag.color) + (createDraft.tagIds.includes(tag.id) ? ' tag-selected' : ' tag-unselected')"
                            @click="toggleTag(createDraft, tag.id)"
                            :aria-pressed="createDraft.tagIds.includes(tag.id)"
                            x-text="tag.name"
                        ></button>
                    </template>
                </div>
                <div class="memo-quick-links">
                    <button type="button" class="btn-memo-details" @click="showAdvanced = !showAdvanced" :aria-expanded="showAdvanced">
                        <x-icon name="sliders" /><span x-text="showAdvanced ? '詳細を閉じる' : '詳細設定'"></span>
                    </button>
                    <button type="button" class="btn-memo-cancel" @click="closeCreate()">キャンセル</button>
                </div>
            </div>

            <div id="memo-advanced-settings" class="memo-advanced" x-show="showAdvanced" x-cloak>
                <div class="memo-form-row">
                    <label class="memo-form-label" for="create-seconds">再生位置（秒）</label>
                    <input
                        id="create-seconds"
                        type="number"
                        class="memo-input-seconds"
                        x-model.number="createDraft.seconds"
                        min="0"
                        aria-label="タイムスタンプ（秒）"
                    >
                    <span class="memo-seconds-preview" x-text="formatSeconds(createDraft.seconds)"></span>
                </div>

                <p class="memo-advanced-label">タグを選択</p>
                <div class="memo-tag-picker">
                    <template x-for="tag in allTags" :key="tag.id">
                        <button
                            type="button"
                            class="tag"
                            :class="tagColorClass(tag.color) + (createDraft.tagIds.includes(tag.id) ? ' tag-selected' : ' tag-unselected')"
                            @click="toggleTag(createDraft, tag.id)"
                            :aria-pressed="createDraft.tagIds.includes(tag.id)"
                            x-text="tag.name"
                        ></button>
                    </template>
                    <div class="memo-tag-input-wrap">
                        <input
                            type="text"
                            class="memo-tag-input"
                            x-model="createDraft.tagInput"
                            placeholder="+ 新しいタグ"
                            @keydown.enter.prevent="addNewTag(createDraft)"
                            @keydown.comma.prevent="addNewTag(createDraft)"
                            aria-label="新規タグ入力"
                        >
                        <template x-for="(name, i) in createDraft.newTagNames" :key="i">
                            <span class="tag tag-new">
                                <span x-text="name"></span>
                                <button type="button" @click="createDraft.newTagNames.splice(i,1)" aria-label="タグを削除"><x-icon name="x" /></button>
                            </span>
                        </template>
                    </div>
                </div>
            </div>
            <p class="memo-shortcut-hint">⌘ / Ctrl + Enter でも追加できます</p>
        </div>
        </template>

        {{-- メモ一覧 --}}
        <div class="memo-list">
            <template x-if="memos.length === 0 && !showCreateForm">
                <p class="memo-empty">まだメモがありません。「現在位置をメモ」ボタンで追加しましょう。</p>
            </template>

            <template x-for="memo in memos" :key="memo.id">
                <div class="memo-card" :class="newMemoId === memo.id ? 'memo-card-new' : ''" x-data="{ actionsOpen: false }">
                    {{-- 表示モード --}}
                    <div x-show="editingId !== memo.id">
                        <div class="memo-card-top">
                            <button
                                type="button"
                                class="memo-timestamp-btn"
                                @click="seekTo(memo.seconds)"
                                :aria-label="'再生位置 ' + memo.seconds_label + ' へシーク'"
                                :title="memo.seconds_label"
                            >
                                <x-icon name="timer" /><span x-text="memo.seconds_label"></span>
                            </button>
                            <div class="memo-card-content">
                                <p class="memo-body" x-text="memo.body"></p>
                                <div class="tag-list" x-show="memo.tags.length > 0">
                                    <template x-for="tag in memo.tags" :key="tag.id">
                                        <span class="tag" :class="tagColorClass(tag.color)" x-text="tag.name"></span>
                                    </template>
                                </div>
                            </div>
                            <div class="memo-card-actions">
                                <button
                                    type="button"
                                    class="btn-memo-icon"
                                    :class="memo.is_favorite ? 'btn-memo-fav-active' : 'btn-memo-fav'"
                                    @click="toggleFavorite(memo)"
                                    :aria-label="memo.is_favorite ? 'お気に入り解除' : 'お気に入り登録'"
                                    :aria-pressed="memo.is_favorite"
                                >
                                    <x-icon name="star" :weight="'memo.is_favorite ? fill : regular'" />
                                </button>
                                <button
                                    type="button"
                                    class="btn-memo-icon btn-memo-more"
                                    @click="actionsOpen = !actionsOpen"
                                    :aria-expanded="actionsOpen"
                                    aria-label="その他の操作"
                                >
                                    <x-icon name="dots" />
                                </button>
                                <div class="memo-actions-menu" x-show="actionsOpen" x-cloak @click.outside="actionsOpen = false">
                                    <button type="button" @click="actionsOpen = false; openEdit(memo)"><x-icon name="pencil" />編集</button>
                                    <a :href="memo.youtube_url" target="_blank" rel="noopener noreferrer"><x-icon name="external" />YouTubeで開く</a>
                                    <button type="button" class="memo-menu-delete" @click="actionsOpen = false; deleteMemo(memo)"><x-icon name="trash" />削除</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 編集モード --}}
                    <div x-show="editingId === memo.id" x-cloak>
                        <p class="memo-edit-error" x-show="editError" x-text="editError" role="alert"></p>
                        <div class="memo-form-row">
                            <label class="memo-form-label">秒</label>
                            <input
                                type="number"
                                class="memo-input-seconds"
                                x-model.number="editDraft.seconds"
                                min="0"
                            >
                            <span class="memo-seconds-preview" x-text="formatSeconds(editDraft.seconds)"></span>
                        </div>
                        <textarea
                            class="memo-input-body"
                            x-model="editDraft.body"
                            maxlength="1000"
                            rows="3"
                        ></textarea>

                        {{-- タグ選択（編集） --}}
                        <div class="memo-tag-picker">
                            <template x-for="tag in allTags" :key="tag.id">
                                <button
                                    type="button"
                                    class="tag"
                                    :class="tagColorClass(tag.color) + (editDraft.tagIds.includes(tag.id) ? ' tag-selected' : ' tag-unselected')"
                                    @click="toggleTag(editDraft, tag.id)"
                                    :aria-pressed="editDraft.tagIds.includes(tag.id)"
                                    x-text="tag.name"
                                ></button>
                            </template>
                            <div class="memo-tag-input-wrap">
                                <input
                                    type="text"
                                    class="memo-tag-input"
                                    x-model="editDraft.tagInput"
                                    placeholder="+ タグを追加"
                                    @keydown.enter.prevent="addNewTag(editDraft)"
                                    @keydown.comma.prevent="addNewTag(editDraft)"
                                >
                                <template x-for="(name, i) in editDraft.newTagNames" :key="i">
                                    <span class="tag tag-new">
                                        <span x-text="name"></span>
                                        <button type="button" @click="editDraft.newTagNames.splice(i,1)">
                                            <x-icon name="x" />
                                        </button>
                                    </span>
                                </template>
                            </div>
                        </div>

                        <div class="memo-form-actions">
                            <button
                                type="button"
                                class="btn-memo-save"
                                @click="saveEdit(memo)"
                                :disabled="submitting || !editDraft.body.trim()"
                            >保存</button>
                            <button type="button" class="btn-memo-cancel" @click="cancelEdit()">キャンセル</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ========== 動画ノートセクション（US2） ========== --}}
    @php
        $noteUpsertUrl = route('archives.note.upsert', $watchItem, false);
        $noteDestroyUrl = route('archives.note.destroy', $watchItem, false);
    @endphp
    <div
        class="show-video-note"
        x-data="videoNoteManager({
            initialBody: {{ json_encode($videoNote?->body ?? '') }},
            upsertUrl: '{{ $noteUpsertUrl }}',
            destroyUrl: '{{ $noteDestroyUrl }}',
            csrfToken: '{{ csrf_token() }}'
        })"
    >
        <h3 class="show-section-title"><x-icon name="note" />感想メモ <small>（この配信を通しての感想）</small></h3>

        <p class="note-saved-msg" x-show="saved" x-cloak role="status">保存しました</p>
        <p class="note-error-msg" x-show="error" x-text="error" role="alert"></p>

        <textarea
            class="note-textarea"
            x-model="body"
            placeholder="動画全体への感想を書く…（最大5000文字）"
            maxlength="5000"
            rows="5"
            aria-label="全体感想"
        ></textarea>

        <div class="note-actions">
            <button
                type="button"
                class="btn-note-save"
                @click="save()"
                :disabled="saving || !body.trim()"
                aria-label="全体感想を保存"
            >
                <span x-show="saving"><x-icon name="spinner" /></span>
                保存
            </button>
            <button
                type="button"
                class="btn-note-delete"
                @click="destroy()"
                x-show="hasNote"
                x-cloak
                aria-label="全体感想を削除"
            >
                <x-icon name="trash" />削除
            </button>
        </div>
    </div>

</div>

{{-- YouTube IFrame API スクリプト（CDN、このページのみ） --}}
<script>
// Alpine.js youtubePlayer コンポーネント（FR-002〜FR-008）
// YT.Player と saveTimer は Alpine の Proxy に入れると メソッドが壊れるため
// クロージャ変数として保持し、リアクティブデータには含めない
function youtubePlayer({ watchItemId, videoId, startSeconds, positionUrl, csrfToken }) {
    let _player    = null;
    let _saveTimer = null;
    let _initialized = false;

    const setPlayerFromEvent = (event) => {
        if (event?.target && typeof event.target.getCurrentTime === 'function') {
            _player = event.target;
        }
    };

    const getCurrentPosition = () => {
        if (!_player || typeof _player.getCurrentTime !== 'function') return null;

        const currentTime = Number(_player.getCurrentTime());
        return Number.isFinite(currentTime) ? Math.floor(currentTime) : null;
    };

    // memoManager コンポーネントが現在再生位置を取得できるよう公開する（FR-002）
    window.getCurrentYoutubePosition = getCurrentPosition;

    // memoManager コンポーネントがシークできるよう公開する（FR-003）
    window.seekYoutubePlayer = (seconds) => {
        if (!_player || typeof _player.seekTo !== 'function') return;
        _player.seekTo(seconds, true);
        if (typeof _player.playVideo === 'function') _player.playVideo();
    };

    return {
        ready: false,
        lastSavedPosition: startSeconds,

        init() {
            if (_initialized) return;
            _initialized = true;

            const setup = () => {
                _player = new YT.Player('yt-player-' + watchItemId, {
                    videoId: videoId,
                    playerVars: {
                        start: startSeconds,
                        rel: 0,
                        modestbranding: 1,
                    },
                    events: {
                        onReady: (event) => {
                            setPlayerFromEvent(event);
                            this.ready = true;
                        },
                        onStateChange: (event) => {
                            setPlayerFromEvent(event);
                            this.onStateChange(event);
                        },
                    },
                });
            };

            // YT.Player の定義だけではAPI内部の初期化が未完了の場合がある。
            // YT.loaded を確認し、半初期化状態のプレイヤー生成を避ける。
            if (window.YT?.Player && window.YT.loaded === 1) {
                setup();
            } else {
                const prev = window.onYouTubeIframeAPIReady;
                window.onYouTubeIframeAPIReady = () => {
                    if (typeof prev === 'function') prev();
                    setup();
                };
            }

            // ページ離脱時に keepalive で最終位置を保存し、タイマーも解除（FR-007）
            window.addEventListener('pagehide', () => this.saveOnUnload());
        },

        onStateChange(event) {
            // PLAYING=1, PAUSED=2, ENDED=0
            if (event.data === 1) {
                this.onPlaying();
            } else if (event.data === 2) {
                this.stopTimer();
                this.savePosition(false);
            } else if (event.data === 0) {
                this.stopTimer();
                this.savePosition(true);
            }
        },

        onPlaying() {
            // 60秒ごとの定期保存タイマーを開始（まだ起動していない場合のみ）（FR-005）
            if (!_saveTimer) {
                _saveTimer = setInterval(() => this.periodicSave(), 60000);
            }
        },

        stopTimer() {
            if (_saveTimer) {
                clearInterval(_saveTimer);
                _saveTimer = null;
            }
        },

        periodicSave() {
            // 前回保存位置との差が 5秒未満なら省略（FR-005）
            const pos = getCurrentPosition();
            if (pos === null) return;
            if (Math.abs(pos - this.lastSavedPosition) < 5) return;
            this.savePosition(false);
        },

        savePosition(isEnded) {
            const pos = getCurrentPosition();
            if (pos === null) return;

            this.sendSave(pos, isEnded, false).then((saved) => {
                if (saved) this.lastSavedPosition = pos;
            });
        },

        saveOnUnload() {
            // pagehide: タイマー解除 + keepalive で最後の位置を試行保存（FR-007）
            this.stopTimer();
            const pos = getCurrentPosition();
            if (pos === null) return;
            this.sendSave(pos, false, true);
        },

        sendSave(positionSeconds, isEnded, keepalive) {
            return fetch(positionUrl, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    last_position_seconds: positionSeconds,
                    is_ended: isEnded,
                }),
                keepalive: keepalive,
            }).then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return true;
            }).catch((err) => {
                console.warn('[youtubePlayer] 再生位置の保存に失敗しました:', err);
                return false;
            });
        },

        // 「アプリ内で続きから再生」ボタン（US3 / FR-012）
        seekTo(seconds) {
            if (!_player || typeof _player.seekTo !== 'function' || typeof _player.playVideo !== 'function') return;
            _player.seekTo(seconds, true);
            _player.playVideo();
        },
    };
}
</script>
<script>
// Alpine.js memoManager コンポーネント（Feature 006 FR-001〜FR-010）
// FR-003a: サーバー応答確認後にのみリストを更新（楽観的更新禁止）
function memoManager({ initialMemos, storeUrl, watchItemId, videoId, csrfToken, systemTags, userTags }) {
    return {
        memos: initialMemos,
        showCreateForm: false,
        showAdvanced: false,
        newMemoId: null,
        editingId: null,
        submitting: false,
        createError: null,
        editError: null,

        createDraft: { seconds: 0, body: '', tagIds: [], newTagNames: [], tagInput: '' },
        editDraft:   { seconds: 0, body: '', tagIds: [], newTagNames: [], tagInput: '' },

        get allTags() {
            return [...systemTags, ...userTags];
        },

        // 新規作成フォームを開き、現在位置から5秒前を初期値にする（FR-002）
        openCreate() {
            const pos = typeof window.getCurrentYoutubePosition === 'function'
                ? window.getCurrentYoutubePosition()
                : null;
            this.createDraft = {
                seconds:     Math.max(0, (pos ?? 0) - 5),
                body:        '',
                tagIds:      [],
                newTagNames: [],
                tagInput:    '',
            };
            this.createError = null;
            this.showAdvanced = false;
            this.showCreateForm = true;
        },

        closeCreate() {
            this.showCreateForm = false;
            this.showAdvanced = false;
            this.createError = null;
        },

        async saveCreate() {
            if (this.submitting || !this.createDraft.body.trim()) return;
            this.submitting = true;
            this.createError = null;
            try {
                const res = await fetch(storeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({
                        seconds:       this.createDraft.seconds,
                        body:          this.createDraft.body,
                        tag_ids:       this.createDraft.tagIds,
                        new_tag_names: this.createDraft.newTagNames,
                    }),
                });
                if (!res.ok) {
                    const data = await res.json();
                    this.createError = data.message ?? '保存に失敗しました。';
                    return;
                }
                const data = await res.json();
                // 秒数昇順でリストに挿入する
                const idx = this.memos.findIndex(m => m.seconds > data.memo.seconds);
                if (idx === -1) {
                    this.memos.push(data.memo);
                } else {
                    this.memos.splice(idx, 0, data.memo);
                }
                this.newMemoId = data.memo.id;
                this.closeCreate();
                setTimeout(() => {
                    if (this.newMemoId === data.memo.id) this.newMemoId = null;
                }, 1800);
            } catch (err) {
                this.createError = 'ネットワークエラーが発生しました。';
                console.warn('[memoManager] メモ保存エラー:', err);
            } finally {
                this.submitting = false;
            }
        },

        // 編集フォームを開く
        openEdit(memo) {
            this.editingId = memo.id;
            this.editDraft = {
                seconds:     memo.seconds,
                body:        memo.body,
                tagIds:      memo.tags.map(t => t.id),
                newTagNames: [],
                tagInput:    '',
            };
            this.editError = null;
        },

        cancelEdit() {
            this.editingId = null;
            this.editError = null;
        },

        async saveEdit(memo) {
            this.submitting = true;
            this.editError = null;
            const url = `/archives/${watchItemId}/memos/${memo.id}`;
            try {
                const res = await fetch(url, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({
                        seconds:       this.editDraft.seconds,
                        body:          this.editDraft.body,
                        tag_ids:       this.editDraft.tagIds,
                        new_tag_names: this.editDraft.newTagNames,
                    }),
                });
                if (!res.ok) {
                    const data = await res.json();
                    this.editError = data.message ?? '更新に失敗しました。';
                    return;
                }
                const data = await res.json();
                this.memos = this.memos.map(m => m.id === memo.id ? data.memo : m);
                this.editingId = null;
            } catch (err) {
                this.editError = 'ネットワークエラーが発生しました。';
                console.warn('[memoManager] メモ更新エラー:', err);
            } finally {
                this.submitting = false;
            }
        },

        async deleteMemo(memo) {
            if (!confirm('このメモを削除しますか？')) return;
            const url = `/archives/${watchItemId}/memos/${memo.id}`;
            try {
                const res = await fetch(url, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                });
                if (res.ok) {
                    this.memos = this.memos.filter(m => m.id !== memo.id);
                }
            } catch (err) {
                console.warn('[memoManager] メモ削除エラー:', err);
            }
        },

        // お気に入りトグル（FR-010）
        async toggleFavorite(memo) {
            const url = `/archives/${watchItemId}/memos/${memo.id}/favorite`;
            try {
                const res = await fetch(url, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                });
                if (res.ok) {
                    const data = await res.json();
                    this.memos = this.memos.map(m =>
                        m.id === memo.id ? { ...m, is_favorite: data.is_favorite } : m
                    );
                }
            } catch (err) {
                console.warn('[memoManager] お気に入りトグルエラー:', err);
            }
        },

        // プレイヤーをシーク（FR-003）
        seekTo(seconds) {
            if (typeof window.seekYoutubePlayer === 'function') {
                window.seekYoutubePlayer(seconds);
            }
        },

        // タグ選択トグル
        toggleTag(draft, tagId) {
            const idx = draft.tagIds.indexOf(tagId);
            if (idx === -1) {
                draft.tagIds.push(tagId);
            } else {
                draft.tagIds.splice(idx, 1);
            }
        },

        // インラインタグ追加（Enter / カンマ）
        addNewTag(draft) {
            const name = draft.tagInput.trim();
            if (name && !draft.newTagNames.includes(name)) {
                draft.newTagNames.push(name);
            }
            draft.tagInput = '';
        },

        // タグカラークラスを返す
        tagColorClass(color) {
            const map = {
                mint:   'tag-mint',
                blue:   'tag-blue',
                purple: 'tag-purple',
                orange: 'tag-orange',
                pink:   'tag-pink',
                green:  'tag-green',
            };
            return map[color] ?? 'tag-purple';
        },

        // seconds を MM:SS 形式で表示する
        formatSeconds(s) {
            s = Math.max(0, parseInt(s) || 0);
            if (s >= 3600) {
                return `${Math.floor(s / 3600)}:${String(Math.floor((s % 3600) / 60)).padStart(2,'0')}:${String(s % 60).padStart(2,'0')}`;
            }
            return `${Math.floor(s / 60)}:${String(s % 60).padStart(2,'0')}`;
        },
    };
}

// Alpine.js videoNoteManager コンポーネント（Feature 006 FR-005 / FR-006）
function videoNoteManager({ initialBody, upsertUrl, destroyUrl, csrfToken }) {
    return {
        body:    initialBody,
        hasNote: initialBody !== '',
        saving:  false,
        saved:   false,
        error:   null,

        async save() {
            this.saving = true;
            this.saved  = false;
            this.error  = null;
            try {
                const res = await fetch(upsertUrl, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ body: this.body }),
                });
                if (!res.ok) {
                    const data = await res.json();
                    this.error = data.message ?? '保存に失敗しました。';
                    return;
                }
                this.hasNote = true;
                this.saved   = true;
                // 3秒後に「保存しました」を消す
                setTimeout(() => { this.saved = false; }, 3000);
            } catch (err) {
                this.error = 'ネットワークエラーが発生しました。';
                console.warn('[videoNoteManager] ノート保存エラー:', err);
            } finally {
                this.saving = false;
            }
        },

        async destroy() {
            if (!confirm('全体感想を削除しますか？')) return;
            try {
                const res = await fetch(destroyUrl, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                });
                if (res.ok || res.status === 404) {
                    this.body    = '';
                    this.hasNote = false;
                    this.saved   = false;
                }
            } catch (err) {
                console.warn('[videoNoteManager] ノート削除エラー:', err);
            }
        },
    };
}
</script>
<script src="https://www.youtube.com/iframe_api" defer></script>
@endsection

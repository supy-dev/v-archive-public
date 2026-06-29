# Tasks: プレイヤーと再生進捗管理 (Feature 005)

**Input**: Design documents from `/specs/005-player-and-playback/`

**Prerequisites**: plan.md ✅ spec.md ✅ research.md ✅ data-model.md ✅ contracts/routes.md ✅

**Organization**: ユーザーストーリー別に整理。各フェーズが独立してテスト可能。

## Format: `[ID] [P?] [Story] Description`

- **[P]**: 並列実行可能（異なるファイル、未完了タスクへの依存なし）
- **[Story]**: 対応するユーザーストーリー（US1〜US5）
- 各タスクに正確なファイルパスを明記

---

## Phase 1: Setup（共有インフラ）

**Purpose**: 全ユーザーストーリーが共有するルート・レートリミット設定

- [x] T001 `AppServiceProvider::boot()` に `playback-position` レートリミット（10 req/分/ユーザー）を定義する（`Limit::perMinute(10)->by($request->user()?->id ?? $request->ip())`）`app/Providers/AppServiceProvider.php`
- [x] T002 [P] `routes/web.php` に 2 ルートを追加する: `GET /archives/{watchItem}` → `ArchiveController::show`（名前: `archives.show`）、`PATCH /watch-items/{watchItem}/position` → `PlaybackPositionController::update`（名前: `watch-items.position.update`、ミドルウェア: `throttle:playback-position`）`routes/web.php`

**Checkpoint**: ルートが登録され、`php artisan route:list` で 2 ルートが確認できる

---

## Phase 2: Foundational（全ストーリーのブロッキング前提）

**Purpose**: US1〜US5 すべてで必要な Policy と中核 Action

⚠️ **CRITICAL**: このフェーズが完了するまでユーザーストーリーの実装を開始しない

- [x] T003 `UserWatchItemPolicy::view()` が存在することを確認し、存在しない場合は `return $item->profile_id === $profile->id;` で追加する `app/Policies/UserWatchItemPolicy.php`
- [x] T004 `UpdatePlaybackPositionAction` を新規作成する。ロジック: ①`is_ended=true` → status が `watched` 以外なら `status=watched, watched_at=now()`、`last_position_seconds` は常に保存; ②`is_ended=false` → status が `want_to_watch` なら `status=watching, started_at=now()`（`started_at` が null の場合のみ）; `last_position_seconds` は新しい値 > 現在値（または null）の場合のみ更新; 変更なければ UPDATE しない `app/Actions/WatchItem/UpdatePlaybackPositionAction.php`

**Checkpoint**: `UpdatePlaybackPositionAction` が PHP として正常にパースでき、`UserWatchItemPolicy::view()` が存在する

---

## Phase 3: User Story 1 — 配信詳細ページで動画を再生する（Priority: P1）🎯 MVP

**Goal**: `/archives/{watchItem}` でプレイヤーが表示され、`last_position_seconds` から再生開始し、`want_to_watch` → `watching` 遷移が起きる

**Independent Test**: アーカイブ詳細ページへアクセスしてプレイヤーが表示され、動画を再生したときに status が `watching` へ変わることをブラウザで確認できる

### US1 の Feature テスト

- [x] T005 [P] [US1] `ArchiveShowTest` を作成する。テストケース: ①認証済みユーザーが自分の watch_item 詳細ページに 200 でアクセスできる; ②未認証は 302 リダイレクト; ③他ユーザーの watch_item は 403; ④存在しない watch_item は 404 `tests/Feature/Playback/ArchiveShowTest.php`

### US1 の実装

- [x] T006 [P] [US1] `ArchiveController::show(Request $request, UserWatchItem $watchItem)` を実装する: `authorize('view', $watchItem)` → `$watchItem->load('youtubeVideo.youtubeChannel')` → `view('archives.show', compact('watchItem'))` `app/Http/Controllers/ArchiveController.php`
- [x] T007 [P] [US1] `archives/show.blade.php` を新規作成する。`layouts/app.blade.php` を継承し、①プレイヤーラッパー（16:9 `div#yt-player`）、②動画情報セクション（タイトル・チャンネル名・投稿日・再生時間・ステータスバッジ）、③操作セクション（続きから再生ボタン・ステータス変更 UI）、④メモプレースホルダー（Feature 6）のスケルトンを構築する `resources/views/archives/show.blade.php`
- [x] T008 [US1] `show.blade.php` に YouTube IFrame API スクリプト（CDN `https://www.youtube.com/iframe_api`）を追加し、Alpine.js `youtubePlayer` コンポーネントを実装する。`init()`: `onYouTubeIframeAPIReady` コールバック設定 + `pagehide` リスナー登録; `initPlayer()`: `new YT.Player('yt-player', { videoId, playerVars: { start: startSeconds }, events: { onStateChange } })`; `onStateChange(event)`: `PLAYING(1)` → タイマー開始、`PAUSED(2)` → 即時保存、`ENDED(0)` → 終了保存。Blade から `x-data="youtubePlayer({ watchItemId: '{{ $watchItem->id }}', videoId: '{{ $watchItem->youtubeVideo->youtube_video_id }}', startSeconds: {{ $watchItem->last_position_seconds ?? 0 }} })"` で初期化 `resources/views/archives/show.blade.php`

**Checkpoint**: ブラウザで `GET /archives/{watchItem}` を開きプレイヤーが表示され、再生時に status が `watching` になる（DevTools Network でリクエストを確認）

---

## Phase 4: User Story 2 — 再生位置が自動的に保存される（Priority: P1）

**Goal**: 60秒ごと・一時停止時・ページ離脱時・動画終了時に `last_position_seconds` が保存され、次回ページを開いたときその位置から再生が始まる

**Independent Test**: 動画を途中まで再生して一時停止し、ページを再読み込みしたとき保存位置（±10秒）から再生が始まる

### US2 の Feature テスト

- [x] T009 [P] [US2] `PlaybackPositionTest` を作成する。テストケース: ①`is_ended=false` で `last_position_seconds` が保存される（SC-001）; ②`is_ended=false` で status が `want_to_watch` → `watching` に変わる（FR-004）; ③`is_ended=false` で status が `watching`・`watched`・`skipped` の場合は変化しない（FR-010 / 仕様 Q1）; ④`is_ended=true` で status が `watched` になり `watched_at` が設定される（FR-008）; ⑤`is_ended=true` で status が `skipped` → `watched` になる; ⑥現在の `last_position_seconds` より小さい値は上書きしない（FR-011）; ⑦`last_position_seconds` が `duration_seconds` を超える場合は 422 `tests/Feature/Playback/PlaybackPositionTest.php`
- [x] T010 [P] [US2] `PlaybackOwnershipTest` を作成する。テストケース: ①未認証は 401; ②他ユーザーの watch_item への PATCH は 403（SC-004）; ③自分の watch_item への PATCH は 204 `tests/Feature/Playback/PlaybackOwnershipTest.php`

### US2 の実装

- [x] T011 [P] [US2] `UpdatePlaybackPositionRequest` を作成する。バリデーションルール: `last_position_seconds`: `required|integer|min:0` + `max:{duration_seconds}`（`$this->route('watchItem')->youtubeVideo->duration_seconds` が null でない場合のみ）; `is_ended`: `required|boolean` `app/Http/Requests/UpdatePlaybackPositionRequest.php`
- [x] T012 [US2] `PlaybackPositionController::update()` を実装する: `authorize('update', $watchItem)` → `UpdatePlaybackPositionRequest` バリデーション → `UpdatePlaybackPositionAction::execute($watchItem, $validated['last_position_seconds'], $validated['is_ended'])` → `response()->noContent()（204）` `app/Http/Controllers/PlaybackPositionController.php`
- [x] T013 [US2] `show.blade.php` の Alpine.js `youtubePlayer` に `periodicSave()`・`savePosition(isEnded)`・`pagehide` ハンドラを追加する。`periodicSave()`: `setInterval(60000)`、前回保存位置との差が 5秒未満はスキップ（FR-005）; `savePosition(isEnded)`: `fetch(positionUrl, { method:'PATCH', body: JSON.stringify({last_position_seconds, is_ended}), keepalive: false })`; `pagehide` ハンドラ: `keepalive: true` で保存試行（FR-007）; `$cleanup` でタイマー解除 `resources/views/archives/show.blade.php`

**Checkpoint**: `php artisan test tests/Feature/Playback/` が全グリーン; ブラウザで 60 秒後・一時停止時に DevTools Network で PATCH リクエストを確認できる

---

## Phase 5: User Story 3 — 続きから再生する導線（Priority: P2）

**Goal**: `last_position_seconds` が保存済みの場合に「アプリ内で続きから再生」「YouTubeで続きから開く」の 2 ボタンが表示され機能する

**Independent Test**: 再生位置が保存済みの動画詳細ページで両ボタンが表示され、それぞれ保存位置から再生が始まる

### US3 の実装

- [x] T014 [P] [US3] Alpine.js `youtubePlayer` に `seekTo(seconds)` を追加し、`show.blade.php` に「アプリ内で続きから再生」ボタンを実装する。`seekTo(seconds)` は `this.player.seekTo(seconds, true)` を呼び出し再生開始; ボタンは `@click="seekTo({{ $watchItem->last_position_seconds }})"` で接続 `resources/views/archives/show.blade.php`
- [x] T015 [P] [US3] `show.blade.php` に「YouTube で続きから開く」ボタンを追加する。`href="https://www.youtube.com/watch?v={{ $watchItem->youtubeVideo->youtube_video_id }}&t={{ $watchItem->last_position_seconds }}s"` を `target="_blank" rel="noopener noreferrer"` で開く（FR-013）`resources/views/archives/show.blade.php`
- [x] T016 [US3] `last_position_seconds` が `null` または `0` の場合に両ボタンを非表示にする Blade 条件分岐を追加する（`@if($watchItem->last_position_seconds > 0)`）`resources/views/archives/show.blade.php`

**Checkpoint**: 再生位置保存済みの動画で両ボタンが表示され機能する；未再生（null）の動画ではボタンが非表示

---

## Phase 6: User Story 4 — 配信詳細ページのレスポンシブ表示（Priority: P2）

**Goal**: デスクトップ（960px+）でプレイヤーが上部中央寄せ表示、モバイル（760px-）で 16:9 全幅表示され、主要操作が快適に行える

**Independent Test**: ブラウザの DevTools でビューポートを 960px（デスクトップ）と 390px（モバイル）に切り替え、プレイヤーと動作ボタンが正常に表示される

### US4 の実装

- [x] T017 [P] [US4] `show.blade.php` にデスクトップ向けレイアウトを実装する。`max-w-4xl mx-auto` でプレイヤーを中央寄せし、プレイヤーラッパーに `aspect-ratio: 16/9` を適用; 動画情報・操作 UI はプレイヤー直下に上下スタック配置; CLAUDE.md カラートークン・余白・角丸を既存画面と統一 `resources/view/archives/show.blade.php` + `resources/css/app.css`
- [x] T018 [US4] `show.blade.php` のモバイル対応を実装する。760px 以下でプレイヤーが左右余白 0 の全幅 16:9 表示; 操作ボタンのタップ領域を `min-h-[44px]` 以上確保; ボトムナビ（71px）との重なりがないよう `pb-20` などでパディング調整 `resources/css/app.css`

**Checkpoint**: DevTools で 960px と 390px の両方でプレイヤーと操作 UI が正常表示される; 横スクロールが発生しない

---

## Phase 7: User Story 5 — 視聴ステータスを手動で変更する（Priority: P3）

**Goal**: 配信詳細ページからステータスを手動変更でき、一覧ページへ戻ったときに反映されている

**Independent Test**: 詳細ページのステータス変更 UI で「視聴済み」を手動選択し、`/watchlist` の「視聴済み」タブに表示される

### US5 の実装

- [x] T019 [P] [US5] `show.blade.php` にステータス変更フォームを追加する。`WatchStatus::cases()` をループしてボタン群を生成（現在ステータスを強調表示）; 各ボタンは `<form method="POST" action="{{ route('watchlist.update', $watchItem) }}">` + `@method('PATCH')` + `<input name="status" value="{{ $status->value }}">` で既存 `PATCH /watchlist/{userWatchItem}` ルートへ送信 `resources/views/archives/show.blade.php`
- [x] T020 [US5] ステータス変更後に `/archives/{watchItem}` へリダイレクトして戻るよう `UserWatchItemController::update()` のリダイレクト先を条件分岐する。リクエストに `redirect_to` hidden フィールドがある場合はそこへ; なければ `watchlist.index` へ（既存の動作を壊さない）`app/Http/Controllers/UserWatchItemController.php`

**Checkpoint**: 詳細ページでステータス変更後、同じページに戻りステータスバッジが更新されている; `/watchlist` でも反映済み

---

## Phase 8: Polish & Cross-Cutting

**Purpose**: エッジケース対応・品質確認・最終検証

- [x] T021 [P] `show.blade.php` に YouTube 非公開・削除動画（`$watchItem->youtubeVideo->is_available === false`）のエラーメッセージを追加する。プレイヤーラッパーに「現在 YouTube で再生できません」メッセージを表示し、動画情報・メモは引き続き表示する（FR-018）`resources/views/archives/show.blade.php`
- [x] T022 [P] `show.blade.php` にメモプレースホルダーを追加する。Feature 6 向けのカードとして「タイムスタンプメモ・全体感想は準備中」旨のダミー表示を追加（保存ロジックなし）`resources/views/archives/show.blade.php`
- [x] T023 `php artisan test tests/Feature/Playback/` を実行し、全テストがグリーンであることを確認する
- [ ] T024 `quickstart.md` のシナリオ 1〜7 をブラウザで手動検証する（デスクトップ 960px + モバイル 390px）; 横スクロール・レイアウト崩れ・タップ領域の問題がないことを確認する

---

## Dependencies & Execution Order

### Phase 依存関係

- **Setup (Phase 1)**: 依存なし — 即座に開始可能
- **Foundational (Phase 2)**: Phase 1 完了後 — 全 US をブロック
- **US1 (Phase 3)**: Foundational 完了後に開始可能
- **US2 (Phase 4)**: US1 完了後（T008 の Alpine コンポーネントに追加するため）
- **US3 (Phase 5)**: US2 完了後（T013 の savePosition があること前提）
- **US4 (Phase 6)**: US1 完了後（Blade ビューに対するスタイル調整）
- **US5 (Phase 7)**: US1 完了後（同じ Blade ビューへの追加）
- **Polish (Phase 8)**: US1〜US5 完了後

### Parallel Opportunities

**Phase 1**:
- T001 と T002 は異なるファイル → 並列実行可

**Phase 2**:
- T003 と T004 は異なるファイル → 並列実行可

**Phase 3（US1）**:
- T005（テスト）・T006（Controller）・T007（Blade スケルトン）の 3 タスクは異なるファイル → 並列実行可
- T008 は T007 完了後（同ファイルへの追記）

**Phase 4（US2）**:
- T009（位置保存テスト）・T010（所有権テスト）・T011（Request）は異なるファイル → 並列実行可
- T012 は T011 完了後
- T013 は T008 完了後（同ファイルへの追記）

**Phase 5（US3）**:
- T014 と T015 は同 Blade ファイルの異なるセクション → 意味的に並列、実装は順次でも可

**Phase 8**:
- T021 と T022 は異なる Blade セクション → 並列実行可
- T023 は実装完了後に独立して実行

---

## Parallel Example: User Story 2

```
# US2 の並列起動例
T009: PlaybackPositionTest 作成（tests/Feature/Playback/PlaybackPositionTest.php）
T010: PlaybackOwnershipTest 作成（tests/Feature/Playback/PlaybackOwnershipTest.php）
T011: UpdatePlaybackPositionRequest 作成（app/Http/Requests/UpdatePlaybackPositionRequest.php）

# これら 3 つが揃った後:
T012: PlaybackPositionController 作成（T011 依存）
T013: Alpine.js に periodicSave 等を追加（T008 依存）
```

---

## Implementation Strategy

### MVP First（User Story 1 + 2 のみ）

1. Phase 1: Setup 完了
2. Phase 2: Foundational 完了（Critical）
3. Phase 3: US1 完了 → ブラウザでプレイヤー表示・再生確認
4. Phase 4: US2 完了 → 再生位置保存・ステータス遷移確認
5. **STOP & VALIDATE**: `php artisan test tests/Feature/Playback/` グリーン確認
6. デモ / デプロイ可能な状態

### Incremental Delivery

1. Setup + Foundational → 基盤完成
2. US1 追加 → プレイヤー表示（MVP 最小単位）
3. US2 追加 → 再生位置保存・ステータス自動遷移
4. US3 追加 → 続きから再生の導線
5. US4 追加 → モバイル・デスクトップ最適化
6. US5 追加 → 手動ステータス変更
7. Polish → エッジケース・品質確認

---

## Notes

- `[P]` タスク = 異なるファイルへの操作、未完了タスクへの依存なし
- `[Story]` ラベルはユーザーストーリーへのトレーサビリティ
- `show.blade.php` へのタスクが多いため順次実行を推奨（マージ競合回避）
- 各フェーズのチェックポイントで動作確認してから次フェーズへ進む
- テストは先に作成し FAIL を確認してから実装する（憲法 VI）
- コメントは日本語で記載する（憲法 技術制約）

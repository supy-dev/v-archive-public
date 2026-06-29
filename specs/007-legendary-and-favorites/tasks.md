# Tasks: 神回登録・神回お気に入りページ改修・タイムスタンプメモ保管庫の新設

**Input**: Design documents from `/specs/007-legendary-and-favorites/`

**Prerequisites**: plan.md ✅ spec.md ✅ research.md ✅ data-model.md ✅ contracts/routes.md ✅

**Tests**: 憲法 VI「テストファースト」に従い、各USに Feature テストを含む。

**Organization**: US 単位でフェーズを分け、各ストーリーを独立して実装・検証できる構成にする。

## Format: `[ID] [P?] [Story] Description`

- **[P]**: 並列実行可（異なるファイル、依存なし）
- **[Story]**: 対応するユーザーストーリー（US1 / US2 / US3）

## アーキテクチャ上の注意

`TimestampMemo` は `user_watch_item_id` FK を持たない（`youtube_video_id` + `profile_id` で動画を参照）。
`/memos` および `/favorites?tab=memos` でアーカイブ詳細リンク（`/archives/{watchItem}`）を生成する際は、各コントローラーで次のパターンを使う:

```php
$watchItemMap = UserWatchItem::where('profile_id', $profile->id)
    ->whereIn('youtube_video_id', $memos->pluck('youtube_video_id')->unique())
    ->pluck('id', 'youtube_video_id');
// View で: route('archives.show', $watchItemMap[$memo->youtube_video_id])
```

---

## Phase 1: ベースライン確認

**Purpose**: 既存テストが全て通ることを確認してから着手する

- [X] T001 既存 Feature テストが全て通ることを確認する（`php artisan test tests/Feature/`）

**Checkpoint**: 全テスト GREEN → US フェーズへ進む

---

## Phase 2: US1 - 動画を神回に登録する (Priority: P1) 🎯 MVP

**Goal**: 配信詳細ページで `user_watch_items.is_favorite` をページ遷移なしにトグルし、神回登録・解除ができる

**Independent Test**: `/archives/{watchItem}` を開き「神回に登録」ボタンを押すと is_favorite が切り替わる。別ユーザーのアイテムに対して PATCH すると 403 が返る

### US1: テスト

- [X] T002 [P] [US1] `tests/Feature/WatchItem/WatchItemFavoriteTest.php` を新規作成する（トグル成功 → 200 JSON / 他ユーザーの watchItem → 403 / 未認証 → 302）

### US1: 実装

- [X] T003 [US1] `app/Actions/WatchItem/ToggleWatchItemFavoriteAction.php` を新規作成する（`$watchItem->update(['is_favorite' => !$watchItem->is_favorite])` を実行し新しい bool 値を返す。`ToggleMemoFavoriteAction` と同パターン）
- [X] T004 [US1] `app/Http/Controllers/WatchItemFavoriteController.php` を新規作成する（`authorize('update', $watchItem)` → `ToggleWatchItemFavoriteAction::execute()` → `response()->json(['is_favorite' => $result])`）
- [X] T005 [US1] `routes/web.php` の `memo-mutations` グループへ `PATCH /archives/{watchItem}/favorite` を追加し `WatchItemFavoriteController` を import する（route name: `archives.watch-item.favorite.update`）
- [X] T006 [US1] `resources/views/archives/show.blade.php` の show-page-actions エリアに神回トグルボタンを追加する（`x-data` に `isFavorite: {{ json_encode($watchItem->is_favorite) }}` を追加し、ボタン押下で PATCH fetch → `isFavorite` を更新、エラー時はメッセージ表示）
- [X] T007 [P] [US1] `resources/css/app.css` に神回トグルボタンのスタイルを追加する（`.btn-kamikai-toggle` / `.btn-kamikai-toggle.active` — 登録済み状態は brand-primary 系の塗りつぶし、未登録はアウトライン）

**Checkpoint**: T002 テスト GREEN、配信詳細ページでトグル操作が動作すること

---

## Phase 3: US2 - /favorites を2タブ構成に刷新する (Priority: P2)

**Goal**: `/favorites` に「神回」タブを追加し、`?tab=kamikai`（デフォルト）で神回動画、`?tab=memos` でお気に入りメモを表示する

**Independent Test**: `/favorites` を開くと神回タブがアクティブ。神回登録済み動画のカードが表示され、クリックで `/archives/{watchItem}` へ遷移する。`/favorites?tab=memos` で★メモ一覧が従来通り表示される

### US2: テスト

- [X] T008 [P] [US2] `tests/Feature/Favorites/FavoritesTabTest.php` を新規作成する（神回タブ デフォルト / `?tab=memos` で★メモ表示 / 推し・年月フィルタ（神回タブ）/ 空状態メッセージ）
- [X] T009 [P] [US2] 既存 `tests/Feature/Favorites/FavoritesIndexTest.php` を確認し、`?tab=memos` パラメータを付与して引き続き通るよう修正する

### US2: 実装

- [X] T010 [US2] `app/Http/Controllers/FavoriteController.php` を2タブ対応に拡張する（`$tab = $request->query('tab', 'kamikai')` で分岐: `kamikai` → `UserWatchItem::is_favorite=true` を `youtubeVideo.youtubeChannel` eager load + `updated_at` 降順 + paginate(20)、`memos` → 既存ロジック維持。`$watchItemMapForMemos` は不要だが神回タブの動画カードに `route('archives.show', $watchItem)` を渡す）
- [X] T011 [US2] `resources/views/favorites/index.blade.php` を2タブ構成に刷新する（タブ切り替え UI: GET リンク `?tab=kamikai` / `?tab=memos`、神回タブ: 動画カードを表示（サムネイル・タイトル・チャンネル名・登録日・配信詳細リンク）・空状態、メモタブ: 既存メモカード UI を維持、推し・年月フィルタを現タブへ適用）
- [X] T012 [P] [US2] `resources/css/app.css` にタブ切り替え UI スタイルと神回動画カード（`.kamikai-card`）スタイルを追加する（サムネイル 16:9 / カードのホバー状態 / アクティブタブのアンダーライン）

**Checkpoint**: T008 / T009 GREEN。`/favorites` で神回タブと★メモタブが正しく切り替わる。既存の `/favorites` テストが通ること

---

## Phase 4: US3 - /memos 保管庫を新設し導線を修正する (Priority: P3)

**Goal**: 全タイムスタンプメモを探せる `/memos` を新設し、サイドバー「タイムスタンプメモ」・ホーム「もっと見る」をここへ接続。ホームの「最近のタイムスタンプ」を実データ化する

**Independent Test**: サイドバー「タイムスタンプメモ」→ `/memos` へ遷移し、★問わず全メモが表示される。各カードに★ボタンがなく、主リンクが `/archives/{watchItem}` へ遷移する

### US3: テスト

- [X] T013 [P] [US3] `tests/Feature/Memo/MemoIndexTest.php` を新規作成する（全メモ表示 / 推し・タグ・年月フィルタ / ページネーション / ★ボタン不在 / 主リンクがアーカイブ詳細 / 他ユーザーのメモが表示されない）
- [X] T014 [P] [US3] `tests/Feature/Home/HomeSummaryTest.php` を更新する（「最近のタイムスタンプ」に実データが含まれることを検証）

### US3: 実装

- [X] T015 [US3] `app/Http/Controllers/MemoController.php` を新規作成する（全 `TimestampMemo` を `tags` + `youtubeVideo.youtubeChannel` eager load、推し/タグ/年月フィルタ、paginate(20)。`$watchItemMap` を `UserWatchItem::pluck('id', 'youtube_video_id')` で生成し view へ渡す）
- [X] T016 [US3] `routes/web.php` に `GET /memos` ルートを追加する（`auth.supabase` + `throttle:60,1` グループ、`MemoController::index`、name: `memos.index`、`MemoController` を import）
- [X] T017 [US3] `resources/views/memos/index.blade.php` を新規作成する（フィルタバー: 推し/タグ/年月、メモカード: タイムスタンプ・本文・タグ・動画タイトル・チャンネル名・作成日・配信詳細リンク・YouTube 副リンク。★ボタンは配置しない（FR-017）、空状態メッセージ、ページネーション）
- [X] T018 [P] [US3] `resources/css/app.css` に `/memos` ページのフィルタバーとメモカードスタイルを追加する（既存 `/favorites` の `.fav-card` スタイルを参考に統一感を保つ）
- [X] T019 [US3] `app/Http/Controllers/HomeController.php` を更新する（`index()` に最近のタイムスタンプ取得クエリを追加: `TimestampMemo::where('profile_id', ...) →with('youtubeVideo') →latest() →limit(3) →get()` → view へ `$recentMemos` として渡す）
- [X] T020 [US3] `resources/views/home.blade.php` を更新する（`$recentMemos` で「最近のタイムスタンプ」を実描画、「もっと見る」を `route('memos.index')` へ変更、空状態（0件）対応）
- [X] T021 [US3] `resources/views/components/crown-banner.blade.php` のリンクを `route('favorites.index')` へ変更する（現状: `route('home')#favorites`、神回タブがデフォルトのため `?tab=` パラメータ不要）
- [X] T022 [US3] `resources/views/layouts/app.blade.php` を更新する（デスクトップサイドバー: 「タイムスタンプメモ」を `route('memos.index')` リンクへ変更、active 判定 `$currentRoute === 'memos.index'` を追加）

**Checkpoint**: T013 / T014 GREEN。サイドバー・ホームからの導線が実画面へ接続されており、`/memos` で全メモが閲覧・絞り込みできること

---

## Phase 5: ポリッシュ & クロスカッティング

**Purpose**: レスポンシブ確認・リグレッション検証

- [X] T023 [P] `quickstart.md` シナリオ1〜4 を Desktop 960px 幅で通し確認する（神回トグル → 神回タブ表示 → /memos 表示 → ホーム導線）
- [X] T024 [P] `quickstart.md` シナリオ1〜4 を Mobile 390px 幅で通し確認する（タップ領域・横スクロールなし・カード収まり）
- [X] T025 `php artisan test tests/Feature/` を実行し全テストが GREEN であることを確認する（リグレッションなし）

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1（ベースライン）**: 依存なし — 即座に開始可
- **Phase 2（US1）**: Phase 1 完了後
- **Phase 3（US2）**: Phase 2（US1）完了後推奨（神回動画カードの動作確認に US1 の登録機能が必要）
- **Phase 4（US3）**: Phase 1 完了後（US1 / US2 とは独立して実装可能）
- **Phase 5（ポリッシュ）**: 全 US フェーズ完了後

### User Story Dependencies

- **US1 (P1)**: Phase 1 完了後に単独で実施可
- **US2 (P2)**: US1 完了後が望ましい（神回タブに US1 の登録データが必要）
- **US3 (P3)**: Phase 1 完了後に US1/US2 と並行可（独立した画面・ルート）

### 各 US 内の実行順

```
テスト作成（失敗確認） → Action/Controller → Route → View → CSS
```

### 並列実行可能なタスク

**US1 内**: T002（テスト）と T007（CSS）は T003〜T006 の実装と並行可
**US2 内**: T008・T009（テスト）と T012（CSS）は T010〜T011 と並行可
**US3 内**: T013・T014（テスト）と T018（CSS）は T015〜T022 と並行可
**ポリッシュ**: T023・T024 は並行可

---

## Implementation Strategy

### MVP First（US1 のみ）

1. Phase 1: ベースライン確認（T001）
2. Phase 2: US1 完了（T002〜T007）
3. **STOP & VALIDATE**: 配信詳細ページで神回トグルが動作することを確認
4. 必要なら `/favorites` を訪問して「神回タブ」のみ実装済みか確認

### Incremental Delivery

1. Phase 1 → Phase 2（US1）→ 神回トグル動作確認
2. Phase 3（US2）→ `/favorites` 2タブ動作確認
3. Phase 4（US3）→ `/memos` 保管庫 + 導線修正確認
4. Phase 5 → 全シナリオ通し確認

---

## Notes

- `[P]` タスクは異なるファイルを対象とするため並列実行可
- `[Story]` ラベルで各タスクの所属 US が一目でわかる
- `TimestampMemo` に `user_watch_item_id` FK なし → アーカイブリンクは Controller で `$watchItemMap` を生成して渡す（「アーキテクチャ上の注意」参照）
- テストは実装前に作成し、**FAIL を確認してから**実装に入ること（憲法 VI）
- 各 Checkpoint で US が独立して動作することを確認してから次フェーズへ進む

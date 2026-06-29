# Tasks: アーカイブ閲覧と見るリスト管理 (Feature 004)

**Input**: Design documents from `/specs/004-archive-and-watchlist/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/routes.md, quickstart.md

**Tests**: 憲法 VI（テストファースト）に基づき Feature テストを含む

**Organization**: タスクはユーザーストーリー単位にまとめる。Phase 2 完了後に US1・US2・US3 を並行実施できる

## Format: `[ID] [P?] [Story] Description`

- **[P]**: ファイルが異なり依存なしで並行実行できるタスク
- **[Story]**: 対応するユーザーストーリー（US1/US2/US3）
- 各タスクに具体的なファイルパスを記載

---

## Phase 1: Setup（前提確認）

**Purpose**: Feature 001〜003 の完了確認と Feature 004 実装準備

- [x] T001 Feature 001〜003 の migration 適用状況と `youtube_videos` データの存在を確認する（`php artisan migrate:status` および DB 確認）

---

## Phase 2: Foundational（全ストーリーの前提）

**Purpose**: 全ユーザーストーリーが依存するデータ層とドメイン基盤を構築する

**⚠️ CRITICAL**: この Phase が完了するまで US1〜US3 のいずれも開始できない

- [x] T002 [P] `user_watch_items` テーブルのマイグレーションを作成する（`database/migrations/2026_06_20_000006_create_user_watch_items_table.php`）— UNIQUE(profile_id, youtube_video_id)、CHECK status、ON DELETE CASCADE FK、3本のインデックスを含む（FR-007, FR-014）
- [x] T003 [P] `App\Enums\WatchStatus` Backed Enum（string）を実装する（`app/Enums/WatchStatus.php`）— WantToWatch / Watching / Watched / Skipped の4値、`label()` と `timestamps()` ヘルパメソッドを追加する
- [x] T004 マイグレーションを実行し `App\Models\UserWatchItem` を実装する（`app/Models/UserWatchItem.php`）— HasUuids トレイト、BelongsTo Profile/YoutubeVideo リレーション、`status` を WatchStatus にキャストする（T002・T003 完了後）
- [x] T005 [P] `Database\Factories\UserWatchItemFactory` をテスト用に作成する（`database/factories/UserWatchItemFactory.php`）— 全カラムの初期値と各ステータス用 state メソッドを定義する（T004 完了後）
- [x] T006 [P] `App\Policies\UserWatchItemPolicy` を実装する（`app/Policies/UserWatchItemPolicy.php`）— view/update/delete は `$item->profile_id === $profile->id` で確認、create は動画チャンネルがユーザーの `user_channels` に含まれることも確認する（FR-009）（T004 完了後）

**Checkpoint**: データ層・Policy 完了。US1・US2・US3 を並行実施できる

---

## Phase 3: User Story 1 — 新着アーカイブを閲覧して見るリストに追加する (Priority: P1) 🎯 MVP

**Goal**: 未整理動画の一覧表示と「見るリスト追加」「見送り」操作を提供する

**Independent Test**: 同期済みチャンネルがある状態で `/archive` を開き、動画1件に「見るリストに追加」を押すと `user_watch_items` に `want_to_watch` で追加されアーカイブ一覧から消えること、「見送る」を押すと `skipped` で作成されること

### Feature テスト（US1）

> **NOTE: 実装前に作成し FAIL 状態を確認すること（憲法 VI）**

- [x] T007 [P] [US1] `ArchiveIndexTest` を作成する（`tests/Feature/Archive/ArchiveIndexTest.php`）— 未整理一覧の表示、`is_available=false` 除外（FR-011）、推し・video_type フィルタ（FR-004）、空状態メッセージ（US1 AC-6）、他ユーザー動画の非表示、ページネーション 20件（FR-012）、未認証リダイレクトを検証する
- [x] T008 [P] [US1] `StoreWatchItemTest` を作成する（`tests/Feature/WatchList/StoreWatchItemTest.php`）— `want_to_watch` / `skipped` 作成成功、重複送信時の upsert 冪等性（FR-007）、チャンネル未登録動画への Policy create 拒否、未認証リダイレクトを検証する

### 実装（US1）

- [x] T009 [P] [US1] `App\Http\Requests\StoreUserWatchItemRequest` を実装する（`app/Http/Requests/StoreUserWatchItemRequest.php`）— `status: required|in:want_to_watch,skipped` バリデーション
- [x] T010 [P] [US1] `App\Actions\WatchItem\AddToWatchListAction` を実装する（`app/Actions/WatchItem/AddToWatchListAction.php`）— `updateOrCreate(['profile_id', 'youtube_video_id'], [...])` で upsert、作成時に `added_at` を設定する（FR-007）
- [x] T011 [US1] `App\Http\Controllers\ArchiveController::index` を実装する（`app/Http/Controllers/ArchiveController.php`）— `user_channels` INNER JOIN → `user_watch_items` LEFT JOIN IS NULL で未整理絞込、`is_available=true`、推し・video_type フィルタ、`published_at DESC`、`paginate(20)`、N+1 防止 eager load（FR-001/002/004/011/012）（T009・T010 完了後）
- [x] T012 [US1] `App\Http\Controllers\UserWatchItemController::store` を実装する（`app/Http/Controllers/UserWatchItemController.php`）— Policy `create` チェック → `AddToWatchListAction` → `redirect()->back()` with flash（T009・T010 完了後）
- [x] T013 [US1] `routes/web.php` に `GET /archive` と `POST /archive/{video}/watch-item` を追加する（`routes/web.php`）— `auth.supabase` middleware を付与する（T011・T012 完了後）
- [x] T014 [US1] `resources/views/archive/index.blade.php` を実装する（`resources/views/archive/index.blade.php`）— 推し・video_type フィルタ（Alpine.js `x-data` auto-submit）、動画カード（サムネイル・タイトル・推し名・公開日時・動画時間、FR-013）、「見るリストに追加」「見送る」ボタン（POST フォーム）、ページネーション、空状態メッセージ。CLAUDE.md のデザイン規約（`x-archive-card` コンポーネント、カラートークン）を遵守する（T011・T013 完了後）

**Checkpoint**: `/archive` で未整理動画一覧の表示・見るリスト追加・見送りが動作する（US1 独立検証可能）

---

## Phase 4: User Story 2 — 見るリストの視聴ステータスを管理する (Priority: P1)

**Goal**: 見るリスト画面でのステータスタブ切替・手動ステータス変更・削除を提供する

**Independent Test**: `want_to_watch` の `user_watch_items` がある状態で `/watchlist?status=want_to_watch` を開くと動画が表示され、「視聴済み」に変更すると DB の `status` が `watched`・`watched_at` が設定されて「視聴済み」タブへ移動すること

### Feature テスト（US2）

> **NOTE: 実装前に作成し FAIL 状態を確認すること（憲法 VI）**

- [x] T015 [P] [US2] `UpdateWatchStatusTest` を作成する（`tests/Feature/WatchList/UpdateWatchStatusTest.php`）— `want_to_watch` / `watched` / `skipped` への変更成功、各タイムスタンプ自動設定（FR-008）、他ユーザーアイテムへの Policy update 拒否（FR-009、SC-005）、未認証リダイレクトを検証する
- [x] T016 [P] [US2] `DeleteWatchItemTest` を作成する（`tests/Feature/WatchList/DeleteWatchItemTest.php`）— 削除成功・アーカイブ一覧への復帰（FR-015）、他ユーザーアイテムへの Policy delete 拒否、未認証リダイレクトを検証する

### 実装（US2）

- [x] T017 [P] [US2] `App\Http\Requests\UpdateWatchStatusRequest` を実装する（`app/Http/Requests/UpdateWatchStatusRequest.php`）— `status: required|in:want_to_watch,watched,skipped` バリデーション（`watching` は手動変更対象外、FR-005）
- [x] T018 [P] [US2] `App\Actions\WatchItem\UpdateWatchStatusAction` を実装する（`app/Actions/WatchItem/UpdateWatchStatusAction.php`）— ステータス変更と対応タイムスタンプ（`watched_at` / `skipped_at`）の自動設定（FR-008）
- [x] T019 [P] [US2] `App\Actions\WatchItem\DeleteWatchItemAction` を実装する（`app/Actions/WatchItem/DeleteWatchItemAction.php`）— `$item->delete()` のシンプルな削除（FR-015）
- [x] T020 [US2] `UserWatchItemController::index`・`update`・`destroy` を実装する（`app/Http/Controllers/UserWatchItemController.php`）— index: `status` クエリパラメータでタブ別 `paginate(20)`・タブカウント4件を取得し View へ渡す、update: Policy `update` → `UpdateWatchStatusAction` → `redirect()->back()`、destroy: Policy `delete` → `DeleteWatchItemAction` → `redirect()->route('watchlist.index')`（T017〜T019 完了後）
- [x] T021 [US2] `routes/web.php` に `GET /watchlist`・`PATCH /watchlist/{userWatchItem}`・`DELETE /watchlist/{userWatchItem}` を追加する（`routes/web.php`）— `auth.supabase` middleware を付与する（T020 完了後）
- [x] T022 [US2] `resources/views/watchlist/index.blade.php` を実装する（`resources/views/watchlist/index.blade.php`）— URL クエリパラメータ `?status=` によるタブ切替（サーバーサイドレンダリング）、タブカウントバッジ、動画カード（FR-013）、ステータス変更ボタン（PATCH フォーム）、削除ボタン（DELETE フォーム）、空状態メッセージ。CLAUDE.md のデザイン規約（`x-archive-card` コンポーネント、カラートークン）を遵守する（T020・T021 完了後）

**Checkpoint**: `/watchlist` でタブ切替・ステータス変更・削除が動作する（US2 独立検証可能）

---

## Phase 5: User Story 3 — ホーム画面で視聴状況のサマリーを確認する (Priority: P2)

**Goal**: ホーム画面に未整理件数・見るリスト件数・視聴中件数・視聴済み件数を表示し、各画面へのナビゲーションを提供する

**Independent Test**: `user_watch_items` が複数ステータスで混在する状態でホーム画面を開くと、各件数が正確なカウントで表示されること（SC-006）

### Feature テスト（US3）

> **NOTE: 実装前に作成し FAIL 状態を確認すること（憲法 VI）**

- [x] T023 [US3] `HomeSummaryTest` を作成する（`tests/Feature/Home/HomeSummaryTest.php`）— 各サマリー件数の正確性（FR-010）、サマリーカードリンクの遷移先確認（US3 AC-2/3）、未認証リダイレクトを検証する

### 実装（US3）

- [x] T024 [US3] `HomeController` にホームサマリー件数算出を追加する（`app/Http/Controllers/HomeController.php`）— 未整理件数は `is_available=true` の登録チャンネル動画数から自ユーザーの `user_watch_items` 総数を引く COUNT サブクエリ2本で算出、`want_to_watch` / `watching` / `watched` 件数は `status` 別 COUNT で取得し View へ渡す（FR-010）（T023 完了後）
- [x] T025 [US3] `resources/views/home.blade.php` のサマリーカードに件数をバインドし動線リンクを設定する（`resources/views/home.blade.php`）— 未整理カードは `/archive` へ、見るリストカードは `/watchlist` へリンクする（US3 AC-2/3）（T024 完了後）

**Checkpoint**: ホーム画面のサマリー件数が正確に表示され各画面へ遷移できる（US3 独立検証可能）

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: パフォーマンス・UI品質・テスト GREEN の最終確認

- [x] T026 [P] `/archive` と `/watchlist` で N+1 が発生しないことを `DB::enableQueryLog()` または Laravel Debugbar で確認し、必要に応じて eager load を追加する（憲法 技術制約）
- [x] T027 [P] モバイル（390px）とデスクトップ（960px）で `archive/index`・`watchlist/index`・`home` のレイアウトを CLAUDE.md の検証基準（余白・文字サイズ・カード比率・ナビゲーション）で確認する
- [x] T028 [P] `quickstart.md` の全シナリオ（シナリオ 1〜3・重複作成防止テスト）を手動で実行し期待結果を確認する
- [x] T029 `php artisan test --filter Feature004` を実行しすべてのテストが GREEN であることを確認する

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1（Setup）**: 依存なし — 即開始可能
- **Phase 2（Foundational）**: Phase 1 完了後 — US1・US2・US3 すべてをブロック
- **Phase 3（US1）**: Phase 2 完了後に開始可能
- **Phase 4（US2）**: Phase 2 完了後に開始可能（US1 と並行実施可能）
- **Phase 5（US3）**: Phase 2 完了後に開始可能（US1・US2 と並行実施可能）
- **Phase 6（Polish）**: 全ストーリー完了後

### User Story Dependencies

- **US1 (P1)**: Phase 2 完了後に独立開始可能。US2・US3 への依存なし
- **US2 (P1)**: Phase 2 完了後に独立開始可能。US1 への依存なし（手動テストには US1 のデータがあると便利）
- **US3 (P2)**: Phase 2 完了後に独立開始可能。US1/US2 完了後にサマリーデータが揃う

### Within Each User Story

- テストは実装より先に作成し FAIL 状態を確認する（憲法 VI）
- Enum/Model → Actions/Requests → Controller → Routes → View の順で実装する
- Phase 2 の Foundational 完了後、US1・US2・US3 は並行実施できる

### Parallel Opportunities

- T002（Migration）と T003（Enum）は同時実行可能
- T005（Factory）と T006（Policy）は T004（Model）完了後に同時実行可能
- US1 の T007〜T010（テスト2本 + Request + Action）は同時実行可能
- US2 の T015〜T019（テスト2本 + Request + Actions 2本）は同時実行可能
- Phase 2 完了後、US1・US2・US3 の各 Phase はチームで並行実施可能

---

## Parallel Example: User Story 1

```bash
# Phase 2 完了後、US1 の以下を同時実行:
Task T007: tests/Feature/Archive/ArchiveIndexTest.php
Task T008: tests/Feature/WatchList/StoreWatchItemTest.php
Task T009: app/Http/Requests/StoreUserWatchItemRequest.php
Task T010: app/Actions/WatchItem/AddToWatchListAction.php

# T007〜T010 完了後:
Task T011: app/Http/Controllers/ArchiveController.php
Task T012: app/Http/Controllers/UserWatchItemController.php (store)

# T011・T012 完了後:
Task T013: routes/web.php (GET /archive, POST /archive/{video}/watch-item)
Task T014: resources/views/archive/index.blade.php
```

## Parallel Example: User Story 2

```bash
# Phase 2 完了後（US1 と並行して）:
Task T015: tests/Feature/WatchList/UpdateWatchStatusTest.php
Task T016: tests/Feature/WatchList/DeleteWatchItemTest.php
Task T017: app/Http/Requests/UpdateWatchStatusRequest.php
Task T018: app/Actions/WatchItem/UpdateWatchStatusAction.php
Task T019: app/Actions/WatchItem/DeleteWatchItemAction.php

# T015〜T019 完了後:
Task T020: app/Http/Controllers/UserWatchItemController.php (index/update/destroy)

# T020 完了後:
Task T021: routes/web.php (GET /watchlist, PATCH, DELETE)
Task T022: resources/views/watchlist/index.blade.php
```

---

## Implementation Strategy

### MVP First（User Story 1 のみ）

1. Phase 1: Setup 完了確認
2. Phase 2: Foundational 完了（CRITICAL — 全ストーリーをブロック）
3. Phase 3: US1 完了
4. **検証停止**: `quickstart.md` シナリオ 1 を手動実行
5. デプロイ / デモ準備完了

### Incremental Delivery

1. Setup + Foundational 完了 → 基盤準備完了
2. US1 完了 → 手動検証 → デプロイ/デモ（MVP!）
3. US2 完了 → 手動検証 → デプロイ/デモ
4. US3 完了 → 手動検証 → デプロイ/デモ
5. 各ストーリーが前のストーリーを壊すことなく価値を追加する

### Parallel Team Strategy

チームで開発する場合:

1. チーム全員で Phase 1 + Phase 2 を完了させる
2. Foundational 完了後:
   - 開発者 A: US1（Phase 3）
   - 開発者 B: US2（Phase 4）
   - 開発者 C: US3（Phase 5）
3. 各ストーリーを独立して完了・統合する

---

## Notes

- [P] タスク = ファイルが異なり依存なし（並行実行可能）
- [Story] ラベルでタスクとユーザーストーリーのトレーサビリティを確保
- 各ユーザーストーリーは独立して完了・テスト可能
- テストは実装前に作成し FAIL を確認する（憲法 VI）
- タスク完了後または論理的なグループ単位でコミットする
- 任意のチェックポイントで一時停止してストーリーを独立検証できる
- View 実装時は `CLAUDE.md` のデザイン規約（カラートークン・共通 Blade コンポーネント・レスポンシブ規則）を必ず遵守する
- コードコメントは日本語で記載する（憲法 v1.1.0）

# Tasks: メモ・タグ・神回お気に入り

**Input**: Design documents from `/specs/006-memos-and-tags/`

**Prerequisites**: plan.md ✅ spec.md ✅ research.md ✅ data-model.md ✅ contracts/routes.md ✅ quickstart.md ✅

**Tech stack**: PHP 8.4 / Laravel 12 / Blade / Alpine.js / PostgreSQL / Pest

**Organization**: US1 → US2 → US3 → US4 の優先度順。Phase 2 完了後から US が並行実施可能。

## Format: `[ID] [P?] [Story] Description`

- **[P]**: 並行実行可能（異なるファイル、依存なし）
- **[Story]**: US1〜US4 のユーザーストーリー対応タスク

---

## Phase 1: Setup（共有インフラ）

**Purpose**: 本 Feature で追加が必要なプロジェクト共通設定を整備する

- [X] T001 memo-mutations レートリミッターを app/Providers/AppServiceProvider.php の `boot()` に追加（RateLimiter::for('memo-mutations', ...)）
- [X] T002 システムタグ 8 件を投入する SystemTagSeeder を database/seeders/SystemTagSeeder.php に作成し DatabaseSeeder に登録する

---

## Phase 2: Foundational（全 US のブロッキング前提条件）

**Purpose**: マイグレーション・モデル・Policy・ルートなど全 US が依存するコアを整備する

**⚠️ CRITICAL**: この Phase が完了するまで US 実装は開始できない

- [X] T003 `tags` テーブルマイグレーション（部分ユニークインデックス含む）を database/migrations/2026_06_20_000007_create_tags_table.php に作成する
- [X] T004 [P] `timestamp_memos` テーブルマイグレーション（seconds CHECK >= 0、FK、インデックス）を database/migrations/2026_06_20_000008_create_timestamp_memos_table.php に作成する
- [X] T005 [P] `video_notes` テーブルマイグレーション（UNIQUE(profile_id, youtube_video_id)）を database/migrations/2026_06_20_000010_create_video_notes_table.php に作成する
- [X] T006 `timestamp_memo_tags` 中間テーブルマイグレーション（複合 PK、FK CASCADE）を database/migrations/2026_06_20_000009_create_timestamp_memo_tags_table.php に作成する（T003・T004 完了後）
- [X] T007 [P] TagScope Enum（system / user_owned）を app/Enums/TagScope.php に作成する
- [X] T008 [P] Tag モデル（belongsToMany TimestampMemo、belongsTo Profile（nullable））を app/Models/Tag.php に作成する
- [X] T009 [P] TimestampMemo モデル（belongsTo Profile / YoutubeVideo、belongsToMany Tag、secondsLabel アクセサ、youtubeUrl アクセサ）を app/Models/TimestampMemo.php に作成する
- [X] T010 [P] VideoNote モデル（belongsTo Profile / YoutubeVideo）を app/Models/VideoNote.php に作成する
- [X] T011 [P] TimestampMemoPolicy（view/create/update/delete は所有権確認）を app/Policies/TimestampMemoPolicy.php に作成し AuthServiceProvider（または #[Policy] attribute）で登録する
- [X] T012 [P] VideoNotePolicy（view/upsert/delete は所有権確認）を app/Policies/VideoNotePolicy.php に作成する
- [X] T013 [P] TagPolicy（create: 認証済みユーザーのみ、update/delete: 所有権確認）を app/Policies/TagPolicy.php に作成する
- [X] T014 全新規ルート（メモ CRUD・ノート upsert/destroy・favorites index）を routes/web.php に追加する（contracts/routes.md 参照）

**Checkpoint**: `php artisan migrate && php artisan db:seed --class=SystemTagSeeder` が通ること

---

## Phase 3: User Story 1 — タイムスタンプメモの記録とシーク（Priority: P1）🎯 MVP

**Goal**: 動画視聴中に再生位置付きメモを保存し、保存済みタイムスタンプをクリックしてプレイヤーをシークできる

**Independent Test**: `/archives/{watchItem}` で「現在位置をメモ」ボタンを押してメモを保存し、タイムスタンプクリックで動画が指定位置から再生されることを確認する

### テスト（US1）

- [X] T015 [P] [US1] タイムスタンプメモ保存・更新・削除の Feature テスト（正常系・バリデーション）を tests/Feature/Memo/TimestampMemoStoreTest.php に作成する
- [X] T016 [P] [US1] 所有権保護テスト（他ユーザーのメモへの操作が 403 になること）を tests/Feature/Memo/TimestampMemoOwnershipTest.php に作成する

### 実装（US1）

- [X] T017 [P] [US1] StoreTimestampMemoRequest（seconds: int >= 0 required、body: string 1〜1000 required、tag_ids: array nullable、new_tag_names: array nullable）を app/Http/Requests/StoreTimestampMemoRequest.php に作成する
- [X] T018 [P] [US1] UpdateTimestampMemoRequest（同上フィールド）を app/Http/Requests/UpdateTimestampMemoRequest.php に作成する
- [X] T019 [P] [US1] CreateTimestampMemoAction（メモ作成 + タグ sync、tag_ids/new_tag_names が空配列でも動作する）を app/Actions/Memo/CreateTimestampMemoAction.php に作成する
- [X] T020 [P] [US1] UpdateTimestampMemoAction（メモ更新 + タグ差分 sync）を app/Actions/Memo/UpdateTimestampMemoAction.php に作成する
- [X] T021 [P] [US1] DeleteTimestampMemoAction（メモ削除、pivot は CASCADE で自動削除）を app/Actions/Memo/DeleteTimestampMemoAction.php に作成する
- [X] T022 [US1] TimestampMemoController（store: 201 JSON、update: 200 JSON、destroy: 204）を app/Http/Controllers/TimestampMemoController.php に作成する（T017〜T021 完了後）
- [X] T023 [US1] youtubePlayer Alpine コンポーネントの init 時に `window.getCurrentYoutubePosition = getCurrentPosition;` を公開するよう resources/views/archives/show.blade.php を更新する
- [X] T024 [US1] `memoManager(initialMemos)` Alpine.js コンポーネント（memos 配列・新規作成フォーム・インライン編集・削除・シーク seekTo 連携）を resources/views/archives/show.blade.php のメモセクションとして追加する（ArchiveController から $memos を渡す対応含む）
- [X] T025 [US1] タイムスタンプメモセクションのスタイル（メモカード・タイムスタンプボタン・フォーム）を resources/css/app.css に追加する

**Checkpoint**: `php artisan test tests/Feature/Memo/` がパスし、ブラウザで新規メモ保存→リスト更新→シークが動作すること

---

## Phase 4: User Story 2 — 動画全体ノート（全体感想）の保存（Priority: P2）

**Goal**: 動画全体への感想を1件保存・上書き・削除できる。保存成功後にフィードバックを表示する

**Independent Test**: 全体感想欄にテキスト入力→保存→ページリロード後も表示され、削除で欄が空になることを確認する

### テスト（US2）

- [X] T026 [P] [US2] 動画ノート upsert・削除・所有権保護の Feature テストを tests/Feature/Memo/VideoNoteTest.php に作成する

### 実装（US2）

- [X] T027 [P] [US2] SaveVideoNoteRequest（body: string 1〜5000 required）を app/Http/Requests/SaveVideoNoteRequest.php に作成する
- [X] T028 [P] [US2] SaveVideoNoteAction（updateOrCreate でユーザー・動画単位 upsert）を app/Actions/Memo/SaveVideoNoteAction.php に作成する
- [X] T029 [P] [US2] DeleteVideoNoteAction（ノート削除）を app/Actions/Memo/DeleteVideoNoteAction.php に作成する
- [X] T030 [US2] VideoNoteController（upsert: PUT → 200 JSON {status,updated_at}、destroy: DELETE → 204）を app/Http/Controllers/VideoNoteController.php に作成する（T027〜T029 完了後）
- [X] T031 [US2] ArchiveController::show() で $videoNote（現ユーザーの動画ノート、nullable）を view に渡す処理を app/Http/Controllers/ArchiveController.php に追加する
- [X] T032 [US2] videoNoteManager Alpine.js コンポーネント（テキストエリア・保存ボタン（空時 disabled）・「保存しました」トースト・削除ボタン）を resources/views/archives/show.blade.php の動画ノートセクションとして追加する
- [X] T033 [US2] 動画ノートセクションのスタイル（テキストエリア・保存ボタン・トーストフィードバック）を resources/css/app.css に追加する

**Checkpoint**: `php artisan test tests/Feature/Memo/VideoNoteTest.php` がパスし、ブラウザで保存→リロード→削除が動作すること

---

## Phase 5: User Story 3 — タグ付与とシステムタグ（Priority: P3）

**Goal**: タイムスタンプメモにシステムタグ・ユーザー固有タグを付与・解除できる

**Independent Test**: メモ作成フォームでシステムタグ「笑った」を選択して保存し、メモ一覧でタグが表示されることを確認する

### テスト（US3）

- [X] T034 [P] [US3] タグ付与・インライン作成・所有権・システムタグ共有の Feature テストを tests/Feature/Memo/TagTest.php に作成する

### 実装（US3）

- [X] T035 [US3] tag_ids と new_tag_names バリデーションルール（各タグ名 50 文字以内、UUIDリスト）を app/Http/Requests/StoreTimestampMemoRequest.php と app/Http/Requests/UpdateTimestampMemoRequest.php に追加する
- [X] T036 [US3] ArchiveController::show() で $systemTags（Tag::system()->get()）と $userTags（認証ユーザー固有タグ）を view に渡す処理を app/Http/Controllers/ArchiveController.php に追加する
- [X] T037 [US3] memoManager Alpine コンポーネントにタグ UI（システムタグチップトグル・カスタムタグ入力 Enter で追加・選択済みチップ表示）を resources/views/archives/show.blade.php に追加する
- [X] T038 [US3] タグチップ・選択状態・タグ入力フィールドのスタイルを resources/css/app.css に追加する

**Checkpoint**: `php artisan test tests/Feature/Memo/TagTest.php` がパスし、タグ付きメモの保存・表示が動作すること

---

## Phase 6: User Story 4 — お気に入り登録と神回・お気に入り一覧（Priority: P4）

**Goal**: タイムスタンプメモをお気に入り登録でき、神回・お気に入り一覧で推し別・タグ別・年月別にフィルタリングできる

**Independent Test**: お気に入りボタンを押し `/favorites` でそのメモが表示されること、フィルターが機能することを確認する

### テスト（US4）

- [X] T039 [P] [US4] お気に入りトグル（登録・解除・所有権保護）の Feature テストを tests/Feature/Memo/TimestampMemoFavoriteTest.php に作成する
- [X] T040 [P] [US4] 神回・お気に入り一覧（表示・フィルタリング・ページネーション）の Feature テストを tests/Feature/Favorites/FavoritesIndexTest.php に作成する

### 実装（US4）

- [X] T041 [P] [US4] ToggleMemoFavoriteAction（is_favorite を反転して保存）を app/Actions/Memo/ToggleMemoFavoriteAction.php に作成する
- [X] T042 [US4] TimestampMemoFavoriteController（PATCH → 200 JSON {is_favorite}）を app/Http/Controllers/TimestampMemoFavoriteController.php に作成する（T041 完了後）
- [X] T043 [US4] memoManager Alpine コンポーネントのお気に入りトグルボタン（クリック → PATCH → is_favorite 状態更新）を resources/views/archives/show.blade.php に追加する
- [X] T044 [US4] FavoriteController::index()（is_favorite=true のメモを eager load 付きで取得、oshi_id / tag_id / month クエリパラメータでフィルタ、ページネーション）を app/Http/Controllers/FavoriteController.php に作成する
- [X] T045 [US4] 神回・お気に入り一覧ページ（フィルターフォーム・メモカード一覧・ページネーション）を resources/views/favorites/index.blade.php に作成する
- [X] T046 [US4] サイドバーナビゲーションに「神回」リンク（/favorites）を resources/views/layouts/app.blade.php に追加する（x-icon 使用）
- [X] T047 [US4] 神回・お気に入り一覧ページのスタイル（フィルターバー・メモカード・動画タイトル表示）を resources/css/app.css に追加する

**Checkpoint**: `php artisan test tests/Feature/Memo/ tests/Feature/Favorites/` が全パスし、ブラウザで全 4 US の動作を確認できること

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: セキュリティ・パフォーマンス・E2E 検証の仕上げ

- [X] T048 [P] メモ本文の XSS 対策確認：show.blade.php・favorites/index.blade.php で `{{ $memo->body }}` が HTML エスケープされていることをコードレビューで確認し、エスケープなし `{!! !!}` が使われていないことを保証する
- [X] T049 [P] N+1 クエリ防止：ArchiveController::show() の $memos クエリに `->with('tags')` を、FavoriteController::index() に `->with(['tags', 'youtubeVideo.youtubeChannel'])` を追加する（app/Http/Controllers/ 内 2 ファイル）
- [ ] T050 quickstart.md の全シナリオ（US1〜US4）をブラウザで実施し、デスクトップ 960px・モバイル 390px の両幅で表示確認する

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: 依存なし — 即開始可能
- **Phase 2 (Foundational)**: Phase 1 完了後 — 全 US をブロック
- **Phase 3 (US1)**: Phase 2 完了後 — 独立実施可能
- **Phase 4 (US2)**: Phase 2 完了後 — US1 と並行実施可能
- **Phase 5 (US3)**: Phase 3 完了後（タグ UI が memo フォームに組み込まれるため）
- **Phase 6 (US4)**: Phase 3 完了後（お気に入りは memo が前提）
- **Phase 7 (Polish)**: 全 US 完了後

### User Story Dependencies

- **US1 (P1)**: Phase 2 完了後 — 独立開始可能
- **US2 (P2)**: Phase 2 完了後 — US1 と並行可能
- **US3 (P3)**: US1 完了後（タグ UI は memo フォームに追加される）
- **US4 (P4)**: US1 完了後（お気に入りトグルは memo リストに追加される）

### Within Each User Story

- テスト → モデル/Request → Action → Controller → Blade/Alpine の順
- T019〜T021 の Action 完了後に T022 の Controller を作成
- T023（window 公開）完了後に T024（Alpine 統合）を実施

---

## Parallel Example: Phase 2（Foundational）

```bash
# 並行実施可能なタスク群（異なるファイル）:
Task: T003 tags migration
Task: T004 timestamp_memos migration
Task: T005 video_notes migration
Task: T007 TagScope Enum
Task: T008 Tag model
Task: T009 TimestampMemo model
Task: T010 VideoNote model
Task: T011 TimestampMemoPolicy
Task: T012 VideoNotePolicy
Task: T013 TagPolicy
# T006（timestamp_memo_tags）は T003 と T004 完了後
```

## Parallel Example: User Story 1（Phase 3）

```bash
# テストと Request は並行実施可能:
Task: T015 TimestampMemoStoreTest
Task: T016 TimestampMemoOwnershipTest
Task: T017 StoreTimestampMemoRequest
Task: T018 UpdateTimestampMemoRequest
Task: T019 CreateTimestampMemoAction
Task: T020 UpdateTimestampMemoAction
Task: T021 DeleteTimestampMemoAction

# Controller は T017〜T021 完了後:
Task: T022 TimestampMemoController
```

---

## Implementation Strategy

### MVP First（User Story 1 のみ）

1. Phase 1: Setup 完了
2. Phase 2: Foundational 完了（CRITICAL）
3. Phase 3: US1 完了（タイムスタンプメモ CRUD + シーク）
4. **STOP and VALIDATE**: `php artisan test tests/Feature/Memo/` + ブラウザ確認
5. デモ可能な状態

### Incremental Delivery

1. Setup + Foundational → 基盤完了
2. US1 → タイムスタンプメモ記録・シーク（MVP!）
3. US2 → 動画ノート保存（独立してデモ可能）
4. US3 → タグ付与（US1 に積み上げ）
5. US4 → お気に入り一覧（US1 に積み上げ）
6. Polish → 仕上げ

---

## Notes

- [P] タスク = 異なるファイル、依存なし（並行実施可能）
- [Story] ラベルで各 US へのトレーサビリティを維持
- メモ本文のエスケープ（`{{ }}`）を徹底し `{!! !!}` を使わない（XSS 防止）
- Alpine.js は楽観的更新しない（FR-003a: サーバー応答確認後にのみリスト更新）
- コードコメントは日本語で記載（憲法 v1.1.0）
- Policy 違反は 403、存在しないリソースは 404 を返す

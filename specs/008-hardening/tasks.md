# Tasks: 本番品質強化（hardening）

**Input**: Design documents from `/specs/008-hardening/`

**Prerequisites**: plan.md ✅ spec.md ✅ research.md ✅ data-model.md ✅ contracts/routes.md ✅

**Organization**: タスクはユーザーストーリー単位でグループ化し、独立実装・独立テストを可能にする。

## Format: `[ID] [P?] [Story] Description`

- **[P]**: 並列実行可（異なるファイル、未完タスクへの依存なし）
- **[Story]**: 対応するユーザーストーリー（US1〜US5）
- 各タスクに具体的なファイルパスを明記する

---

## Phase 1: Setup（共有インフラ）

**Purpose**: 全ユーザーストーリーが依存する基盤変更。US3〜US5 より先に完了させる。

- [x] T001 `database/migrations/2026_06_22_000001_add_missing_indexes.php` を新設し、`user_watch_items(profile_id, updated_at)` と `user_channels(profile_id, sync_enabled)` の複合インデックスを追加する（data-model.md §追加インデックス参照）
- [x] T002 [P] `app/Providers/AppServiceProvider.php` の `boot()` メソッドに `channel-sync` リミッター（5回/分、ユーザーID単位）を追加する（既存 `oshi-mutations` の定義の直後に挿入）
- [x] T003 [P] `resources/views/layouts/minimal.blade.php` を新設する（ロゴ + コンテンツ slot + フッターのみ、認証依存なし、CLAUDE.md カラートークン・フォントスタック準拠）

**Checkpoint**: T001〜T003 完了後、US1〜US5 の実装を並列で開始できる

---

## Phase 2: US1 — 不正なデータ操作から自分のデータを守る（Priority: P1）

**Goal**: Policy 全カバレッジの最終確認と、認証フロー横断テストの追加

**Independent Test**: `php artisan test tests/Feature/Hardening/MainFlowTest.php` が PASS し、全所有権テスト群が PASS すること

### US1 実装

- [x] T004 [US1] `app/Policies/VideoNotePolicy.php` を開き、`view`・`update`・`delete` の全アクションで `$user->id === $videoNote->userWatchItem->profile_id` チェックが適切に行われているか確認する。不足があれば補完する（research.md §1 参照）
- [x] T005 [US1] `tests/Feature/Hardening/MainFlowTest.php` を新設する（認証済みユーザーが アーカイブ一覧→配信詳細→タイムスタンプメモ作成→神回トグル→`/favorites` を順に操作する横断テスト。各ステップで HTTP 200/302 かつ主要コンテンツの存在を確認する）

**Checkpoint**: US1 完了 — 全 Policy 所有権テストがグリーン、横断テストが PASS

---

## Phase 3: US2 — 過剰なリクエストからサービスを守る（Priority: P1）

**Goal**: `fetchOlder` 専用レート制限（5回/分）を実装し、レート制限テストを追加

**Independent Test**: `fetchOlder` を 6 回連打すると 6 回目が 302（Blade リダイレクト時）または 429 を返すことをテストで確認できる

### US2 実装

- [x] T006 [US2] `routes/web.php` の `fetchOlder` ルートを `throttle:oshi-mutations` グループから独立させ、専用ミドルウェア `throttle:channel-sync` を個別に付与する（contracts/routes.md §変更ルート参照）
- [x] T007 [US2] `tests/Feature/Hardening/RateLimitTest.php` を新設する（以下の 2 シナリオを含む）
  - `RateLimiter::clear()` でリセット後、`fetchOlder` を 5 回呼んで全て成功 → 6 回目が 429
  - `memo-mutations` を 61 回呼んで 61 回目が 429（既存リミッターの動作確認）

**Checkpoint**: US2 完了 — レート制限テストがグリーン、`fetchOlder` が 5回/分に制限されている

---

## Phase 4: US3 — エラー発生時も安心して使い続けられる（Priority: P2）

**Goal**: カスタムエラーページ（404・500）の実装と、API 障害フォールバックの確認テスト追加

**Independent Test**: 存在しない URL へのアクセスで `resources/views/errors/404.blade.php` が表示される。`Http::fake()` で YouTube API 障害をシミュレートしても `/archive`・`/memos`・`/favorites` が 200 を返す

### US3 実装

- [x] T008 [P] [US3] `resources/views/errors/404.blade.php` を新設する（`layouts/minimal.blade.php` を継承、「ページが見つかりません」メッセージ + ホームへ戻るリンク、スタックトレース非表示、CLAUDE.md デザイン準拠）
- [x] T009 [P] [US3] `resources/views/errors/500.blade.php` を新設する（`layouts/minimal.blade.php` を継承、「エラーが発生しました。時間をおいてお試しください。」+ ホームへ戻るリンク、スタックトレース非表示）
- [x] T010 [US3] `tests/Feature/Hardening/ApiFallbackTest.php` を新設する（`Http::fake(['*.googleapis.com/*' => Http::response(null, 503)])` で障害シミュレーション後、`GET /archive`・`GET /memos`・`GET /favorites` が HTTP 200 を返すことを確認する）

**Checkpoint**: US3 完了 — カスタムエラーページが表示され、YouTube API 停止時も閲覧画面が維持される

---

## Phase 5: US4 — 大量データでもスムーズに閲覧できる（Priority: P2）

**Goal**: インデックス追加（Phase 1 で完了）に加え、一覧 Controller の N+1 クエリを確認・修正する

**Independent Test**: `php artisan test` が全テスト PASS し、`telescope` または `debugbar` で一覧ページのクエリ件数がページ件数に比例して増加しないことを確認する

### US4 実装

- [x] T011 [US4] `app/Http/Controllers/ArchiveController.php` の `index()` を確認し、`userWatchItem`・`youtubeVideo`・`youtubeChannel` などのリレーションが `with()` で一括ロードされているか検証する。不足があれば eager load を追加する
- [x] T012 [US4] `app/Http/Controllers/FavoriteController.php` の `index()` を確認し、神回タブ（`is_favorite = true`）の `youtubeVideo`・`youtubeChannel` リレーションが一括ロードされているか検証する。不足があれば eager load を追加する
- [x] T013 [US4] `app/Http/Controllers/MemoController.php` の `index()` を確認し、`userWatchItem`・`youtubeVideo`・`youtubeChannel`・`tags` リレーションが一括ロードされているか検証する。不足があれば eager load を追加する

**Checkpoint**: US4 完了 — `php artisan migrate` でインデックスが追加され、N+1 クエリが解消されている

---

## Phase 6: US5 — サービス利用規約とプライバシーポリシーを確認できる（Priority: P3）

**Goal**: `/privacy`・`/terms` ルートの新設、法的ページビュー作成、フッターリンク追加

**Independent Test**: 未認証ユーザーが `GET /privacy` と `GET /terms` へアクセスすると HTTP 200 が返る。フッターの両リンクがクリック可能

### US5 実装

- [x] T014 [US5] `app/Http/Controllers/LegalController.php` を新設する（`privacy()` メソッドで `legal/privacy` ビューを返却、`terms()` メソッドで `legal/terms` ビューを返却。Blade 返却のみ、ロジックなし）
- [x] T015 [P] [US5] `resources/views/legal/privacy.blade.php` を新設する（`layouts/minimal.blade.php` を継承。収集情報・利用目的・免責事項・最終更新日（2026年6月22日）を含むドラフト版プライバシーポリシー）
- [x] T016 [P] [US5] `resources/views/legal/terms.blade.php` を新設する（`layouts/minimal.blade.php` を継承。サービス概要・利用条件・禁止事項・免責事項・最終更新日（2026年6月22日）を含むドラフト版利用規約）
- [x] T017 [US5] `routes/web.php` に公開ルート（認証不要）として `GET /privacy` → `LegalController@privacy`（名前: `legal.privacy`）と `GET /terms` → `LegalController@terms`（名前: `legal.terms`）を追加する（contracts/routes.md §新規ルート参照）
- [x] T018 [US5] `resources/views/layouts/app.blade.php` のフッターに「プライバシーポリシー」（`route('legal.privacy')`）と「利用規約」（`route('legal.terms')`）のリンクを追加する（CLAUDE.md のデザイン・フォント規約に準拠、`--an-muted` カラー使用）

**Checkpoint**: US5 完了 — 未認証で `/privacy`・`/terms` にアクセス可能、フッターにリンクが表示される

---

## Phase 7: Polish & 横断確認

**Purpose**: 全変更の統合確認と品質最終チェック

- [x] T019 [P] `npm run build` を実行し、Vite ビルドエラーがないことを確認する（CSS 変更があった場合）
- [x] T020 `php artisan migrate` を実行し、インデックス追加マイグレーションがエラーなく完了することを確認する
- [x] T021 `php artisan test` を実行し、全テスト（既存 + Hardening/ 新規テスト）が PASS することを確認する
- [ ] T022 Desktop（960px）と Mobile（390px）でエラーページ・規約ページの表示を確認する（CLAUDE.md デザインガイドライン準拠）

---

## Dependencies & 実行順序

### Phase 依存関係

- **Phase 1（Setup）**: 依存なし → すぐ開始可能
- **Phase 2（US1）**: T001〜T003 完了後に開始
- **Phase 3（US2）**: T001〜T003 完了後に開始（US1 と並列可）
- **Phase 4（US3）**: T003 完了後に開始（US1・US2 と並列可）
- **Phase 5（US4）**: T001 完了後に開始（他 US と並列可）
- **Phase 6（US5）**: T003 完了後に開始（他 US と並列可）
- **Phase 7（Polish）**: 全 US 完了後

### ユーザーストーリー間の依存

- **US1（P1）**: T003 後に開始。他 US に依存なし
- **US2（P1）**: T002 後に開始。他 US に依存なし（routes/web.php はUS5とも触れるが競合しない）
- **US3（P2）**: T003 後に開始。他 US に依存なし
- **US4（P2）**: T001 後に開始。他 US に依存なし
- **US5（P3）**: T003 後に開始。T014（LegalController）→ T015/T016 → T017 → T018 の順序あり

### 各フェーズ内の並列機会

- T002・T003 → 並列実行可（異なるファイル）
- T008・T009 → 並列実行可（異なるエラービュー）
- T015・T016 → 並列実行可（異なる規約ビュー）
- T019・T020 → 並列実行可（独立操作）

---

## Parallel Example: Phase 1

```bash
# T002 と T003 は同時に実行可能:
Task: "AppServiceProvider に channel-sync リミッター追加"
Task: "minimal.blade.php を新設"
```

---

## Implementation Strategy

### MVP First（US1・US2 の P1 のみ）

1. Phase 1 完了（T001〜T003）
2. Phase 2（US1）: Policy 確認 + 横断テスト
3. Phase 3（US2）: fetchOlder リミッター付け替え + レート制限テスト
4. **STOP & VALIDATE**: `php artisan test` でグリーンを確認
5. P2・P3 は後続スプリントで対応可能

### Incremental Delivery

1. Setup + US1 + US2 → セキュリティ基盤確立（公開最低条件）
2. US3（エラーページ・フォールバック）→ 運用品質向上
3. US4（インデックス・N+1 解消）→ パフォーマンス保証
4. US5（規約ページ）→ 法的要件充足 → 公開可

---

## Notes

- `[P]` タスクは異なるファイルを編集するため並列実行可能
- `[Story]` ラベルでタスクと spec.md のユーザーストーリーを対応付ける
- Policy カバレッジ（T004）は確認のみの可能性が高い。既に完全実装済みの場合はスキップ可
- 規約ページ（T015・T016）の文言はドラフト版。法的レビューは本 Feature スコープ外
- テストは実 YouTube API を呼ばない（`Http::fake()` 必須、憲法 VI）

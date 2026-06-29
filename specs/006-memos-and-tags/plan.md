# Implementation Plan: メモ・タグ・神回お気に入り

**Branch**: `006-memos-and-tags` | **Date**: 2026-06-20 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/006-memos-and-tags/spec.md`

## Summary

Feature 005 で構築した YouTube IFrame Player・配信詳細ページ（`/archives/{watchItem}`）を基盤として、タイムスタンプメモ・動画ノート・タグ・お気に入りの 4 エンティティを追加する。メモ CRUD は JSON レスポンス + Alpine.js によるインライン更新で実現し、神回・お気に入り一覧は推し別・タグ別・年月別のサーバーサイドフィルタリングで提供する。技術的詳細は [research.md](research.md) を参照。

## Technical Context

**Language/Version**: PHP 8.4 / Laravel 12

**Primary Dependencies**: Blade、Alpine.js、Eloquent ORM、Laravel RateLimiter、Laravel Policy

**Storage**: PostgreSQL 16（本番: Supabase PostgreSQL） — 新規テーブル 4 件（`timestamp_memos`、`video_notes`、`tags`、`timestamp_memo_tags`）

**Testing**: Pest / PHPUnit（Feature テスト中心）

**Target Platform**: Linux サーバ（Docker Compose ローカル / Supabase 本番）

**Project Type**: Web アプリケーション（Laravel モノリス + Blade テンプレート）

**Performance Goals**:
- SC-003: タイムスタンプメモ一覧を持つ配信詳細画面が 3 秒以内にロード（メモ 50 件以内）
- SC-002: タイムスタンプクリックから動画再生開始まで 2 秒以内（IFrame API `seekTo` の応答）
- SC-004: 神回・お気に入り一覧のフィルター結果が正常表示

**Constraints**:
- Alpine.js + Vanilla JS のみ（React/Vue 追加禁止、憲法 技術制約）
- FR-003a: メモ保存はサーバー応答確認後にのみ Alpine.js リストを更新（楽観的更新禁止）
- 他ユーザーのメモ・ノートへの操作は Policy で 403（憲法 III）
- メモ本文を HTML として描画しない（XSS 防止、憲法 技術制約）
- `user_watch_items` テーブルを本 Feature では変更しない（お気に入り動画は将来拡張）

**Scale/Scope**:
- 1 ユーザーあたり最大数百件のタイムスタンプメモを想定（通常 50 件以内 / 動画）
- タグ: システムタグ 10〜20 件、ユーザー固有タグは上限なし（実用上数十件）

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| 原則 | 評価 | 対応 |
|------|------|------|
| **I. 薄い Controller** | ✅ | `TimestampMemoController` / `VideoNoteController` / `FavoriteController` は FormRequest → Policy → Action → JSON/View のみ。ビジネスロジックは Action 層へ分離 |
| **II. 共有マスタ分離** | ✅ | `youtube_videos` は読み取りのみ参照。`timestamp_memos` / `video_notes` / `tags`（ユーザー固有）はユーザーデータとして管理。システムタグ（`is_system=true`）は共有マスタとして seeder で管理し、一般ユーザーから直接更新させない |
| **III. 所有権認可** | ✅ | `TimestampMemoPolicy` / `VideoNotePolicy` / `TagPolicy` で所有権確認必須。他ユーザーのメモ・ノートへの操作は 403 |
| **IV. シークレット管理** | ✅ | CSRF トークンは `meta[name="csrf-token"]` 経由。メモ本文の全文ログ出力禁止 |
| **V. YT クォータ** | ✅ | 本 Feature は YouTube Data API を使用しない。IFrame の `seekTo` はクォータ非消費 |
| **VI. テストファースト** | ✅ | タイムスタンプメモ CRUD・所有権保護・動画ノート保存・お気に入りトグルを Feature テストで担保 |
| **VII. 進捗データの正直表示** | ✅ | メモ・タグは視聴進捗と独立。`user_watch_items` への自動変更なし |

**Constitution Check 結果**: ✅ 全原則クリア。`Complexity Tracking` 記入不要。

## Project Structure

### Documentation (this feature)

```text
specs/006-memos-and-tags/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   └── routes.md        # Phase 1 output
└── tasks.md             # Phase 2 output (/speckit-tasks で生成)
```

### Source Code (repository root)

```text
app/
├── Actions/
│   ├── WatchItem/                         # [既存]
│   └── Memo/                              # [新規]
│       ├── CreateTimestampMemoAction.php  # メモ保存 + タグ処理
│       ├── UpdateTimestampMemoAction.php  # メモ更新 + タグ差分更新
│       ├── DeleteTimestampMemoAction.php  # メモ削除（関連タグ自動 detach）
│       ├── ToggleMemoFavoriteAction.php   # is_favorite トグル
│       ├── SaveVideoNoteAction.php        # 動画ノート upsert
│       └── DeleteVideoNoteAction.php      # 動画ノート削除
├── Enums/
│   └── TagScope.php                       # [新規] system / user_owned
├── Http/
│   ├── Controllers/
│   │   ├── TimestampMemoController.php    # [新規] メモ CRUD（JSON レスポンス）
│   │   ├── TimestampMemoFavoriteController.php  # [新規] お気に入りトグル
│   │   ├── VideoNoteController.php        # [新規] 動画ノート upsert / destroy
│   │   └── FavoriteController.php         # [新規] 神回・お気に入り一覧
│   └── Requests/
│       ├── StoreTimestampMemoRequest.php  # [新規]
│       ├── UpdateTimestampMemoRequest.php # [新規]
│       └── SaveVideoNoteRequest.php       # [新規]
├── Models/
│   ├── TimestampMemo.php                  # [新規]
│   ├── VideoNote.php                      # [新規]
│   └── Tag.php                            # [新規]
├── Policies/
│   ├── TimestampMemoPolicy.php            # [新規]
│   ├── VideoNotePolicy.php                # [新規]
│   └── TagPolicy.php                      # [新規]
└── Services/                              # [既存]

database/
├── migrations/
│   ├── 2026_06_20_000007_create_tags_table.php
│   ├── 2026_06_20_000008_create_timestamp_memos_table.php
│   ├── 2026_06_20_000009_create_timestamp_memo_tags_table.php
│   └── 2026_06_20_000010_create_video_notes_table.php
└── seeders/
    └── SystemTagSeeder.php                # [新規] 初期システムタグ投入

resources/
├── css/
│   └── app.css                            # [更新] メモ・タグ・お気に入り UI スタイル追加
└── views/
    ├── archives/
    │   └── show.blade.php                 # [更新] メモセクション + 動画ノートセクション追加
    ├── components/
    │   ├── timestamp-memo-list.blade.php  # [新規] タイムスタンプメモ一覧コンポーネント
    │   └── video-note-form.blade.php      # [新規] 動画ノートフォームコンポーネント
    └── favorites/
        └── index.blade.php                # [新規] 神回・お気に入り一覧

tests/
└── Feature/
    ├── Memo/
    │   ├── TimestampMemoStoreTest.php     # [新規]
    │   ├── TimestampMemoUpdateTest.php    # [新規]
    │   ├── TimestampMemoDeleteTest.php    # [新規]
    │   ├── TimestampMemoOwnershipTest.php # [新規]
    │   ├── TimestampMemoFavoriteTest.php  # [新規]
    │   ├── VideoNoteTest.php              # [新規]
    │   └── TagTest.php                    # [新規]
    └── Favorites/
        └── FavoritesIndexTest.php         # [新規]
```

**Structure Decision**: Laravel モノリス（Option 1 相当）。既存のディレクトリ規約に沿い、Action 層を `app/Actions/Memo/` に集約する。

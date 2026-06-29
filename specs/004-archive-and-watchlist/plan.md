# Implementation Plan: アーカイブ閲覧と見るリスト管理

**Branch**: `004-archive-and-watchlist` | **Date**: 2026-06-20 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/004-archive-and-watchlist/spec.md`

## Summary

Feature 003 で同期された `youtube_videos` をユーザーが最初に活用する画面群を実装する。新着アーカイブ一覧・見るリスト・ステータス管理・ホームサマリーの4機能を Laravel + Blade + Alpine.js で構築する。`user_watch_items` テーブルを新規作成し、WatchStatus Enum / Action / Policy / Controller の標準レイヤー構成を採用する（研究詳細は [research.md](research.md) 参照）。

## Technical Context

**Language/Version**: PHP 8.4 / Laravel 12

**Primary Dependencies**: Blade、Alpine.js、Tailwind CSS、Eloquent ORM、Laravel Paginator

**Storage**: PostgreSQL 16（本番: Supabase PostgreSQL）

**Testing**: Pest / PHPUnit（既存プロジェクトの選択に準拠）

**Target Platform**: Linux サーバ（Docker Compose ローカル / Supabase 本番）

**Project Type**: Web アプリケーション（Laravel モノリス + Blade テンプレート）

**Performance Goals**:
- SC-002: 新着アーカイブ一覧初期表示 3秒以内（100件以上の動画があっても）
- SC-003: ステータス変更操作 2秒以内応答

**Constraints**:
- 全件取得禁止（FR-012）: `paginate(20)` 必須
- N+1 禁止（憲法 技術制約）: eager load 必須
- `is_available=false` 動画は一覧から完全除外（FR-011）
- 他ユーザーデータへのアクセス 100% 拒否（FR-009、SC-005）

**Scale/Scope**:
- 1ユーザーあたり数百〜数千件の動画を想定
- ページネーション 1ページ 20件

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| 原則 | 評価 | 対応 |
|------|------|------|
| **I. 薄い Controller** | ✅ | `AddToWatchListAction`, `UpdateWatchStatusAction`, `DeleteWatchItemAction` を `app/Actions/WatchItem/` に分離。Controller は FormRequest → Action → Redirect のみ |
| **II. 共有マスタ分離** | ✅ | `youtube_videos` は変更しない。ユーザー固有状態は `user_watch_items` に分離 |
| **III. 所有権認可** | ✅ | `UserWatchItemPolicy` 全 CRUD に実装。クエリにも `profile_id` フィルタを必ず付与 |
| **IV. シークレット管理** | ✅ | このFeatureで新規シークレットなし。既存認証基盤を踏襲 |
| **V. YT クォータ** | ✅ | このFeatureは YouTube API を呼ばない（閲覧のみ） |
| **VI. テストファースト** | ✅ | Archive一覧・WatchItem CRUD・所有権保護・ホームサマリーを Feature テストで担保 |
| **VII. 進捗データの正直表示** | ✅ | `last_position_seconds` / `watching` 遷移は Feature 005 に委ねる。誤認させる表示なし |

**Constitution Check 結果**: ✅ 全原則クリア。`Complexity Tracking` 記入不要。

## Project Structure

### Documentation (this feature)

```text
specs/004-archive-and-watchlist/
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
│   └── WatchItem/
│       ├── AddToWatchListAction.php      # 見るリスト追加 / 見送り（upsert）
│       ├── UpdateWatchStatusAction.php   # ステータス変更 + タイムスタンプ自動設定
│       └── DeleteWatchItemAction.php     # 削除（未整理に戻す）
├── Enums/
│   └── WatchStatus.php                  # want_to_watch / watching / watched / skipped
├── Http/
│   ├── Controllers/
│   │   ├── ArchiveController.php         # GET /archive
│   │   └── UserWatchItemController.php   # /watchlist CRUD + POST /archive/{video}/watch-item
│   └── Requests/
│       ├── StoreUserWatchItemRequest.php  # status: in:want_to_watch,skipped
│       └── UpdateWatchStatusRequest.php   # status: in:want_to_watch,watched,skipped
├── Models/
│   └── UserWatchItem.php                 # HasUuids, BelongsTo Profile/YoutubeVideo
└── Policies/
    └── UserWatchItemPolicy.php           # view/create/update/delete 所有権確認

database/
└── migrations/
    └── 2026_06_20_000006_create_user_watch_items_table.php

resources/views/
├── archive/
│   └── index.blade.php                  # 新着アーカイブ一覧
└── watchlist/
    └── index.blade.php                  # 見るリスト（タブ付き）

tests/
└── Feature/
    ├── Archive/
    │   └── ArchiveIndexTest.php
    └── WatchList/
        ├── StoreWatchItemTest.php
        ├── UpdateWatchStatusTest.php
        ├── DeleteWatchItemTest.php
        └── HomeSummaryTest.php
```

**Structure Decision**: Laravel モノリス（Option 1相当）。Feature 002/003 と同一パターンを踏襲し、`app/Actions/{Domain}/` に Action を分離してController を薄くする。

## Implementation Phases

### Phase A — データ層

1. **Migration**: `user_watch_items` テーブル作成（UNIQUE・CHECK・インデックスを含む）
2. **Enum**: `App\Enums\WatchStatus` — 4値 Backed Enum + `label()` / `timestamps()` ヘルパ
3. **Model**: `App\Models\UserWatchItem` — HasUuids、BelongsTo、casts（`WatchStatus`）
4. **Factory**: `UserWatchItemFactory`（テスト用）

### Phase B — ドメイン層

5. **Action**: `AddToWatchListAction` — `updateOrCreate` で upsert（FR-007 対応）、`added_at` セット
6. **Action**: `UpdateWatchStatusAction` — ステータスに応じたタイムスタンプ自動設定（FR-008）
7. **Action**: `DeleteWatchItemAction` — シンプルな削除（FR-015）
8. **Policy**: `UserWatchItemPolicy` — `create`（チャンネル所有確認含む）/ `view` / `update` / `delete`

### Phase C — Controller 層

9. **ArchiveController**: `index` — クエリビルダで未整理一覧、フィルタ、`paginate(20)`
10. **UserWatchItemController**: `store` — Policy `create` → `AddToWatchListAction`
11. **UserWatchItemController**: `index` — タブ別 `user_watch_items` + タブカウント
12. **UserWatchItemController**: `update` — Policy `update` → `UpdateWatchStatusAction`
13. **UserWatchItemController**: `destroy` — Policy `delete` → `DeleteWatchItemAction`
14. **HomeController 拡張**: ホームサマリー件数を追加（FR-010）
15. **FormRequest**: `StoreUserWatchItemRequest` / `UpdateWatchStatusRequest`

### Phase D — View 層

16. **`archive/index.blade.php`**: 推し・video_type フィルタ（Alpine.js auto-submit）、動画カード、ページネーション
17. **`watchlist/index.blade.php`**: ステータスタブ、動画カード、ステータス変更 UI、削除ボタン
18. **ホーム画面更新**: `home.blade.php` のサマリーカードに件数バインド

### Phase E — ルーティング・テスト

19. **Routes**: `web.php` に新着アーカイブ + 見るリストルートを追加
20. **Feature テスト**: Archive一覧・WatchItem CRUD・所有権拒否・ホームサマリー正確性

## Key Design Decisions

| 決定事項 | 選択 | 理由 |
|---------|------|------|
| 重複防止 | `updateOrCreate`（upsert） | DB UNIQUE 制約と組み合わせて確実に防ぐ（FR-007） |
| ページネーション | オフセット `paginate(20)` | カーソル方式より実装コストが低く MVP 規模では十分 |
| 楽観的 UI | 採用しない（サーバ応答後更新） | SC-003 の2秒要件を POST→redirect で満たせる |
| タブ切替 | URL クエリパラメータ（サーバレンダリング） | ブックマーク・戻るボタンが自然に動く |
| ホームサマリー | `COUNT` 2クエリ | シンプルかつ N+1 なし |

## Artifacts

- [research.md](research.md) — 技術決定8項目
- [data-model.md](data-model.md) — `user_watch_items` スキーマ + WatchStatus Enum
- [contracts/routes.md](contracts/routes.md) — ルート・Action・Policy インターフェース
- [quickstart.md](quickstart.md) — 検証シナリオ + テストコマンド

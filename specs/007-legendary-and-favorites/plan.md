# Implementation Plan: 神回登録・神回お気に入りページ改修・タイムスタンプメモ保管庫の新設

**Branch**: `007-legendary-and-favorites` | **Date**: 2026-06-22 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/007-legendary-and-favorites/spec.md`

## Summary

Feature 006 で構築したタイムスタンプメモ基盤と `/favorites` ページを拡張し、3 点の改善を行う。①配信詳細ページに「神回に登録」トグルを追加（`user_watch_items.is_favorite` の操作 UI）、② `/favorites` を「神回動画タブ」+「お気に入りメモタブ」の2タブ構成に刷新、③全タイムスタンプメモを探せる `/memos` 保管庫を新設してサイドバー・ホームの壊れた導線を修正する。マイグレーション不要（既存カラムの活用のみ）。詳細は [research.md](research.md)・[data-model.md](data-model.md)・[contracts/routes.md](contracts/routes.md) を参照。

## Technical Context

**Language/Version**: PHP 8.4 / Laravel 12

**Primary Dependencies**: Blade、Alpine.js、Eloquent ORM、Laravel Policy、Laravel RateLimiter

**Storage**: PostgreSQL 16（本番: Supabase PostgreSQL） — 既存テーブルのみ。マイグレーションなし。

**Testing**: Pest / PHPUnit（Feature テスト中心）

**Target Platform**: Linux サーバ（Docker Compose ローカル / Supabase 本番）

**Project Type**: Web アプリケーション（Laravel モノリス + Blade テンプレート）

**Performance Goals**:
- SC-001: 神回トグル（PATCH）のサーバー応答から Alpine.js のボタン状態反映まで体感 1 秒以内
- SC-004: Desktop 960px / Mobile 390px の両幅で横スクロールなし

**Constraints**:
- Alpine.js + Vanilla JS のみ（React/Vue 追加禁止、憲法 技術制約）
- `/memos` は★トグル操作を提供しない（FR-017、`archives/show` 専用）
- 他ユーザーの watchItem への神回トグルは Policy で 403（憲法 III）
- タブ切り替えはサーバーサイド GET パラメータ（`?tab=kamikai|memos`）

**Scale/Scope**:
- 1 ユーザーあたり神回動画は通常数十〜数百件、タイムスタンプメモは全体で数百件を想定
- ページネーション 20 件/ページで対応

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| 原則 | 評価 | 対応 |
|------|------|------|
| **I. 薄い Controller** | ✅ | `WatchItemFavoriteController` / `MemoController` / `FavoriteController`（刷新）は FormRequest → Policy → Action → JSON/View のみ |
| **II. 共有マスタ分離** | ✅ | `youtube_videos` / `youtube_channels` は読み取り参照のみ。`user_watch_items.is_favorite` のみ更新 |
| **III. 所有権認可** | ✅ | `UserWatchItemPolicy::update()` を神回トグルに流用。`TimestampMemoPolicy` は既存のまま継続 |
| **IV. シークレット管理** | ✅ | 本機能に新規秘密情報なし。CSRF トークンは Alpine.js の fetch ヘッダー経由 |
| **V. YouTube クォータ** | ✅ | YouTube Data API 不使用 |
| **VI. テストファースト** | ✅ | 神回トグル・/favorites タブ・/memos 一覧・所有権保護を Feature テストで担保 |
| **VII. 進捗データの正直表示** | ✅ | `is_favorite` は視聴進捗（`last_position_seconds`・`status`）と完全に独立 |

**Constitution Check 結果**: ✅ 全原則クリア。`Complexity Tracking` 記入不要。

## Project Structure

### Documentation (this feature)

```text
specs/007-legendary-and-favorites/
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
│   ├── Memo/                                        # [既存]
│   │   ├── CreateTimestampMemoAction.php
│   │   ├── UpdateTimestampMemoAction.php
│   │   ├── DeleteTimestampMemoAction.php
│   │   └── ToggleMemoFavoriteAction.php
│   └── WatchItem/                                   # [既存ディレクトリ]
│       └── ToggleWatchItemFavoriteAction.php        # [新規] 神回フラグトグル
├── Http/
│   ├── Controllers/
│   │   ├── WatchItemFavoriteController.php          # [新規] PATCH /archives/{watchItem}/favorite
│   │   ├── MemoController.php                      # [新規] GET /memos
│   │   └── FavoriteController.php                  # [更新] 2タブ対応に拡張
│   └── Requests/                                   # [変更なし]
├── Models/
│   └── TimestampMemo.php                            # [確認/更新] userWatchItem() リレーション追加
└── Policies/
    └── UserWatchItemPolicy.php                      # [確認] update() を神回トグルに流用

routes/
└── web.php                                          # [更新] 新規ルート2本追加

resources/
├── views/
│   ├── archives/
│   │   └── show.blade.php                          # [更新] 神回登録トグルボタン追加
│   ├── favorites/
│   │   └── index.blade.php                         # [更新] 2タブ構成に刷新
│   ├── memos/
│   │   └── index.blade.php                         # [新規] /memos 保管庫ビュー
│   └── home.blade.php                              # [更新] 最近のタイムスタンプ実データ化・もっと見る修正
├── css/
│   └── app.css                                     # [更新] 神回トグル・2タブ・/memos カードスタイル追加
└── layouts/
    └── app.blade.php                               # [更新] サイドバーリンク修正・/memos active 判定追加

app/Http/Controllers/
└── HomeController.php                              # [更新] 最近のタイムスタンプ 実データクエリ追加

tests/
└── Feature/
    ├── WatchItem/
    │   └── WatchItemFavoriteTest.php               # [新規] 神回トグル・所有権保護
    ├── Favorites/
    │   ├── FavoritesTabTest.php                    # [新規] 2タブ切り替え・フィルタ
    │   └── FavoritesIndexTest.php                  # [既存] 既存テスト維持
    ├── Memo/
    │   └── MemoIndexTest.php                       # [新規] /memos 一覧・フィルタ・ページネーション
    └── Home/
        └── HomeSummaryTest.php                     # [更新] 最近のタイムスタンプ実データ確認
```

**Structure Decision**: Laravel モノリス（Option 1 相当）。既存の Action/Controller/Policy 規約を踏襲し、WatchItem 系 Action は `app/Actions/WatchItem/` に配置する。

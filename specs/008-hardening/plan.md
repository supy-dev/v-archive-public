# Implementation Plan: 本番品質強化（セキュリティ・エラー処理・パフォーマンス・E2E・規約）

**Branch**: `008-hardening` | **Date**: 2026-06-22 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/008-hardening/spec.md`

## Summary

Feature 001〜007 で構築した全機能を対象に、公開品質への引き上げを行う。研究フェーズ（[research.md](research.md)）で判明した未実装箇所は以下の 5 点に絞られた。①手動チャンネル同期（`fetchOlder`）専用レート制限（5回/分）の設置、②不足していた DB インデックス 2 件の追加マイグレーション、③カスタムエラーページ（404・500）の新設、④プライバシーポリシー・利用規約ページの新設、⑤レート制限・API フォールバック・主要フロー横断の Feature テスト追加。Policy カバレッジはすでに完全であり、変更不要と判断した。

## Technical Context

**Language/Version**: PHP 8.4 / Laravel 12

**Primary Dependencies**: Blade、Alpine.js、Eloquent ORM、Laravel Policy、Laravel RateLimiter

**Storage**: PostgreSQL 16（本番: Supabase PostgreSQL） — 既存テーブルのみ。インデックス追加マイグレーション 2 件。

**Testing**: Pest / PHPUnit（Feature テスト中心）

**Target Platform**: Linux サーバ（Docker Compose ローカル / Supabase 本番）

**Project Type**: Web アプリケーション（Laravel モノリス + Blade テンプレート）

**Performance Goals**:
- SC-003: YouTube API 停止時も閲覧画面が 3 秒以内に表示
- SC-004: 動画 500 件・メモ 1000 件で一覧初期表示が 3 秒以内

**Constraints**:
- Alpine.js + Vanilla JS のみ（React/Vue 追加禁止、憲法 技術制約）
- テストで実 YouTube API を呼ばない（憲法 VI）
- 新規テーブル・カラムなし（インデックスのみ追加）

**Scale/Scope**:
- 1 ユーザーあたり動画数百件・メモ数百件を想定
- ページネーション 20 件/ページ

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| 原則 | 評価 | 対応 |
|------|------|------|
| **I. 薄い Controller** | ✅ | `LegalController` は View 返却のみ。`ChannelSyncController` は変更なし |
| **II. 共有マスタ分離** | ✅ | 共有マスタへの変更なし。インデックス追加はユーザーデータテーブルのみ |
| **III. 所有権認可** | ✅ | Policy カバレッジが完全であることを research.md で確認済み |
| **IV. シークレット管理** | ✅ | 新規秘密情報なし。ログ規律は既存実装に準拠 |
| **V. YouTube クォータ** | ✅ | `fetchOlder` に 5回/分の厳格制限を追加。既存クォータ対策に加える |
| **VI. テストファースト** | ✅ | レート制限・フォールバック・フロー横断 Feature テストを追加 |
| **VII. 進捗データの正直表示** | ✅ | 再生位置・ステータスに変更なし |

**Constitution Check 結果**: ✅ 全原則クリア。`Complexity Tracking` 記入不要。

## Project Structure

### Documentation (this feature)

```text
specs/008-hardening/
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
├── Http/
│   └── Controllers/
│       └── LegalController.php                      # [新規] GET /privacy・GET /terms
├── Providers/
│   └── AppServiceProvider.php                       # [更新] channel-sync リミッター追加

database/
└── migrations/
    └── 2026_06_22_000001_add_missing_indexes.php    # [新規] インデックス 2 件追加

resources/
├── views/
│   ├── layouts/
│   │   └── minimal.blade.php                        # [新規] 認証不要の最小レイアウト
│   ├── errors/
│   │   ├── 404.blade.php                            # [新規] カスタム 404 ページ
│   │   └── 500.blade.php                            # [新規] カスタム 500 ページ
│   └── legal/
│       ├── privacy.blade.php                        # [新規] プライバシーポリシー
│       └── terms.blade.php                          # [新規] 利用規約
│   └── layouts/
│       └── app.blade.php                            # [更新] フッターに /privacy・/terms リンク追加
├── css/
│   └── app.css                                      # [更新] エラーページ・規約ページスタイル（最小限）

routes/
└── web.php                                          # [更新] /privacy・/terms 追加、fetchOlder リミッター付け替え

tests/
└── Feature/
    └── Hardening/
        ├── RateLimitTest.php                        # [新規] channel-sync 5回/分レート制限テスト
        ├── ApiFallbackTest.php                      # [新規] YouTube API 障害時フォールバックテスト
        └── MainFlowTest.php                         # [新規] 主要フロー横断テスト（E2E 相当）
```

**Structure Decision**: Laravel モノリス（既存構造踏襲）。法的ページは `LegalController` を新設し `resources/views/legal/` に分離する。`layouts/minimal.blade.php` はエラーページ・規約ページ共用の軽量レイアウト（認証不要）。

---

## Phase 0: Research 結果サマリー

詳細は [research.md](research.md) を参照。主要決定事項:

1. **Policy**: 全 7 クラスが登録済み・テスト済み → 追加対応不要
2. **レート制限**: `fetchOlder` のみ `channel-sync`（5回/分）へ分離。他は現状維持（60回/分）
3. **インデックス**: 2 件不足（`user_watch_items(profile_id, updated_at)`・`user_channels(profile_id, sync_enabled)`）
4. **エラーページ**: `resources/views/errors/` 未作成 → 新設
5. **テスト**: 所有権テストは充足。レート制限・フォールバック・フロー横断テストが不足
6. **規約ページ**: 未実装 → `LegalController` + ビュー新設

---

## Phase 1: Design & Contracts

詳細は [data-model.md](data-model.md)・[contracts/routes.md](contracts/routes.md) を参照。

### 実装タスクの依存順序

以下の順で実装する（後続が前者に依存）:

```
1. マイグレーション（インデックス追加）
   └─ 独立。最初に実施してよい。

2. AppServiceProvider（channel-sync リミッター追加）
   └─ 独立。最初に実施してよい。

3. routes/web.php（fetchOlder のリミッター付け替え + /privacy・/terms 追加）
   └─ LegalController が必要。

4. LegalController + legal/ ビュー + minimal レイアウト
   └─ minimal.blade.php が先。

5. エラービュー（errors/404, errors/500）
   └─ minimal.blade.php が先。

6. layouts/app.blade.php フッター更新（/privacy・/terms リンク追加）
   └─ 規約ビューが先。

7. Feature テスト（Hardening/）
   └─ 実装変更が完了してから作成。
```

### 最小レイアウト（`layouts/minimal.blade.php`）設計方針

- `layouts/app.blade.php` の認証依存部分（サイドバー・トップバー・ユーザー情報）を外した構成
- ロゴ（テキストのみ可）+ コンテンツ + フッター（/privacy・/terms リンク）
- CLAUDE.md のカラートークン・フォントスタックを継承
- 未認証でレンダリングできることが必須条件

### エラーページ設計方針

- `layouts/minimal.blade.php` を継承（認証依存なし）
- 404: 「ページが見つかりません」+ ホームへ戻るリンク
- 500: 「エラーが発生しました。時間をおいてお試しください。」+ ホームへ戻るリンク
- スタックトレース・内部エラーコードは表示しない
- デザインは CLAUDE.md のカラートークン・角丸・影規約に沿う

### テスト設計方針

**`RateLimitTest.php`**:
- `RateLimiter::clear('channel-sync|{userId}')` でリミットをリセット
- fetchOlder を 5 回呼んで全て成功（200 or 302）→ 6 回目が 429
- memo-mutations を 60 回呼んで全て成功 → 61 回目が 429

**`ApiFallbackTest.php`**:
- `Http::fake(['*.googleapis.com/*' => Http::response(null, 503)])` で障害シミュレーション
- `GET /archive`・`GET /memos`・`GET /favorites` が 200 を返すことを確認
- 同期 Job 失敗後も一覧表示が維持されることを確認

**`MainFlowTest.php`**:
- 認証 → アーカイブ一覧 → 配信詳細 → タイムスタンプメモ作成 → 神回トグル → `/favorites` で神回確認
- 各ステップで HTTP ステータスと主要コンテンツの存在を確認

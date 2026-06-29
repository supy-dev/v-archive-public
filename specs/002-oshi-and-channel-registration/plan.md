# Implementation Plan: Oshi & Channel Registration（推し・チャンネル登録）

**Branch**: `002-oshi-and-channel-registration` | **Date**: 2026-06-20 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-oshi-and-channel-registration/spec.md`

## Summary

ログイン済みユーザーが「推し」を作成・管理し、その推しに YouTube チャンネルを登録できるようにする。
YouTube チャンネルは URL または @handle を入力して特定し、全ユーザー共有の共有マスタ（`youtube_channels`）
として1レコードで保持する。ユーザー固有の登録情報・設定（同期可否・通知可否・メイン指定）は
`user_channels`（中間テーブル）で管理する。動画の取得・一覧は後続フィーチャー（003）が担い、
本フィーチャーはチャンネルを「同期待ち」状態で登録するまでを完結させる。

技術アプローチ: チャンネル特定は YouTube Data API v3 の `channels.list`（`search.list` は禁止・憲法 V）
のみを用い、入力から channel_id を確定してからAPIを呼ぶ。推し操作・チャンネル登録はそれぞれ
`Actions/Oshi/**` と `Actions/Channel/**` に分離し Controller を薄く保つ（憲法 I）。テーマカラーは
PHP backed Enum（`OshiColor`）で管理し、パレット外の値は FormRequest 段階で拒否する（憲法 III）。

## Technical Context

**Language/Version**: PHP 8.3（Feature 001 と同一）

**Primary Dependencies**: Laravel 13.8, Blade, Tailwind CSS + Vite, Alpine.js,
Laravel HTTP Client（`Http::fake` で YouTube API をモック）

**Storage**: PostgreSQL（Docker Compose / Supabase 本番）。新規テーブル: `oshis`, `youtube_channels`,
`user_channels`。既存 `profiles` への FK を追加。

**Testing**: Pest + PHPUnit 12。YouTube Data API は `Http::fake()` で完全モック（憲法 VI）。

**Target Platform**: Webアプリ（PC・スマートフォン レスポンシブ Blade）

**Project Type**: Laravel モノリス（Feature 001 と同一構成）

**Performance Goals**: 推し作成からチャンネル登録完了まで通常2分以内・数ステップ（SC-001）。
一覧は N+1 なし eager load・ページネーション（憲法 技術制約）。

**Constraints**: YouTube `search.list` 禁止 / `channels.list` のみ / 画面表示時に YouTube API を呼ばない /
共有マスタ（`youtube_channels`）は一般ユーザーが直接更新不可 / APIキーはサーバ専用・非ログ（憲法 IV/V）/
認証済み変更操作にレート制限 / 所有権は Policy で強制（憲法 III）。

**Scale/Scope**: 個人開発 MVP。画面は推し一覧・推し詳細（チャンネル含む）・推し作成・推し編集・
チャンネル登録フォーム（推し詳細内）の4–5系統。

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| 原則 | 適合方針 | 状態 |
|---|---|---|
| I. 薄いController・レイヤード設計 | 推し CRUD・チャンネル登録/解除/設定変更/メイン指定を各 Action に分離。Controller は FormRequest 受け取り → Action 呼び出し → Response のみ | PASS |
| II. 共有マスタとユーザーデータの分離 | `youtube_channels` は全ユーザー共通1レコード（upsert で重複作成しない）。`user_channels` がユーザー固有の登録・設定を保持。動画ファイル・サムネイル本体・API全文は保存しない | PASS |
| III. 所有権ベースの認可と型安全 | `oshis`/`user_channels` は `OshiPolicy`/`UserChannelPolicy` で所有権確認（`profile_id = auth()->id()`）。テーマカラーは `OshiColor` backed Enum で管理。本人識別はサーバセッション（`auth()->id()`）のみ採用 | PASS |
| IV. シークレット・ハイジーン | YouTube APIキーは `.env` 経由でサーバ専用。ユーザー入力や API エラー詳細はログ出力しない（内部エラーのみ記録）。キーをフロントへ渡さない | PASS |
| V. YouTube連携・クォータ規律 | `search.list` 不使用・`channels.list` のみ。画面表示時に API を呼ばず DB のキャッシュを参照。チャンネル登録は upsert で共有マスタを重複作成しない。429/5xx と入力不正を区別し `sync_error_message` に記録。初回同期処理は後続フィーチャーへ引き継ぐ | PASS |
| VI. テストファースト & 外部APIモック | `Http::fake()` で YouTube API を完全モック。所有権・重複登録防止・メインチャンネル管理・エラーハンドリングを Feature/Unit テストで担保 | PASS |
| VII. 進捗データの自前管理 | 本フィーチャーは視聴・再生位置を扱わないため該当なし | N/A |

**初期判定: PASS**（違反なし）

**Phase 1 設計後 再確認**: PASS — 設計でパレット外カラー値を FormRequest 段階で拒否すること、
共有マスタ更新ルートを認証ユーザーに公開しないことを contracts に明記。

## Project Structure

### Documentation (this feature)

```text
specs/002-oshi-and-channel-registration/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   └── oshi-and-channel-endpoints.md  # Phase 1 output
└── tasks.md             # /speckit-tasks で生成（本コマンドでは作成しない）
```

### Source Code (repository root)

```text
app/
├── Actions/
│   ├── Oshi/
│   │   ├── CreateOshiAction.php           # 推し作成
│   │   ├── UpdateOshiAction.php           # 推し編集
│   │   └── DeleteOshiAction.php           # 推し削除（user_channels も cascade）
│   └── Channel/
│       ├── RegisterChannelAction.php      # URL解析 → API解決 → upsert共有マスタ → user_channel作成
│       ├── DeregisterChannelAction.php    # user_channel削除 → メインなら次チャンネルへ自動付け替え
│       ├── SetMainChannelAction.php       # メインチャンネル変更（他を解除 → 指定を設定）
│       └── UpdateChannelSettingsAction.php # sync_enabled / notify_enabled 更新
├── Enums/
│   ├── OshiColor.php                      # backed enum（識別子 → 表示名・Tailwindクラス）
│   └── ChannelSyncStatus.php             # backed enum: pending / synced / error
├── Http/
│   ├── Controllers/
│   │   ├── OshiController.php             # index, create, store, show, edit, update, destroy
│   │   └── UserChannelController.php      # store（登録）, destroy（解除）, update（設定）, setMain
│   └── Requests/
│       ├── StoreOshiRequest.php
│       ├── UpdateOshiRequest.php
│       ├── StoreUserChannelRequest.php    # channel_url バリデーション・形式チェック
│       └── UpdateChannelSettingsRequest.php
├── Models/
│   ├── Oshi.php                           # HasMany UserChannel, BelongsTo Profile
│   ├── YoutubeChannel.php                 # HasMany UserChannel（共有マスタ）
│   └── UserChannel.php                    # BelongsTo Oshi, Profile, YoutubeChannel
├── Policies/
│   ├── OshiPolicy.php                     # view/update/delete: profile_id === auth()->id()
│   └── UserChannelPolicy.php              # view/update/delete: profile_id === auth()->id()
└── Services/
    └── YouTube/
        ├── YouTubeChannelResolverInterface.php
        ├── ApiYouTubeChannelResolver.php   # Http::get で channels.list を呼ぶ実装
        ├── ChannelInput.php                # 値オブジェクト: 入力種別（channel_id / handle / username）+ 値
        └── ResolvedChannel.php             # 値オブジェクト: API応答の正規化済みデータ

config/
└── youtube.php                            # api_key（env）, base_url

database/migrations/
├── XXXX_create_oshis_table.php
├── XXXX_create_youtube_channels_table.php
└── XXXX_create_user_channels_table.php

resources/views/
├── oshis/
│   ├── index.blade.php                    # 推し一覧（各推しのメインチャンネル・登録数を表示）
│   ├── show.blade.php                     # 推し詳細（紐づくチャンネル一覧・設定・チャンネル登録フォーム）
│   ├── create.blade.php                   # 推し作成フォーム
│   └── edit.blade.php                     # 推し編集フォーム
└── components/
    └── oshi-color-picker.blade.php        # パレット選択 UI コンポーネント

routes/web.php                             # /oshis/** ルート追加

tests/
├── Feature/
│   ├── Oshi/
│   │   ├── CreateOshiTest.php             # 作成・バリデーション（色パレット外拒否等）
│   │   ├── UpdateOshiTest.php
│   │   ├── DeleteOshiTest.php             # 推し削除でuser_channelsも消える
│   │   └── OshiOwnershipTest.php          # 他ユーザーの推しへのアクセス拒否
│   └── Channel/
│       ├── RegisterChannelTest.php        # URL/handle 各形式・重複防止・共有マスタupsert
│       ├── DeregisterChannelTest.php      # 自分の登録のみ削除・メイン再割当
│       ├── MainChannelTest.php            # 最初は自動メイン・変更・常に1つ
│       ├── ChannelSettingsTest.php        # sync/notify トグル
│       └── ChannelOwnershipTest.php       # 他ユーザーのチャンネル登録へのアクセス拒否
└── Unit/
    └── YouTube/
        ├── ApiYouTubeChannelResolverTest.php  # channels.list モック・エラーハンドリング
        └── ChannelInputParserTest.php         # 各URL形式の解析ロジック
```

**Structure Decision**: Feature 001 と同一の Laravel モノリス構成を踏襲。ドメイン別に
`Actions/Oshi/` と `Actions/Channel/` を分割し、YouTube API 依存を `Services/YouTube/` に閉じる。
フロントは Blade + Tailwind（Alpine.js でカラーピッカー等の軽量インタラクション）。完全 SPA 化しない。

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

憲法違反はないが、設計上の非自明な選択を明示する。

| 項目 | 必要理由 | 採用しなかった代替 |
|---|---|---|
| `user_channels.is_main` を部分ユニークインデックスで DB レベルで保護 | メインチャンネルが「常にちょうど1つ」という不変条件を DB で担保し、アプリバグによる複数メイン発生を防ぐ | アプリレベルのみで管理する案は、並行リクエストやバグで不変条件が壊れるリスクがあるため却下 |
| チャンネル解決を `Service` 層に分離し `Http::fake` でモック可能にする | テスト時に実 YouTube API を呼ばない（憲法 VI）。解決ロジックが Action に混在すると差し替えが困難 | Action 内に直接 HTTP 呼び出しを書く案は、テストと懸念分離の両面で不適切なため却下 |

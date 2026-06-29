# Implementation Plan: 動画同期（Video Sync）

**Branch**: `003-video-sync` | **Date**: 2026-06-20 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/003-video-sync/spec.md`

---

## Summary

登録済みYouTubeチャンネルの動画メタ情報を共有マスタ（`youtube_videos`）として管理し、初回同期・定期同期・過去動画追加取得・削除動画検出の4つの非同期Jobで動画データを最新に保つ。`playlistItems.list`経由でuploads playlistを取得し、`videos.list`でバッチ詳細取得・upsert冪等保存する。Feature 4（archive-and-watchlist）で新着一覧・閲覧ステータスを実装するための動画データ基盤を確立する。

---

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 13.16.1（既存）

**Primary Dependencies**:
- Laravel Queue（databaseドライバー、`QUEUE_CONNECTION=database`）
- Laravel Scheduler（`withoutOverlapping`）
- Laravel HTTP Client（`Http::fake()`でテストモック）
- Backed Enums（`VideoType`, `LiveStatus`）

**YouTube APIs**:
- `playlistItems.list` — uploads playlist（1 unit/call）
- `videos.list` — 動画詳細バッチ取得（1 unit/call, 最大50件）
- `search.list` — **使用禁止**（憲法V）

**Storage**: PostgreSQL（本番Supabase / テストSQLite）

**Testing**: Pest 4.7 / PHPUnit 12（既存）。外部API呼び出しは`Http::fake()`で完全モック

**Target Platform**: Laravelサーバー（Webアプリ + Queue Worker + Scheduler）

**Performance Goals**:
- 初回同期完了: 60秒以内（SC-001）
- 定期同期1サイクル: 30分以内（SC-004）
- APIクォータ: 2 units/チャンネル/同期（playlistItems.list 1 + videos.list 1）

**Constraints**:
- `search.list` MUST NOT使用（憲法V）
- APIキーをログに出力しない（憲法IV）
- `youtube_videos`はユーザー単位で複製しない（憲法II）
- 全テスト環境でHTTP実呼び出し禁止（憲法VI）

---

## Constitution Check

| 原則 | 評価 | 根拠 |
|---|---|---|
| I. 薄いController | ✅ PASS | 同期ロジックはJob + Serviceに分離。Controllerは過去動画取得のdispatchのみ |
| II. 共有マスタとユーザーデータの分離 | ✅ PASS | `youtube_videos`は全ユーザー共通。`oldest_page_token`も共有マスタ側に保存 |
| III. 所有権ベースの認可 | ✅ PASS | 過去動画取得操作は`user_channels`経由で`sync_enabled`確認。Policyで認可 |
| IV. シークレット・ハイジーン | ✅ PASS | APIキーはサーバー側のみ。エラーログにAPIキーを含めない（`YouTubeApiException`実装済み） |
| V. YouTube連携・クォータ規律 | ✅ PASS | `playlistItems.list`のみ使用。`search.list`不使用。画面はDB表示のみ |
| VI. テストファースト & 外部APIモック | ✅ PASS | 全Job・Serviceテストで`Http::fake()`使用。実APIアクセスなし |
| VII. 進捗データの自前管理 | N/A | 本Featureは動画メタ同期のみ。視聴進捗はFeature 5で対応 |

---

## Project Structure

### Documentation（本Feature）

```text
specs/003-video-sync/
├── plan.md         ← このファイル
├── research.md     ← Phase 0出力
├── data-model.md   ← Phase 1出力
├── quickstart.md   ← Phase 1出力
├── contracts/
│   ├── jobs.md
│   └── services.md
└── tasks.md        ← /speckit-tasks で生成
```

### Source Code

```text
app/
├── Console/Commands/
│   ├── DispatchVideoSyncsCommand.php     # youtube:dispatch-syncs
│   └── MarkUnavailableVideosCommand.php  # youtube:mark-unavailable
├── Enums/
│   ├── VideoType.php                     # archive/live/upcoming/short/video/unknown
│   └── LiveStatus.php                    # none/upcoming/live/completed/unknown
├── Jobs/
│   ├── InitialSyncYoutubeChannelJob.php  # チャンネル登録後の初回同期
│   ├── SyncYoutubeChannelJob.php         # 定期同期（新着のみ）
│   ├── FetchOlderYoutubeVideosJob.php    # 過去動画追加取得
│   ├── RefreshYoutubeVideoDetailsJob.php # ライブ終了後の詳細更新
│   └── MarkUnavailableYoutubeVideosJob.php
├── Models/
│   └── YoutubeVideo.php                  # 共有マスタ
├── Services/YouTube/
│   ├── FetchUploadedVideosService.php    # playlistItems.list ラッパー
│   ├── FetchVideoDetailsService.php      # videos.list バッチラッパー
│   ├── SyncChannelVideosService.php      # upsertロジック集約
│   ├── IsoDurationParser.php             # "PT1H23M45S" → 秒数
│   ├── YoutubeVideoTypeResolver.php      # video_type / live_status 判定
│   └── ResolvedVideo.php                 # Value Object
├── Http/Controllers/
│   └── ChannelSyncController.php         # POST fetch-older

database/migrations/
├── 2026_06_20_000004_create_youtube_videos_table.php
└── 2026_06_20_000005_add_sync_columns_to_youtube_channels.php

database/factories/
└── YoutubeVideoFactory.php

tests/
├── Feature/Sync/
│   ├── InitialSyncTest.php
│   ├── PeriodicSyncTest.php
│   ├── FetchOlderVideosTest.php
│   ├── RefreshVideoDetailsTest.php
│   └── MarkUnavailableTest.php
└── Unit/YouTube/
    ├── IsoDurationParserTest.php
    ├── YoutubeVideoTypeResolverTest.php
    ├── FetchUploadedVideosServiceTest.php
    └── FetchVideoDetailsServiceTest.php
```

---

## Implementation Strategy

### フェーズ順序

```
Phase 1 (基盤):
  マイグレーション + Enum + Model + Factory

Phase 2 (Service層):
  IsoDurationParser → YoutubeVideoTypeResolver → ResolvedVideo
  → FetchVideoDetailsService → FetchUploadedVideosService → SyncChannelVideosService

Phase 3 (Jobs - 初回同期, P1):
  InitialSyncYoutubeChannelJob
  → RegisterChannelAction（Feature 2）へ dispatch 追加
  → 初回同期テスト

Phase 4 (Jobs - 定期同期, P1):
  SyncYoutubeChannelJob
  → DispatchVideoSyncsCommand
  → Scheduler登録（routes/console.php）
  → 定期同期テスト

Phase 5 (Jobs - 過去動画・補完, P2):
  FetchOlderYoutubeVideosJob → ChannelSyncController → route追加
  RefreshYoutubeVideoDetailsJob（SyncJobから呼ぶ）

Phase 6 (Jobs - 削除検出, P2):
  MarkUnavailableYoutubeVideosJob
  → MarkUnavailableVideosCommand
  → Scheduler登録

Phase 7 (Blade UI):
  oshis/show.blade.php に同期状態表示 + 最終同期日時 + 「もっと見る」ボタン
```

### 重要実装メモ

1. **upsert with UUID**: `Model::upsert()`使用時、`HasUuids`トレイトがINSERT前にUUID生成しないため、`$rows`を構築する前に`Str::uuid()`で明示的に`id`を付与する。

2. **ShouldBeUnique + databaseドライバー**: `jobs`テーブルに加えて`job_batches`テーブルも必要。`php artisan queue:table`で確認。

3. **FetchUploadedVideosService::fetchUntilKnown**: `playlistItems.list`を1ページずつ取得し、取得したvideoIdが`youtube_videos`に既存かを確認。全件既存または`nextPageToken`がnullになったら終了。

4. **APIキーのログ汚染防止**: `YouTubeApiException`（Feature 2実装済み）を継承して使用。`Log::warning()`には`['channel_id' => ..., 'status' => ...]`形式で構造化ログを使い、APIキーを含めない。

5. **テストのQueue設定**: `Queue::fake()`でJobのdispatch検証。Job自体のロジックテストは`$job->handle()`を直接呼び`Http::fake()`と組み合わせる。

6. **routes/console.php**: Laravel 11+でSchedulerはBootstrapの`schedule`メソッドまたは`routes/console.php`に記述。既存プロジェクトの慣習を確認して合わせる。

---

## Complexity Tracking

Constitution違反なし。Complexityトラッキング対象なし。

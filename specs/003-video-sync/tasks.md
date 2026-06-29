# Tasks: 動画同期（Video Sync）

**Input**: Design documents from `specs/003-video-sync/`

**Prerequisites**: plan.md ✅, spec.md ✅, research.md ✅, data-model.md ✅, contracts/ ✅

**Constitution**: 憲法 I〜VI 全原則 PASS（plan.md Constitution Check 参照）

---

## Format: `[ID] [P?] [Story] Description`

- **[P]**: 並列実行可能（異なるファイル、未完了タスクへの依存なし）
- **[US1]〜[US4]**: ユーザーストーリー対応
- ファイルパスは全タスクに明示

---

## Phase 1: Setup（共有インフラ）

**Purpose**: マイグレーション・Factory の作成。全フェーズの前提となる DB スキーマを確立する。

- [X] T001 `youtube_videos` テーブルのマイグレーションを作成する in `database/migrations/2026_06_20_000004_create_youtube_videos_table.php`（uuid PK, youtube_video_id UNIQUE, youtube_channel_id FK, title, description VARCHAR(500), thumbnail_url, published_at, duration_seconds, video_type, live_status, scheduled_start_at, actual_start_at, actual_end_at, privacy_status, is_available, last_fetched_at, timestampsTz。インデックス: (youtube_channel_id, published_at DESC), video_type, live_status, published_at DESC, is_available WHERE FALSE）
- [X] T002 `youtube_channels` に `oldest_page_token` / `oldest_fetched_at` カラムを追加するマイグレーションを作成する in `database/migrations/2026_06_20_000005_add_sync_columns_to_youtube_channels.php`
- [X] T003 [P] `YoutubeVideoFactory` を作成する in `database/factories/YoutubeVideoFactory.php`（VideoType・LiveStatus Enum対応、is_available デフォルト true）

**Checkpoint**: `php artisan migrate` が通ること

---

## Phase 2: Foundational（全 US の前提となるブロッキング基盤）

**Purpose**: Enum・Value Object・Model・Parser の実装。US1〜US4 はすべてこのフェーズに依存する。

**⚠️ CRITICAL**: このフェーズが完了するまで US1〜US4 の実装は開始できない

- [X] T004 [P] `VideoType` backed Enum を作成する in `app/Enums/VideoType.php`（cases: Archive='archive', Live='live', Upcoming='upcoming', Short='short', Video='video', Unknown='unknown'。`label()`, `isLive()` メソッド付き。コメントは日本語）
- [X] T005 [P] `LiveStatus` backed Enum を作成する in `app/Enums/LiveStatus.php`（cases: None='none', Upcoming='upcoming', Live='live', Completed='completed', Unknown='unknown'。`isActive()`, `label()` メソッド付き。コメントは日本語）
- [X] T006 [P] `IsoDurationParser` ユーティリティを作成する in `app/Services/YouTube/IsoDurationParser.php`（`toSeconds(string $duration): ?int` — "PT1H23M45S" → 5025 変換。"P0D" → 0。null/空 → null）
- [X] T007 [P] `YoutubeVideoTypeResolver` を作成する in `app/Services/YouTube/YoutubeVideoTypeResolver.php`（`resolveVideoType(array $item): VideoType`, `resolveLiveStatus(array $item): LiveStatus` — data-model.md の判定ロジック実装。コメントは日本語）
- [X] T008 [P] `ResolvedVideo` Value Object を作成する in `app/Services/YouTube/ResolvedVideo.php`（readonly class。全フィールド: youtubeVideoId, title, description(先頭500文字), thumbnailUrl, publishedAt, durationSeconds, videoType, liveStatus, scheduledStartAt, actualStartAt, actualEndAt, privacyStatus。`fromApiItem(array $item): self` ファクトリメソッド付き）
- [X] T009 `YoutubeVideo` モデルを作成する in `app/Models/YoutubeVideo.php`（HasFactory, HasUuids。$fillable 全カラム設定。$casts: video_type→VideoType::class, live_status→LiveStatus::class, published_at/scheduled_start_at/actual_start_at/actual_end_at/last_fetched_at→datetime, is_available→boolean。`youtubeChannel(): BelongsTo` リレーション。コメントは日本語。T004・T005 完了後）
- [X] T010 `YoutubeChannel` モデルに `youtubeVideos(): HasMany` リレーションと `oldest_page_token` / `oldest_fetched_at` を $fillable/$casts に追加する in `app/Models/YoutubeChannel.php`（T009 完了後）

**Checkpoint**: `php artisan test` が既存 90 テストすべて PASS のこと

---

## Phase 3: US1 — チャンネル登録後に最新動画が自動取得される（Priority: P1）🎯 MVP

**Goal**: チャンネル登録後に `InitialSyncYoutubeChannelJob` が dispatch され、`youtube_videos` に最新 50 件が upsert される。

**Independent Test**: `php artisan test tests/Feature/Sync/InitialSyncTest.php` が PASS すること

### US1 実装

- [X] T011 [P] [US1] `FetchUploadedVideosService` を作成する in `app/Services/YouTube/FetchUploadedVideosService.php`（`fetchLatest(YoutubeChannel $channel, int $maxPages = 1): FetchedPlaylistPage`, `fetchUntilKnown(YoutubeChannel $channel): FetchedPlaylistPage`, `fetchPage(YoutubeChannel $channel, ?string $pageToken): FetchedPlaylistPage`。`playlistItems.list` 呼び出し。uploads_playlist_id が null の場合は空結果返却。429/5xx → `YouTubeApiException` throw。APIキーをログに出力しない。`FetchedPlaylistPage` readonly クラスも同ファイル or 同ディレクトリに定義）
- [X] T012 [P] [US1] `FetchVideoDetailsService` を作成する in `app/Services/YouTube/FetchVideoDetailsService.php`（`fetchBatch(array $youtubeVideoIds): array` — videos.list を 50 件バッチで呼ぶ。`IsoDurationParser::toSeconds()` で duration 変換。description 先頭 500 文字切り詰め。`YoutubeVideoTypeResolver` で video_type/live_status 判定。`ResolvedVideo::fromApiItem()` で変換）
- [X] T013 [US1] `SyncChannelVideosService` を作成する in `app/Services/YouTube/SyncChannelVideosService.php`（`upsert(YoutubeChannel $channel, array $resolvedVideos): int`。`YoutubeVideo::upsert($data, ['youtube_video_id'], $updateCols)` 呼び出し。INSERT 前に `Str::uuid()` で id を明示付与。created_at は UPDATE 対象外。last_fetched_at は常に UPDATE。影響件数を返す）
- [X] T014 [US1] `InitialSyncYoutubeChannelJob` を作成する in `app/Jobs/InitialSyncYoutubeChannelJob.php`（`ShouldQueue`, `ShouldBeUnique` 実装。`uniqueId(): string` で `$this->youtubeChannel->id` 返却。コンストラクタに `YoutubeChannel $youtubeChannel`。`handle()` で FetchUploadedVideosService → FetchVideoDetailsService → SyncChannelVideosService の順に呼ぶ。完了後 sync_status='synced', last_synced_at=now() 更新。429/5xx はリトライ（最大3回）、4xx は即座に error 記録。コメントは日本語）
- [X] T015 [US1] `RegisterChannelAction` に `InitialSyncYoutubeChannelJob::dispatch($youtubeChannel)` を追加する in `app/Actions/Channel/RegisterChannelAction.php`（DB::transaction 完了後に dispatch）
- [X] T016 [US1] 初回同期 Feature テストを作成する in `tests/Feature/Sync/InitialSyncTest.php`（`Http::fake()` で `playlistItems.list` / `videos.list` をモック。Queue::fake() でdispatch確認。検証: 最大50件のyoutube_videos作成 / 同一youtube_video_id重複なし / sync_status='synced' / APIキーログ出力なし / 429リトライ / 初回同期中はsync_status='pending'）

**Checkpoint**: `php artisan test tests/Feature/Sync/InitialSyncTest.php` PASS

---

## Phase 4: US2 — 登録チャンネルの新着動画が定期的に自動更新される（Priority: P1）

**Goal**: 30 分毎の Scheduler が `SyncYoutubeChannelJob` を dispatch し、既存 ID に到達するまで新着動画を取得・upsert する。

**Independent Test**: `php artisan test tests/Feature/Sync/PeriodicSyncTest.php` が PASS すること

### US2 実装

- [X] T017 [US2] `SyncYoutubeChannelJob` を作成する in `app/Jobs/SyncYoutubeChannelJob.php`（`ShouldQueue`, `ShouldBeUnique` 実装。`uniqueId()` で channel id 返却。`handle()` で `fetchUntilKnown()` → `fetchBatch()` → `upsert()`。`live_status=live` の動画を DB から検出し `RefreshYoutubeVideoDetailsJob::dispatch($video)` を発行。last_synced_at 更新。エラーハンドリングは InitialSyncJob と同様。コメントは日本語）
- [X] T018 [US2] `RefreshYoutubeVideoDetailsJob` を作成する in `app/Jobs/RefreshYoutubeVideoDetailsJob.php`（コンストラクタに `YoutubeVideo $video`。`handle()` で `videos.list` を 1 件呼び、live_status / duration_seconds / actual_end_at / video_type を更新。コメントは日本語）
- [X] T019 [US2] `DispatchVideoSyncsCommand` を作成する in `app/Console/Commands/DispatchVideoSyncsCommand.php`（`signature = 'youtube:dispatch-syncs'`。sync_enabled=true の user_channels が存在する youtube_channels を抽出し `SyncYoutubeChannelJob::dispatch()` を発行。dispatch 件数を構造化ログ出力。APIキーをログに含めない）
- [X] T020 [US2] Scheduler に `youtube:dispatch-syncs` を登録する in `routes/console.php`（`Schedule::command('youtube:dispatch-syncs')->everyThirtyMinutes()->withoutOverlapping()->runInBackground()`）
- [X] T021 [US2] 定期同期 Feature テストを作成する in `tests/Feature/Sync/PeriodicSyncTest.php`（`Http::fake()` / `Queue::fake()` 使用。検証: 既存 videoId 到達で停止 / 新着のみ追加 / last_synced_at 更新 / sync_enabled=true チャンネルのみ対象 / ShouldBeUnique で重複 Job がキューされない）
- [X] T022 [US2] RefreshVideoDetails テストを作成する in `tests/Feature/Sync/RefreshVideoDetailsTest.php`（`Http::fake()` で videos.list をモック。検証: live_status=live で RefreshJob が dispatch される / actual_end_at が更新される / live_status='completed' に更新される）

**Checkpoint**: `php artisan test tests/Feature/Sync/` PASS

---

## Phase 5: US3 — 過去の動画を遡って追加取得できる（Priority: P2）

**Goal**: 「過去の動画をもっと見る」操作で `FetchOlderYoutubeVideosJob` が dispatch され、`oldest_page_token` を使い過去ページを取得・upsert する。

**Independent Test**: `php artisan test tests/Feature/Sync/FetchOlderVideosTest.php` が PASS すること

### US3 実装

- [X] T023 [US3] `FetchOlderYoutubeVideosJob` を作成する in `app/Jobs/FetchOlderYoutubeVideosJob.php`（`ShouldQueue`, `ShouldBeUnique`。`uniqueId()` で channel id 返却。`handle()` で youtubeChannel.oldest_page_token を取得 → `fetchPage()` → `fetchBatch()` → `upsert()` → oldest_page_token / oldest_fetched_at を更新。nextPageToken=null 時は oldest_page_token=null に。コメントは日本語）
- [X] T024 [US3] `ChannelSyncController` を作成する in `app/Http/Controllers/ChannelSyncController.php`（`fetchOlder(Oshi $oshi, UserChannel $userChannel): RedirectResponse`。`$this->authorize('update', $userChannel)` で Policy チェック。`FetchOlderYoutubeVideosJob::dispatch($userChannel->youtubeChannel)` を発行。flash でフィードバック返却。コメントは日本語）
- [X] T025 [US3] ルートを追加する in `routes/web.php`（`POST /oshis/{oshi}/channels/{userChannel}/fetch-older` → `ChannelSyncController@fetchOlder` を `throttle:oshi-mutations` 配下に追加。named: `oshis.channels.fetchOlder`）
- [X] T026 [US3] 過去動画取得 Feature テストを作成する in `tests/Feature/Sync/FetchOlderVideosTest.php`（`Http::fake()` / `Queue::fake()` 使用。検証: 既存より古い動画が追加 / oldest_page_token が更新 / 全件取得済みで oldest_page_token=null / 他ユーザーのチャンネルで 403）
- [X] T027 [US3] 「過去の動画をもっと見る」ボタンを Blade に追加する in `resources/views/oshis/show.blade.php`（oldest_page_token が null でない場合にボタン表示。全件取得済み時は「これ以上動画はありません」テキスト表示。POST form + @csrf）

**Checkpoint**: `php artisan test tests/Feature/Sync/FetchOlderVideosTest.php` PASS

---

## Phase 6: US4 — 削除・非公開になった動画でもメモが保持される（Priority: P2）

**Goal**: 1 日 1 回 `MarkUnavailableYoutubeVideosJob` が実行され、削除・非公開動画の `is_available=false` が更新される。メモ等は削除されない。

**Independent Test**: `php artisan test tests/Feature/Sync/MarkUnavailableTest.php` が PASS すること

### US4 実装

- [X] T028 [US4] `MarkUnavailableYoutubeVideosJob` を作成する in `app/Jobs/MarkUnavailableYoutubeVideosJob.php`（コンストラクタに `array $youtubeVideoIds`（最大 50 件の youtube_video_id 文字列）。`handle()` で `videos.list` を呼び、API が返さなかった ID または privacy_status != public のレコードを `is_available=false` に更新。コメントは日本語）
- [X] T029 [US4] `MarkUnavailableVideosCommand` を作成する in `app/Console/Commands/MarkUnavailableVideosCommand.php`（`signature = 'youtube:mark-unavailable'`。`youtube_videos` から `is_available=true` を 50 件ずつ chunk 取得し `MarkUnavailableYoutubeVideosJob::dispatch()` を発行。処理件数・更新件数をログ出力）
- [X] T030 [US4] Scheduler に `youtube:mark-unavailable` を登録する in `routes/console.php`（`Schedule::command('youtube:mark-unavailable')->dailyAt('03:00')->withoutOverlapping()->runInBackground()`）
- [X] T031 [US4] MarkUnavailable Feature テストを作成する in `tests/Feature/Sync/MarkUnavailableTest.php`（`Http::fake()` で videos.list をモック。検証: API が返さない動画 ID の is_available が false に更新 / 利用可能な動画は変更なし）
- [X] T032 [US4] `is_available=false` 動画の再生不可表示を Blade に追加する in `resources/views/oshis/show.blade.php`（`@if(!$video->is_available)` で「この動画は現在YouTubeで再生できません。保存済みメモは引き続き確認できます。」を表示）

**Checkpoint**: `php artisan test tests/Feature/Sync/MarkUnavailableTest.php` PASS

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Unit テスト・同期状態 UI・全スイート確認

- [X] T033 [P] `IsoDurationParser` Unit テストを作成する in `tests/Unit/YouTube/IsoDurationParserTest.php`（変換ケース: PT1H23M45S→5025 / PT5M30S→330 / P0D→0 / 空文字→null / PT0S→0 / PT2H→7200）
- [X] T034 [P] `YoutubeVideoTypeResolver` Unit テストを作成する in `tests/Unit/YouTube/YoutubeVideoTypeResolverTest.php`（video_type 判定: upcoming/live/archive/video/short/unknown。live_status 判定: none/upcoming/live/completed/unknown）
- [X] T035 [P] `FetchUploadedVideosService` Unit テストを作成する in `tests/Unit/YouTube/FetchUploadedVideosServiceTest.php`（`Http::fake()` で playlistItems.list をモック。検証: 正しいパラメータで呼ばれる / uploads_playlist_id=null で空結果 / 429 で YouTubeApiException / nextPageToken が正しく返る）
- [X] T036 [P] `FetchVideoDetailsService` Unit テストを作成する in `tests/Unit/YouTube/FetchVideoDetailsServiceTest.php`（`Http::fake()` で videos.list をモック。検証: 50 件バッチで呼ぶ / description が 500 文字に切り詰められる / video_type / live_status が正しく設定される）
- [X] T037 `oshis/show.blade.php` に同期中状態と最終同期日時を追加する in `resources/views/oshis/show.blade.php`（sync_status='pending' 時は「同期中...」スピナー表示。sync_status='synced' 時は「最終同期: {last_synced_at}」表示。sync_status='error' 時はエラーメッセージ表示）
- [X] T038 全テストスイートを実行して全 PASS を確認する（`php artisan test` で既存 90 + 新規テストが全 PASS）

---

## Dependencies & Execution Order

### フェーズ依存関係

- **Phase 1 (Setup)**: 依存なし。即座に開始可能
- **Phase 2 (Foundational)**: Phase 1 完了後。**全 US をブロック**
- **Phase 3 (US1)**: Phase 2 完了後。依存なし（独立テスト可能）
- **Phase 4 (US2)**: Phase 2 完了後。US1 の Service 層を再利用（T011〜T013 依存）
- **Phase 5 (US3)**: Phase 2 + Phase 3 の Service 完了後（FetchUploadedVideosService 依存）
- **Phase 6 (US4)**: Phase 2 完了後。他 US と独立
- **Phase 7 (Polish)**: 全 US フェーズ完了後

### US 間の依存関係

- **US2** は US1 の `FetchUploadedVideosService` / `FetchVideoDetailsService` / `SyncChannelVideosService` を共用する（T011〜T013 が必要）
- **US3** は US1 の Service 層に加え `FetchUploadedVideosService::fetchPage()` を使う
- **US4** は US2 の `FetchVideoDetailsService` の videos.list 呼び出しを流用する
- **US1** は最初に完了することで US2〜US4 の Service 依存を解消する

---

## Parallel Execution Examples

### Phase 2（Foundational）の並列実行

```bash
# T004〜T008 はすべて独立ファイルのため同時実行可能
Task T004: VideoType Enum
Task T005: LiveStatus Enum
Task T006: IsoDurationParser
Task T007: YoutubeVideoTypeResolver
Task T008: ResolvedVideo Value Object
# → 完了後、T009 (YoutubeVideo モデル) → T010 (YoutubeChannel 追加) を順次実行
```

### Phase 3（US1）の並列実行

```bash
# T011 と T012 は独立したファイルのため同時実行可能
Task T011: FetchUploadedVideosService
Task T012: FetchVideoDetailsService
# → 完了後、T013 (SyncChannelVideosService) → T014 (InitialSyncJob) を順次実行
```

### Phase 7（Polish）の並列実行

```bash
# T033〜T036 はすべて独立テストファイルのため同時実行可能
Task T033: IsoDurationParserTest
Task T034: YoutubeVideoTypeResolverTest
Task T035: FetchUploadedVideosServiceTest
Task T036: FetchVideoDetailsServiceTest
```

---

## Implementation Strategy

### MVP First（US1 + US2 のみ）

1. Phase 1: Setup 完了
2. Phase 2: Foundational 完了（**必須ブロッカー**）
3. Phase 3: US1 完了 → `php artisan test tests/Feature/Sync/InitialSyncTest.php` PASS
4. Phase 4: US2 完了 → `php artisan test tests/Feature/Sync/PeriodicSyncTest.php` PASS
5. **STOP & VALIDATE**: チャンネル登録 → 動画が取得される → 30 分毎に更新される の基本サイクルが動作
6. Phase 4 まで完了でデプロイ可能な MVP

### インクリメンタルデリバリー

1. Setup + Foundational → DB スキーマ確立
2. US1 → チャンネル登録後の初回同期が動く（MVP 最小単位）
3. US2 → 定期自動更新が動く（実用的な MVP）
4. US3 → 過去動画遡り機能追加
5. US4 → 削除動画の適切な処理追加

---

## Notes

- `[P]` タスクは異なるファイルを扱い、未完了タスクへの依存がないため並列実行可能
- `[US?]` ラベルはユーザーストーリーとのトレーサビリティを保持
- 全テスト（Feature / Unit）で `Http::fake()` を使用。実 YouTube API への呼び出し禁止（憲法 VI）
- `upsert()` 使用時は `id`（UUID）を事前に `Str::uuid()` で明示付与（research.md Decision 7）
- `ShouldBeUnique` 使用には `jobs` テーブルが必要（`QUEUE_CONNECTION=database` 確認）
- コードコメント・PHPDoc は日本語で記載（憲法「技術・セキュリティ制約」）
- `youtube:dispatch-syncs` / `youtube:mark-unavailable` は `routes/console.php` に Scheduler 定義

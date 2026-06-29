# Research: 動画同期（Video Sync）

**Feature**: 003-video-sync
**Date**: 2026-06-20

---

## Decision 1: `youtube_videos` プライマリキー型

**Decision**: uuid

**Rationale**: Feature 2（oshi-and-channel-registration）で`youtube_channels`をuuidで実装済み。`user_watch_items`（Feature 4）等が`youtube_video_id`をFKとして参照するため、全テーブルでuuidに統一することでJOINの型整合性を保つ。開発指示書§7.5のbigint記載より憲法II準拠の一貫性を優先する。

**Alternatives considered**: bigint autoincrement（軽量・インデックス効率が高いが、UUIDとの混在でFK設計が複雑になる）

---

## Decision 2: `youtube_videos.description` 保存方針

**Decision**: 先頭500文字に切り詰めて保存（`VARCHAR(500) nullable`）

**Rationale**: 動画詳細ページの概要表示に500文字は十分。全文保存はテーブルサイズ肥大化（`text`）を招き、`description`全文取得は一覧クエリから常に除外する必要がある（§18.1）。`null`だと後から再取得コスト（APIクォータ）が発生する。500文字は`videos.list`の`snippet.description`からサーバー側で切り捨てる。

**Alternatives considered**:
- 全文保存: ストレージ肥大化、一覧N+1リスク
- null保存: 詳細ページ表示時に再取得APIコストが生じる

---

## Decision 3: `playlistItems.list` による動画収集

**Decision**: uploads playlist経由（`playlistItems.list?part=contentDetails&playlistId=UU...&maxResults=50`）

**Rationale**:
- `search.list`はクォータコスト100 units/call（憲法Vで明示的禁止）
- `playlistItems.list`はコスト1 unit/call
- `YoutubeChannel.uploads_playlist_id`はFeature 2の`channels.list`取得時に既に保存済み
- 1ページ最大50件、`nextPageToken`でページネーション可能

**API呼び出しシーケンス（初回同期）**:
```
1. playlistItems.list?playlistId={uploads_playlist_id}&maxResults=50
   → videoId一覧（最大50件）+ nextPageToken
2. videos.list?id={videoId1,videoId2,...}&part=snippet,contentDetails,status,liveStreamingDetails
   → 動画詳細（最大50件をバッチで一括取得、コスト1 unit/call）
3. youtube_videos にupsert
```

**クォータ試算（1チャンネル初回同期）**:
- playlistItems.list: 1 unit
- videos.list（50件バッチ）: 1 unit
- 合計: 2 units/チャンネル

**Alternatives considered**: `search.list`（高クォータ・禁止）、`channels.list`のみ（動画取得不可）

---

## Decision 4: `videos.list` バッチサイズ

**Decision**: 最大50件/call（YouTube Data APIのバッチ上限）

**Rationale**: `videos.list`は`id`パラメータに最大50件のvideoIdをカンマ区切りで指定可能。1callで50件を取得できるため、`playlistItems.list`の1ページ（50件）と完全に対応し、追加API呼び出しが不要。

**取得フィールド**:
- `snippet`: title, description, publishedAt, thumbnails, channelId
- `contentDetails`: duration（ISO 8601形式 → 秒数に変換）
- `status`: privacyStatus
- `liveStreamingDetails`: scheduledStartTime, actualStartTime, actualEndTime, concurrentViewers

---

## Decision 5: Job重複防止方式

**Decision**: `WithoutOverlapping` ミドルウェア + チャンネルIDをJobキーに使用

**Rationale**: Laravelの`ShouldBeUnique`インターフェース（`uniqueId()`メソッド）を使うと、同一channelId（またはchannel UUID）のJobがQueueに積まれていても1件のみ実行される。Queueドライバーが`database`の場合、`job_batches`テーブルで一意性を保証。

**実装方針**:
```php
class SyncYoutubeChannelJob implements ShouldQueue, ShouldBeUnique
{
    public function uniqueId(): string
    {
        return $this->youtubeChannel->id;
    }
    // ユニーク期間: デフォルト60秒（sync中に別Jobがdispatchされても待機）
}
```

**Alternatives considered**: Redisロック（Redisなしでも動作するdatabaseドライバーを優先）、DB-levelフラグ（複雑化する）

---

## Decision 6: Scheduler 重複実行防止

**Decision**: `->withoutOverlapping()` + `->runInBackground()`

**Rationale**: `dispatch-syncs` Artisanコマンドが30分ごとに実行され、全`sync_enabled=true`チャンネルにJobをdispatchするだけなので処理は軽い。`withoutOverlapping()`で同コマンドの二重起動を防止。各チャンネルの実際の同期はJob側の`ShouldBeUnique`で重複防止。

```php
// app/Console/Kernel.php (または routes/console.php)
Schedule::command('youtube:dispatch-syncs')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('youtube:mark-unavailable')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();
```

---

## Decision 7: upsert方式

**Decision**: Eloquentの`upsert()`メソッド

**Rationale**: Laravel 8+の`Model::upsert()`は`INSERT ... ON CONFLICT DO UPDATE`に変換され、PostgreSQLでネイティブサポートされる。`youtube_video_id`をユニークキーとして`upsert($rows, ['youtube_video_id'], $updateCols)`で冪等に実行できる。

**注意点**:
- `HasUuids`トレイト使用時、`upsert()`は`id`の自動生成をスキップするため、呼び出し前にUUIDを明示的に設定する
- `created_at`/`updated_at`は`upsert()`の`$updateCols`から除外して初回作成タイムスタンプを保持する

---

## Decision 8: ISO 8601 duration → 秒数変換

**Decision**: PHPの`DateInterval::createFromDateString()`または正規表現でパース

**Rationale**: `videos.list`の`contentDetails.duration`は`PT1H23M45S`形式。正規表現でH/M/Sを抽出して秒数に変換するUtilityクラス（`IsoDurationParser`）をUnit Testで固定する。

```php
// 例: "PT1H23M45S" → 5025秒
preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $m);
$seconds = (int)($m[1] ?? 0) * 3600 + (int)($m[2] ?? 0) * 60 + (int)($m[3] ?? 0);
```

---

## Decision 9: `oldest_page_token` のスコープ

**Decision**: `youtube_channels`（共有マスタ）に保存

**Rationale**: 憲法II準拠。動画データは共有マスタで管理し、ユーザーごとに重複しない。「過去の動画をもっと見る」を実行したユーザーの取得結果は同チャンネルを登録する全ユーザーが参照できる（クォータ節約にもなる）。

**追加カラム（`youtube_channels`テーブルへのマイグレーション）**:
- `oldest_page_token VARCHAR(255) nullable` — 過去取得の継続トークン（nullなら全件取得済み or 未取得）
- `oldest_fetched_at TIMESTAMPTZ nullable` — 最古取得済み動画の公開日時

---

## Decision 10: `video_type` / `live_status` の判定ロジック

**Decision**: `videos.list`のレスポンスから以下のロジックで判定

```
video_type:
  - contentDetails.duration == "P0D" → live (ライブ配信)
  - snippet.liveBroadcastContent == "upcoming" → upcoming
  - contentDetails.duration がISO durationかつ60秒未満 → short (暫定、ショート動画の公式判定はAPIで難しい)
  - それ以外 → archive（録画済み配信）または video（通常動画）
  ※ 詳細は YoutubeVideoTypeResolver サービスで集約

live_status:
  - liveStreamingDetails が存在しない → none
  - snippet.liveBroadcastContent == "upcoming" → upcoming
  - snippet.liveBroadcastContent == "live" → live
  - liveStreamingDetails.actualEndTime が存在する → completed
  - それ以外 → unknown
```

---

## Decision 11: RefreshYoutubeVideoDetailsJob のトリガー

**Decision**: 定期同期Job（SyncYoutubeChannelJob）内で`live_status=live`の動画を検出し、同Job内で`videos.list`を呼び出して更新する（独立したJobは不要）

**Rationale**: ライブ配信中の動画はuploads playlistには含まれない場合があるため、DBから`live_status=live`のレコードをクエリして`videos.list`で最新詳細を取得・更新する。30分の定期同期サイクルで自動的にカバーされる。

---

## Decision 12: `MarkUnavailableYoutubeVideosJob` の実行スケジュール

**Decision**: 1日1回・深夜3時（mark-unavailableコマンド経由）

**Rationale**: 削除動画の検出は即時性が不要（ユーザーのメモは保持されるため、翌日検出でも問題なし）。毎30分サイクルに含めるとクォータ消費が増大する。`videos.list`で最大50件/callバッチ処理するため、大量チャンネルでも効率的。

**実装方針**: DBの`youtube_videos`を`youtube_channel_id`でグループし、`videos.list`に50件ずつ送信。404または`status.privacyStatus == 'private'`かつ前回公開だった動画を`is_available=false`に更新。

---

## Summary: APIクォータ試算（MVP規模）

| 操作 | API | コスト | 頻度 |
|---|---|---|---|
| 初回同期（1チャンネル・50件） | playlistItems.list + videos.list | 2 units | チャンネル登録時 |
| 定期同期（1チャンネル・新着のみ） | playlistItems.list + videos.list | 2〜4 units | 30分/チャンネル |
| ライブ詳細更新（1動画） | videos.list（バッチに含む） | 0追加 | 定期同期内 |
| 削除動画チェック（50件バッチ） | videos.list | 1 unit | 1日1回 |
| 過去動画追加取得（1ページ） | playlistItems.list + videos.list | 2 units | ユーザー操作時 |

日次クォータ上限: 10,000 units。チャンネル数100・新着ほぼなし・削除チェック100件の場合:
- 定期同期: 100 × 2 × 48 = 9,600 units/日（上限近辺のため、同期間隔1時間への調整を推奨）
- 実際には多くのチャンネルで新着なし → `playlistItems.list`の1件目で既存IDを検出し終了 → 大幅削減

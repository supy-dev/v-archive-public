# Service Contracts: 動画同期（Video Sync）

**Feature**: 003-video-sync
**Date**: 2026-06-20

---

## FetchUploadedVideosService

**目的**: `playlistItems.list` APIラッパー。アップロード動画IDリストを取得する。

**メソッド**:

```php
// 最新動画を最大maxPages×50件取得（初回同期用）
public function fetchLatest(YoutubeChannel $channel, int $maxPages = 1): FetchedPlaylistPage

// 既存IDに到達するまでフェッチ（定期同期用）
public function fetchUntilKnown(YoutubeChannel $channel): FetchedPlaylistPage

// 指定ページトークンから1ページ取得（過去動画取得用）
public function fetchPage(YoutubeChannel $channel, ?string $pageToken): FetchedPlaylistPage
```

**返り値 `FetchedPlaylistPage`**:
```php
readonly class FetchedPlaylistPage
{
    public array  $videoIds;       // youtube_video_id の文字列配列
    public ?string $nextPageToken; // 次ページトークン（nullなら最後）
    public bool   $reachedKnown;  // 既存IDに到達したか（fetchUntilKnown用）
}
```

**テスト検証点**:
- [ ] `playlistItems.list`が正しいパラメータで呼ばれる（`Http::fake()`で確認）
- [ ] `uploads_playlist_id`がnullの場合は空結果を返す
- [ ] 429エラー時に`YouTubeApiException`をthrowする

---

## FetchVideoDetailsService

**目的**: `videos.list` APIラッパー。動画詳細を50件ずつバッチ取得する。

**メソッド**:

```php
// 最大50件ずつバッチ取得
public function fetchBatch(array $youtubeVideoIds): array  // ResolvedVideo[]
```

**返り値 `ResolvedVideo`**:
```php
readonly class ResolvedVideo
{
    public string  $youtubeVideoId;
    public string  $title;
    public ?string $description;      // 先頭500文字
    public ?string $thumbnailUrl;
    public Carbon  $publishedAt;
    public ?int    $durationSeconds;  // ISO 8601 → 秒数変換済み
    public string  $videoType;        // VideoType::class の value
    public string  $liveStatus;       // LiveStatus::class の value
    public ?Carbon $scheduledStartAt;
    public ?Carbon $actualStartAt;
    public ?Carbon $actualEndAt;
    public ?string $privacyStatus;

    public static function fromApiItem(array $item): self;
}
```

**テスト検証点**:
- [ ] 50件を1callで取得する（`Http::fake()`で確認）
- [ ] `duration_seconds`がISO 8601から正しく変換される
- [ ] `description`が500文字に切り詰められる
- [ ] `video_type` / `live_status`が正しく判定される

---

## SyncChannelVideosService

**目的**: `youtube_videos`へのupsert処理を集約する。

**メソッド**:

```php
// 動画詳細リストをupsert。`youtube_video_id`をキーに冪等実行
public function upsert(YoutubeChannel $channel, array $resolvedVideos): int  // 影響件数
```

**処理詳細**:
```
1. $resolvedVideos から upsertデータ配列を構築（idはUUID生成）
2. YoutubeVideo::upsert($data, ['youtube_video_id'], $updateCols) を実行
3. 新規作成レコードには id（UUID）を明示的に付与
4. created_at は UPDATE 対象外（初回作成時刻を保持）
5. last_fetched_at は常に UPDATE
```

---

## IsoDurationParser

**目的**: YouTube APIが返す`PT1H23M45S`形式の時間を秒数に変換する。

**メソッド**:
```php
public static function toSeconds(string $duration): ?int
```

**変換例**:
- `"PT1H23M45S"` → `5025`
- `"PT5M30S"` → `330`
- `"P0D"` → `0`（ライブ配信中）
- `""` or `null` → `null`

---

## YoutubeVideoTypeResolver

**目的**: APIレスポンスから`video_type`・`live_status`を判定する。

**メソッド**:
```php
public static function resolveVideoType(array $item): VideoType
public static function resolveLiveStatus(array $item): LiveStatus
```

**判定ロジック**:
```
video_type:
  liveBroadcastContent == "upcoming" → upcoming
  liveBroadcastContent == "live" → live
  duration == "P0D" → live（後方互換）
  duration < 60秒 AND タイトルに#Shorts → short（暫定）
  liveStreamingDetails 存在 → archive
  それ以外 → video

live_status:
  liveStreamingDetails なし → none
  liveBroadcastContent == "upcoming" → upcoming
  liveBroadcastContent == "live" → live
  actualEndTime 存在 → completed
  それ以外 → unknown
```

---

## Artisan Commands

### `youtube:dispatch-syncs`

**目的**: 全sync_enabled=trueチャンネルに`SyncYoutubeChannelJob`をdispatch

```php
// 30分毎にSchedulerが呼ぶ
php artisan youtube:dispatch-syncs
```

**処理フロー**:
```
1. user_channels に sync_enabled=true が存在する youtube_channels を抽出
2. 各チャンネルに SyncYoutubeChannelJob::dispatch($channel) を発行
3. dispatchしたチャンネル数をログに記録（APIキーなし）
```

### `youtube:mark-unavailable`

**目的**: 削除・非公開動画を検出して`is_available=false`に更新

```php
// 1日1回・深夜3時にSchedulerが呼ぶ
php artisan youtube:mark-unavailable
```

**処理フロー**:
```
1. youtube_videos からis_available=trueのyoutube_video_idを50件ずつ取得
2. videos.list でバッチ確認
3. 削除・非公開になったものを is_available=false に更新
4. 処理件数・更新件数をログに記録
```

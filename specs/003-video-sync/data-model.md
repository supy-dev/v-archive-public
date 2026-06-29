# Data Model: 動画同期（Video Sync）

**Feature**: 003-video-sync
**Date**: 2026-06-20

---

## 新規テーブル

### `youtube_videos`（共有マスタ）

全ユーザー共通のYouTube動画メタ情報。ユーザーが直接更新・削除できない（憲法II）。

```sql
CREATE TABLE youtube_videos (
    id                   UUID PRIMARY KEY,           -- HasUuids
    youtube_video_id     VARCHAR(20)   NOT NULL UNIQUE,
    youtube_channel_id   UUID          NOT NULL REFERENCES youtube_channels(id),
    title                VARCHAR(255)  NOT NULL,
    description          VARCHAR(500)  NULL,          -- 先頭500文字のみ保存
    thumbnail_url        TEXT          NULL,
    published_at         TIMESTAMPTZ   NOT NULL,
    duration_seconds     INTEGER       NULL,          -- ISO 8601変換後の秒数; ライブ中はNULL
    video_type           VARCHAR(20)   NOT NULL DEFAULT 'unknown',
    live_status          VARCHAR(20)   NOT NULL DEFAULT 'none',
    scheduled_start_at   TIMESTAMPTZ   NULL,
    actual_start_at      TIMESTAMPTZ   NULL,
    actual_end_at        TIMESTAMPTZ   NULL,
    privacy_status       VARCHAR(20)   NULL,
    is_available         BOOLEAN       NOT NULL DEFAULT TRUE,
    last_fetched_at      TIMESTAMPTZ   NOT NULL,
    created_at           TIMESTAMPTZ   NOT NULL DEFAULT now(),
    updated_at           TIMESTAMPTZ   NOT NULL DEFAULT now()
);

-- インデックス（§18.2 推奨）
CREATE INDEX idx_youtube_videos_channel_published
    ON youtube_videos (youtube_channel_id, published_at DESC);

CREATE INDEX idx_youtube_videos_video_type ON youtube_videos (video_type);
CREATE INDEX idx_youtube_videos_live_status ON youtube_videos (live_status);
CREATE INDEX idx_youtube_videos_published_at ON youtube_videos (published_at DESC);
CREATE INDEX idx_youtube_videos_is_available ON youtube_videos (is_available)
    WHERE is_available = FALSE;  -- 削除チェック用
```

#### `video_type` 列挙値

| 値 | 意味 |
|---|---|
| `archive` | ライブ配信のアーカイブ録画 |
| `live` | ライブ配信中（`live_status=live`と同期） |
| `upcoming` | プレミア公開・予定配信 |
| `short` | YouTube Shorts（60秒未満の縦動画） |
| `video` | 通常のアップロード動画 |
| `unknown` | 判定不能 |

#### `live_status` 列挙値

| 値 | 意味 |
|---|---|
| `none` | 通常動画（ライブ配信でない） |
| `upcoming` | 配信予定（開始前） |
| `live` | 配信中 |
| `completed` | 配信終了（アーカイブ化済み） |
| `unknown` | 判定不能 |

---

## 既存テーブルへの追加カラム

### `youtube_channels` への追加

```sql
ALTER TABLE youtube_channels
    ADD COLUMN oldest_page_token VARCHAR(255) NULL,   -- 過去動画取得の継続トークン
    ADD COLUMN oldest_fetched_at  TIMESTAMPTZ  NULL;  -- 取得済み最古動画の公開日時
```

---

## Eloquent モデル

### `YoutubeVideo`

```
app/Models/YoutubeVideo.php
├── HasFactory, HasUuids
├── $fillable: youtube_video_id, youtube_channel_id, title, description,
│             thumbnail_url, published_at, duration_seconds, video_type,
│             live_status, scheduled_start_at, actual_start_at, actual_end_at,
│             privacy_status, is_available, last_fetched_at
├── $casts:
│   - video_type → VideoType::class
│   - live_status → LiveStatus::class
│   - published_at, scheduled_start_at, actual_start_at, actual_end_at,
│     last_fetched_at → datetime
│   - is_available → boolean
└── Relations:
    - youtubeChannel(): BelongsTo YoutubeChannel
```

---

## Enums

### `App\Enums\VideoType`

```php
enum VideoType: string
{
    case Archive  = 'archive';
    case Live     = 'live';
    case Upcoming = 'upcoming';
    case Short    = 'short';
    case Video    = 'video';
    case Unknown  = 'unknown';

    public function label(): string { ... }
    public function isLive(): bool { return $this === self::Live; }
}
```

### `App\Enums\LiveStatus`

```php
enum LiveStatus: string
{
    case None      = 'none';
    case Upcoming  = 'upcoming';
    case Live      = 'live';
    case Completed = 'completed';
    case Unknown   = 'unknown';

    public function isActive(): bool { return $this === self::Live; }
    public function label(): string { ... }
}
```

---

## Job / Service クラス一覧

| クラス | 役割 | トリガー |
|---|---|---|
| `InitialSyncYoutubeChannelJob` | 初回同期（最新50件） | チャンネル登録後 |
| `SyncYoutubeChannelJob` | 定期同期（新着分のみ） | Scheduler 30分毎 |
| `FetchOlderYoutubeVideosJob` | 過去動画追加取得（1ページ分） | ユーザー操作 |
| `RefreshYoutubeVideoDetailsJob` | ライブ終了後の動画詳細更新 | 定期同期内で検出 |
| `MarkUnavailableYoutubeVideosJob` | 削除・非公開動画の検出 | Scheduler 1日1回 |
| `FetchUploadedVideosService` | playlistItems.list呼び出し | Jobs経由 |
| `FetchVideoDetailsService` | videos.list呼び出し（バッチ） | Jobs経由 |
| `SyncChannelVideosService` | upsertロジック集約 | Jobs経由 |
| `IsoDurationParser` | "PT1H23M45S" → 秒数変換 | FetchVideoDetailsService |
| `YoutubeVideoTypeResolver` | video_type / live_status 判定 | FetchVideoDetailsService |

---

## エンティティ関係図

```
youtube_channels (共有マスタ)
    id (uuid PK)
    uploads_playlist_id
    oldest_page_token   ← NEW
    oldest_fetched_at   ← NEW
    sync_status
    last_synced_at
    └─── 1:N ─────────────────────────────────────────────┐
                                                           │
youtube_videos (共有マスタ)                              │
    id (uuid PK)                                          │
    youtube_channel_id (FK → youtube_channels.id) ←───┘
    youtube_video_id (UNIQUE)
    title
    description (先頭500文字)
    published_at
    duration_seconds
    video_type (Enum)
    live_status (Enum)
    is_available
    last_fetched_at
    └─── 1:N ─────────── user_watch_items (Feature 4)
```

---

## 状態遷移

### `youtube_channels.sync_status`（Feature 2で追加済み）

```
pending  ──初回同期Job成功──▶  synced
pending  ──初回同期Job失敗──▶  error
synced   ──定期同期失敗   ──▶  error
error    ──次回同期成功   ──▶  synced
```

### `youtube_videos.is_available`

```
TRUE (デフォルト)
  └── MarkUnavailableJob で API 404 / private 検出 ──▶ FALSE
        （復活は検討対象外：一度falseになった動画は以後更新しない）
```

### `youtube_videos.live_status`

```
upcoming ──配信開始──▶ live ──配信終了──▶ completed
none     ──変化なし──▶ none
live     ──RefreshJob──▶ completed（actual_end_at が埋まる）
```

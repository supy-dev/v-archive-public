# Data Model: 神回登録・神回お気に入りページ改修・タイムスタンプメモ保管庫の新設

> マイグレーション不要。既存カラムの活用のみ。

## 既存テーブルの利用箇所

### `user_watch_items`

| カラム | 型 | 本Feature での用途 |
|--------|-----|-------------------|
| `id` | UUID PK | 配信詳細URL（`/archives/{watchItem}`）のルートパラメータ |
| `profile_id` | UUID FK | 所有権チェック（UserWatchItemPolicy） |
| `youtube_video_id` | BIGINT FK | 動画マスタへの参照 |
| `is_favorite` | BOOLEAN (default: false) | **神回フラグ** — `WatchItemFavoriteController` でトグル |
| `updated_at` | TIMESTAMP | 神回動画タブの「年月」フィルタ基準（神回登録日） |

**状態遷移（is_favorite）**:
```
false ──[PATCH /archives/{watchItem}/favorite]──▶ true
true  ──[PATCH /archives/{watchItem}/favorite]──▶ false
```

### `timestamp_memos`

| カラム | 型 | 本Feature での用途 |
|--------|-----|-------------------|
| `id` | UUID PK | — |
| `profile_id` | UUID FK | 所有権チェック（TimestampMemoPolicy） |
| `user_watch_item_id` | UUID FK | `/archives/{watchItem}` への逆引き（メモカードの主リンク生成） |
| `seconds` | INTEGER | タイムスタンプ表示（`seconds_label` アクセサ経由） |
| `body` | TEXT | メモ本文 |
| `is_favorite` | BOOLEAN | ★お気に入りフラグ（既存トグル機能、本Featureでは /memos から変更不可） |
| `created_at` | TIMESTAMP | /memos ソート順（降順）、年月フィルタ基準 |

### `youtube_videos`（共有マスタ、読み取りのみ）

| カラム | 本Feature での用途 |
|--------|-------------------|
| `title` | 神回動画カード・メモカードのタイトル表示 |
| `thumbnail_url` | 神回動画カードのサムネイル（16:9 表示） |
| `youtube_video_id` | YouTube副リンク生成（YouTubeで開く） |
| `published_at` | （神回タブ年月フィルタには使わない、research.md 参照） |

### `youtube_channels`（共有マスタ、読み取りのみ）

| カラム | 本Feature での用途 |
|--------|-------------------|
| `title` | 神回動画カード・メモカードのチャンネル名表示 |
| `thumbnail_url` | メモカードのチャンネルアバター（任意） |

### `tags` / `timestamp_memo_tags`

本Feature では /memos・/favorites（お気に入りメモタブ）のタグフィルタに使用。既存の多対多リレーションをそのまま利用。

## Eager Load パターン

### 神回動画タブ（/favorites?tab=kamikai）

```
UserWatchItem → youtubeVideo → youtubeChannel
```

### お気に入りメモタブ（/favorites?tab=memos）および /memos

```
TimestampMemo → tags
TimestampMemo → userWatchItem（→ id のみ、主リンク生成用）
TimestampMemo → youtubeVideo → youtubeChannel
```

> `youtubeVideo` は `TimestampMemo` に直接 `belongsTo` として定義済み（`TimestampMemo::youtubeVideo()`）。

## 新規リレーション（追加検討）

`TimestampMemo::userWatchItem()` リレーションが未定義の場合は追加する。メモカードから `/archives/{watchItem}` へのリンク生成に必要。

```php
// app/Models/TimestampMemo.php
public function userWatchItem(): BelongsTo
{
    return $this->belongsTo(UserWatchItem::class, 'user_watch_item_id');
}
```

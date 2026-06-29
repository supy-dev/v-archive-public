# Data Model: アーカイブ閲覧と見るリスト管理 (Feature 004)

## 新規テーブル

### `user_watch_items`

ユーザーが操作した YouTube 動画1件ごとに1レコードを保持するユーザー固有の視聴アイテム（憲法 II）。

| カラム | 型 | NOT NULL | デフォルト | 説明 |
|-------|----|----------|-----------|------|
| `id` | `uuid` | ✓ | `gen_random_uuid()` | 主キー |
| `profile_id` | `uuid` | ✓ | — | 所有ユーザー（FK → `profiles.id`） |
| `youtube_video_id` | `uuid` | ✓ | — | 対象動画（FK → `youtube_videos.id`） |
| `status` | `varchar(20)` | ✓ | `'want_to_watch'` | 視聴ステータス（CHECK 制約あり） |
| `priority` | `integer` | ✓ | `0` | 優先度（MVP では変更 UI なし） |
| `is_favorite` | `boolean` | ✓ | `false` | お気に入り（Feature 006 以降で UI 提供） |
| `added_at` | `timestamptz` | ✓ | `NOW()` | 見るリスト追加日時 |
| `started_at` | `timestamptz` | — | `NULL` | 視聴開始日時（Feature 005 で自動設定） |
| `watched_at` | `timestamptz` | — | `NULL` | 視聴完了日時 |
| `skipped_at` | `timestamptz` | — | `NULL` | 見送り日時 |
| `last_position_seconds` | `integer` | — | `NULL` | 最終再生位置秒（Feature 005 で管理） |
| `created_at` | `timestamptz` | ✓ | — | |
| `updated_at` | `timestamptz` | ✓ | — | |

**制約**:
```sql
UNIQUE (profile_id, youtube_video_id)    -- FR-007: 重複作成禁止
CHECK (status IN ('want_to_watch', 'watching', 'watched', 'skipped'))  -- FR-014: 列挙値強制
FOREIGN KEY (profile_id)         REFERENCES profiles(id) ON DELETE CASCADE
FOREIGN KEY (youtube_video_id)   REFERENCES youtube_videos(id) ON DELETE CASCADE
```

**インデックス**:
```sql
INDEX (profile_id, status)               -- 見るリストタブ別取得
INDEX (profile_id, status, added_at)     -- ページネーション付きタブ別取得
INDEX (profile_id, added_at)             -- 未整理件数算出
```

---

## 既存テーブル（参照のみ・変更なし）

### `youtube_videos`（Feature 003 で作成済み）

Feature 004 で参照するフィールド:

| カラム | 用途 |
|-------|------|
| `id` | `user_watch_items.youtube_video_id` の FK 先 |
| `youtube_channel_id` | `user_channels` との JOIN キー |
| `title` | カード表示（FR-013） |
| `thumbnail_url` | カード表示（FR-013） |
| `published_at` | ソート基準（FR-001）、表示（FR-013） |
| `duration_seconds` | カード表示（FR-013） |
| `video_type` | フィルタ対象（FR-004） |
| `is_available` | フィルタ条件（FR-011）: `= true` のみ |

### `user_channels`（Feature 002 で作成済み）

| カラム | 用途 |
|-------|------|
| `profile_id` | ユーザーのチャンネルを絞り込む JOIN キー |
| `youtube_channel_id` | `youtube_videos` との JOIN キー |
| `oshi_id` | 推しフィルタ（FR-004）、カード表示（FR-013） |

### `oshis`（Feature 002 で作成済み）

| カラム | 用途 |
|-------|------|
| `id` | 推しフィルタの識別子 |
| `name` | カード表示（推し名、FR-013） |
| `color` | 推しカラートークン生成 |

---

## PHP Enum

### `App\Enums\WatchStatus`

```php
enum WatchStatus: string
{
    case WantToWatch = 'want_to_watch'; // 未視聴（見るリスト）
    case Watching    = 'watching';      // 視聴中（Feature 005 で自動設定）
    case Watched     = 'watched';       // 視聴済み
    case Skipped     = 'skipped';       // 見送り
}
```

**タイムスタンプ対応**:

| ステータス | 自動設定されるタイムスタンプ |
|-----------|--------------------------|
| `want_to_watch` | `added_at` (作成時のみ) |
| `watching` | `started_at` |
| `watched` | `watched_at` |
| `skipped` | `skipped_at` |

---

## モデル関連図

```
Profile (1) ─────────── (N) UserWatchItem (N) ─────────── (1) YoutubeVideo
                                                                     │
UserChannel (N) ─── (1) YoutubeChannel (1) ────────────────────────┘
    │
    └─── (N) Oshi
```

- `UserWatchItem` は `profile_id` と `youtube_video_id` を持ち、間接的に `Oshi` に辿れる。
- 新着アーカイブ一覧は `user_channels → youtube_videos` を JOIN し、`user_watch_items` が存在しない動画を表示する。

---

## マイグレーション順序

```
2026_06_20_000006_create_user_watch_items_table.php
```

（`profiles`, `youtube_videos` テーブルの後に実行する。Feature 003 のマイグレーションが完了している前提）

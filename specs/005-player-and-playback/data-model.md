# Data Model: プレイヤーと再生進捗管理 (Feature 005)

## 既存テーブルの利用（変更なし）

Feature 005 は新規テーブルを追加しない。Feature 3・4 で作成済みのテーブルを利用する。

---

### `user_watch_items`（Feature 4 作成済み）

Feature 005 で使用する主要カラム:

| カラム | 型 | NULL | 説明 |
|-------|----|------|------|
| `id` | uuid PK | NO | UUIDv4 |
| `profile_id` | uuid FK | NO | 所有ユーザー（`profiles.id`） |
| `youtube_video_id` | uuid FK | NO | 対象動画（`youtube_videos.id`） |
| `status` | varchar(20) | NO | `want_to_watch` / `watching` / `watched` / `skipped` |
| `last_position_seconds` | integer | YES | 最終再生位置（秒）。NULL = 未再生 |
| `started_at` | timestamptz | YES | 視聴開始日時（初回再生時に設定、上書きしない） |
| `watched_at` | timestamptz | YES | 視聴完了日時（動画終了 or 手動 watched 設定時） |
| `skipped_at` | timestamptz | YES | 見送り日時 |
| `updated_at` | timestamptz | NO | Eloquent 自動管理 |

**Feature 005 でのステータス遷移**:

```
want_to_watch ─[再生開始]→ watching ─[動画終了 or 手動]→ watched
     │                         │
     │                   [手動変更のみ]
     └──────────────────────────────────────────────→ skipped
                                                         │
                                               [動画終了]→ watched
```

- `want_to_watch → watching`: 再生開始時（`is_ended: false`）かつ status == want_to_watch の場合のみ
- `watching → watched`: `is_ended: true` or 手動変更
- `skipped → watching`: **禁止**（`once skipped, stays skipped`）
- `skipped → watched`: `is_ended: true` の場合のみ許可（完全視聴を優先）
- `watched → watching`: **禁止**（定期保存リクエストでは変更しない）

**上書き防止ルール（FR-011）**:

```
UPDATE user_watch_items
SET last_position_seconds = :new
WHERE id = :id
  AND (last_position_seconds IS NULL OR last_position_seconds < :new)
```

ただし `is_ended: true` の場合はこの条件なしで常に保存（動画終了時の最終位置確定）。

---

### `youtube_videos`（Feature 3 作成済み）

Feature 005 で参照する主要カラム:

| カラム | 型 | NULL | 説明 |
|-------|----|------|------|
| `id` | uuid PK | NO | UUIDv4 |
| `youtube_video_id` | varchar | NO | YouTube の動画ID（`dQw4w9WgXcQ` 形式） |
| `title` | varchar | NO | 動画タイトル |
| `thumbnail_url` | varchar | YES | サムネイル URL |
| `duration_seconds` | integer | YES | 動画時間（秒）。NULL の場合、位置バリデーションは `>= 0` のみ |
| `published_at` | timestamptz | YES | 投稿日時 |
| `is_available` | boolean | NO | false の場合、プレイヤーはエラー表示（FR-018） |
| `video_type` | varchar | NO | `archive` / `live` / `short` / `unknown` |

---

## 新規マイグレーション

**Feature 005 ではマイグレーションは不要。** 既存のカラム・インデックスで要件を満たす。

`last_position_seconds` カラムはすでに `nullable integer` として存在する。
ステータス遷移のための `started_at`・`watched_at`・`skipped_at` もすでに存在する。

---

## 新規コンポーネント（DB 非依存）

### `App\Actions\WatchItem\UpdatePlaybackPositionAction`

再生位置保存と自動ステータス遷移を担う新規 Action。DB テーブルは変更せず、Eloquent 操作のみ追加。

```
入力: UserWatchItem $item, int $newPositionSeconds, bool $isEnded
出力: void

ロジック:
  1. is_ended = true の場合:
     - status が 'watched' 以外 → status = watched, watched_at = now()
     - last_position_seconds = $newPositionSeconds（上書き防止なし）
  2. is_ended = false の場合:
     - status が want_to_watch → status = watching, started_at = now()（started_at が null の場合のみ）
     - last_position_seconds: $newPositionSeconds > current（または current が null） の場合のみ更新
  3. 変更がない場合は UPDATE しない（空 update 回避）
```

### `App\Http\Controllers\PlaybackPositionController`

```
PATCH /watch-items/{watchItem}/position
  → authorize('update', $watchItem)  # UserWatchItemPolicy::update()
  → UpdatePlaybackPositionRequest を通してバリデーション
  → UpdatePlaybackPositionAction::execute()
  → 204 No Content
```

### `App\Http\Requests\UpdatePlaybackPositionRequest`

```
rules:
  last_position_seconds: required | integer | min:0 | max:duration_seconds（nullable）
  is_ended: required | boolean
```

`duration_seconds` は `$request->watchItem->youtubeVideo->duration_seconds` から取得し、
NULL の場合は `max` ルールを省略する。

### `ArchiveController::show(UserWatchItem $watchItem)`

```
- authorize('view', $watchItem)  # UserWatchItemPolicy::view()
- $watchItem->load('youtubeVideo.youtubeChannel')
- return view('archives.show', compact('watchItem', 'profile'))
```

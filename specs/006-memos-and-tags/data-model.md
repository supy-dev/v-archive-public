# Data Model: メモ・タグ・神回お気に入り

## エンティティ一覧

| エンティティ | テーブル | 種別 |
|---|---|---|
| タグ | `tags` | ユーザーデータ（user_id = NULL のものはシステムタグ） |
| タイムスタンプメモ | `timestamp_memos` | ユーザーデータ |
| タイムスタンプメモタグ（中間） | `timestamp_memo_tags` | ユーザーデータ |
| 動画ノート | `video_notes` | ユーザーデータ |

---

## tags

システムが提供する共有タグ（`is_system = true`）とユーザー固有タグの両方を格納する。

| カラム | 型 | 制約 | 説明 |
|---|---|---|---|
| id | uuid | PK | |
| profile_id | uuid | FK → profiles, NULL 可 | NULL = システムタグ |
| name | varchar(100) | NOT NULL | 表示名（日本語可） |
| slug | varchar(100) | NOT NULL | URL/識別用スラッグ |
| color | varchar(20) | NULL 可 | UI カラークラス（mint/blue/purple 等） |
| is_system | boolean | NOT NULL, DEFAULT false | true = 全ユーザー共有 |
| created_at | timestamptz | NOT NULL | |
| updated_at | timestamptz | NOT NULL | |

**UNIQUE 制約**:
- システムタグ: `UNIQUE INDEX tags_system_slug_unique ON tags(slug) WHERE is_system = true`
- ユーザー固有タグ: `UNIQUE INDEX tags_user_slug_unique ON tags(profile_id, slug) WHERE is_system = false`

**FK**:
- `profile_id` → `profiles(id)` ON DELETE CASCADE（ユーザー削除時に固有タグも削除）

**バリデーション**:
- `name`: 最大 50 文字
- `slug`: Str::slug() で自動生成、kebab-case
- ユーザー固有タグは `firstOrCreate(['profile_id' => $userId, 'slug' => $slug])` で重複防止

**状態遷移**: なし（タグは単純な CRUD）

---

## timestamp_memos

ユーザーが特定の動画の特定秒数に紐付けて保存する短文メモ。

| カラム | 型 | 制約 | 説明 |
|---|---|---|---|
| id | uuid | PK | |
| profile_id | uuid | FK → profiles, NOT NULL | |
| youtube_video_id | uuid | FK → youtube_videos, NOT NULL | |
| seconds | integer | NOT NULL, CHECK >= 0 | 0 = 動画開始直後（有効） |
| body | text | NOT NULL | 本文（最大 1000 文字） |
| is_favorite | boolean | NOT NULL, DEFAULT false | お気に入りフラグ |
| created_at | timestamptz | NOT NULL | |
| updated_at | timestamptz | NOT NULL | |

**インデックス**:
- `(profile_id, youtube_video_id, seconds)` — 動画詳細ページのメモ一覧取得（秒数昇順）
- `(profile_id, is_favorite, created_at)` — お気に入り一覧取得

**FK**:
- `profile_id` → `profiles(id)` ON DELETE CASCADE
- `youtube_video_id` → `youtube_videos(id)` ON DELETE CASCADE

**バリデーション**:
- `body`: 1〜1000 文字（必須）
- `seconds`: 0 以上の整数

**所有権**: `TimestampMemoPolicy` で `$memo->profile_id === $user->id` を検証（憲法 III）

**メモ**: 同一秒数に複数のタイムスタンプメモを保存可能（UNIQUE 制約なし）

---

## timestamp_memo_tags（中間テーブル）

タイムスタンプメモとタグの多対多関係。

| カラム | 型 | 制約 | 説明 |
|---|---|---|---|
| timestamp_memo_id | uuid | FK → timestamp_memos, NOT NULL | |
| tag_id | uuid | FK → tags, NOT NULL | |

**PK**: `(timestamp_memo_id, tag_id)` 複合主キー

**FK**:
- `timestamp_memo_id` → `timestamp_memos(id)` ON DELETE CASCADE
- `tag_id` → `tags(id)` ON DELETE CASCADE

**インデックス**:
- `(tag_id)` — タグ別フィルタリング（お気に入り一覧）

---

## video_notes

ユーザーが動画全体に対して記述する自由記述メモ。1 ユーザー・1 動画につき 1 件。

| カラム | 型 | 制約 | 説明 |
|---|---|---|---|
| id | uuid | PK | |
| profile_id | uuid | FK → profiles, NOT NULL | |
| youtube_video_id | uuid | FK → youtube_videos, NOT NULL | |
| body | text | NOT NULL | 本文（最大 5000 文字） |
| created_at | timestamptz | NOT NULL | |
| updated_at | timestamptz | NOT NULL | |

**UNIQUE**: `(profile_id, youtube_video_id)` — 1 ユーザー・1 動画につき 1 件

**FK**:
- `profile_id` → `profiles(id)` ON DELETE CASCADE
- `youtube_video_id` → `youtube_videos(id)` ON DELETE CASCADE

**バリデーション**:
- `body`: 1〜5000 文字（必須。空の場合は DB に残さず削除で対応）

**所有権**: `VideoNotePolicy` で検証

---

## リレーション図

```
profiles
  ├── timestamp_memos (1:N)
  │     └── timestamp_memo_tags (N:M via)
  │           └── tags
  ├── video_notes (1:N, UNIQUE per video)
  └── tags (1:N, ユーザー固有タグのみ)

youtube_videos
  ├── timestamp_memos (1:N)
  └── video_notes (1:N)
```

---

## PHP Enum

```php
// app/Enums/TagScope.php
enum TagScope: string {
    case System = 'system';
    case User   = 'user_owned';
}
```

---

## システムタグ初期データ（SystemTagSeeder）

| slug | name | color |
|---|---|---|
| waratta | 笑った | mint |
| naita | 泣いた | blue |
| uta | 歌 | purple |
| teetee | てぇてぇ | pink |
| juudai-happyou | 重大発表 | orange |
| kami-scene | 神シーン | purple |
| oshi-koi | 推し活 | pink |
| ikouze | いこうぜ | green |

---

## マイグレーション順序

1. `2026_06_20_000007_create_tags_table.php`
2. `2026_06_20_000008_create_timestamp_memos_table.php`
3. `2026_06_20_000009_create_timestamp_memo_tags_table.php`
4. `2026_06_20_000010_create_video_notes_table.php`

# ルート・インターフェースコントラクト: Feature 004

## 新規ルート

### 新着アーカイブ一覧

```
GET /archive
Middleware: auth.supabase, throttle:60,1
Controller: App\Http\Controllers\ArchiveController::index
View: archive.index
```

**クエリパラメータ**:

| パラメータ | 型 | 必須 | 説明 |
|-----------|----|------|------|
| `oshi_id` | `uuid` | — | 推しでフィルタ |
| `video_type` | `string` | — | 動画種別フィルタ（`archive`/`live`/`short`/`video`/`upcoming`） |
| `page` | `int` | — | ページ番号（デフォルト 1） |

**レスポンス（Blade ビュー変数）**:

```php
[
    'videos'      => LengthAwarePaginator,  // YoutubeVideo (with youtubeChannel.oshiRelation)
    'oshis'       => Collection<Oshi>,      // フィルタ用推し一覧
    'videoTypes'  => array<string, string>, // フィルタ用動画種別ラベル
    'filters'     => ['oshi_id' => ?string, 'video_type' => ?string],
    'profile'     => Profile,
]
```

---

### 見るリスト追加 / 見送り

```
POST /archive/{video}/watch-item
Middleware: auth.supabase, throttle:oshi-mutations
Controller: App\Http\Controllers\UserWatchItemController::store
```

**リクエストボディ（FormRequest: `StoreUserWatchItemRequest`）**:

| フィールド | 型 | 必須 | バリデーション |
|-----------|-----|------|--------------|
| `status` | `string` | ✓ | `in:want_to_watch,skipped` |

**レスポンス**: `redirect()->back()` または `redirect()->route('archive.index')` with flash message

---

### 見るリスト一覧

```
GET /watchlist
Middleware: auth.supabase, throttle:60,1
Controller: App\Http\Controllers\UserWatchItemController::index
View: watchlist.index
```

**クエリパラメータ**:

| パラメータ | 型 | 必須 | 説明 |
|-----------|----|------|------|
| `status` | `string` | — | タブ選択（デフォルト `want_to_watch`） |
| `page` | `int` | — | ページ番号 |

**レスポンス（Blade ビュー変数）**:

```php
[
    'watchItems'    => LengthAwarePaginator,  // UserWatchItem (with youtubeVideo.youtubeChannel.oshiRelation)
    'currentStatus' => WatchStatus,
    'tabCounts'     => ['want_to_watch' => int, 'watching' => int, 'watched' => int, 'skipped' => int],
    'profile'       => Profile,
]
```

---

### ステータス変更

```
PATCH /watchlist/{userWatchItem}
Middleware: auth.supabase, throttle:oshi-mutations
Controller: App\Http\Controllers\UserWatchItemController::update
```

**リクエストボディ（FormRequest: `UpdateWatchStatusRequest`）**:

| フィールド | 型 | 必須 | バリデーション |
|-----------|-----|------|--------------|
| `status` | `string` | ✓ | `in:want_to_watch,watched,skipped` |

**レスポンス**: `redirect()->back()` with flash message

---

### 削除（未整理に戻す）

```
DELETE /watchlist/{userWatchItem}
Middleware: auth.supabase, throttle:oshi-mutations
Controller: App\Http\Controllers\UserWatchItemController::destroy
```

**レスポンス**: `redirect()->route('watchlist.index')` with flash message

---

## Actions インターフェース

### `App\Actions\WatchItem\AddToWatchListAction`

```
入力: Profile $profile, YoutubeVideo $video, WatchStatus $status (want_to_watch|skipped)
出力: UserWatchItem
例外: AlreadyExistsException（upsert で吸収するため実質発生しない）
副作用: user_watch_items に upsert（FR-007 対応）
```

### `App\Actions\WatchItem\UpdateWatchStatusAction`

```
入力: UserWatchItem $item, WatchStatus $newStatus
出力: UserWatchItem
例外: なし
副作用: status + 対応タイムスタンプを更新
```

### `App\Actions\WatchItem\DeleteWatchItemAction`

```
入力: UserWatchItem $item
出力: void
副作用: user_watch_items から削除（動画は未整理状態に戻る）
```

---

## Policy インターフェース

### `App\Policies\UserWatchItemPolicy`

| メソッド | 主体 | 対象 | 許可条件 |
|---------|------|------|---------|
| `create` | `Profile` | `YoutubeVideo` | 動画のチャンネルがユーザーの `user_channels` に含まれる |
| `view` | `Profile` | `UserWatchItem` | `$item->profile_id === $profile->id` |
| `update` | `Profile` | `UserWatchItem` | `$item->profile_id === $profile->id` |
| `delete` | `Profile` | `UserWatchItem` | `$item->profile_id === $profile->id` |

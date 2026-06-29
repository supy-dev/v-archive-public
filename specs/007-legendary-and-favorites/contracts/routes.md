# Route Contracts: Feature 007

## 新規エンドポイント

### PATCH /archives/{watchItem}/favorite

**用途**: 動画の神回フラグ（`user_watch_items.is_favorite`）をトグル

**Controller**: `App\Http\Controllers\WatchItemFavoriteController::update`

**Middleware**: `auth.supabase`, `throttle:memo-mutations`

**Authorization**: `UserWatchItemPolicy::update($profile, $watchItem)`

**Request**: なし（ボディ不要）

**Response (200 JSON)**:
```json
{
  "is_favorite": true
}
```

**Error (403)**: 他ユーザーの watchItem への操作

**Route name**: `archives.watch-item.favorite.update`

---

### GET /memos

**用途**: ログインユーザーの全タイムスタンプメモ一覧（保管庫）

**Controller**: `App\Http\Controllers\MemoController::index`

**Middleware**: `auth.supabase`, `throttle:60,1`

**Query Parameters**:

| パラメータ | 型 | 説明 |
|-----------|-----|------|
| `oshi_id` | UUID（任意） | 推し別フィルタ |
| `tag_id` | UUID（任意） | タグ別フィルタ |
| `month` | `YYYY-MM`（任意） | 年月別フィルタ（`timestamp_memos.created_at` 基準） |

**Response**: Blade view `memos/index.blade.php`

**Pagination**: 20件/ページ（`withQueryString()`）

**Route name**: `memos.index`

---

## 変更エンドポイント

### GET /favorites（既存 → 変更）

**変更内容**: `FavoriteController::index()` を2タブ対応に拡張

**Query Parameters（追加）**:

| パラメータ | 値 | 説明 |
|-----------|-----|------|
| `tab` | `kamikai`（デフォルト）/ `memos` | 表示タブ切り替え |

パラメータなし → `tab=kamikai` と同等（神回動画タブ）。

**tab=kamikai 追加クエリパラメータ**:

| パラメータ | 型 | 説明 |
|-----------|-----|------|
| `oshi_id` | UUID（任意） | 推し別フィルタ |
| `month` | `YYYY-MM`（任意） | 年月別フィルタ（`user_watch_items.updated_at` 基準） |

**tab=memos クエリパラメータ（既存維持）**:

| パラメータ | 型 | 説明 |
|-----------|-----|------|
| `oshi_id` | UUID（任意） | 推し別フィルタ |
| `tag_id` | UUID（任意） | タグ別フィルタ |
| `month` | `YYYY-MM`（任意） | 年月別フィルタ（`timestamp_memos.created_at` 基準） |

---

## 変更なしのエンドポイント（参考）

以下は本Featureで変更しない既存エンドポイント:

| Method | URL | 用途 |
|--------|-----|------|
| POST | `/archives/{watchItem}/memos` | タイムスタンプメモ作成 |
| PATCH | `/archives/{watchItem}/memos/{memo}` | タイムスタンプメモ更新 |
| DELETE | `/archives/{watchItem}/memos/{memo}` | タイムスタンプメモ削除 |
| PATCH | `/archives/{watchItem}/memos/{memo}/favorite` | ★トグル（メモ） |
| PUT/DELETE | `/archives/{watchItem}/note` | 感想メモ |

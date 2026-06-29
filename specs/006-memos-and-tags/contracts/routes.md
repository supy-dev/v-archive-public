# API / Route Contracts: メモ・タグ・神回お気に入り

すべてのルートは `auth.supabase` ミドルウェア適用済み。

---

## タイムスタンプメモ

### POST /archives/{watchItem}/memos
タイムスタンプメモを新規作成する。

**Rate limit**: `memo-mutations`（60 req/min）

**Request** (JSON):
```json
{
  "seconds": 123,
  "body": "この場面最高だった",
  "tag_ids": ["uuid-of-system-tag"],
  "new_tag_names": ["オリジナルタグ"]
}
```

**バリデーション**:
- `seconds`: integer, >= 0, required
- `body`: string, 1〜1000 文字, required
- `tag_ids`: array of uuid, optional
- `new_tag_names`: array of string (各 50 文字以内), optional

**Response** `201 Created` (JSON):
```json
{
  "memo": {
    "id": "uuid",
    "seconds": 123,
    "seconds_label": "2:03",
    "body": "この場面最高だった",
    "is_favorite": false,
    "tags": [
      { "id": "uuid", "name": "笑った", "slug": "waratta", "color": "mint" },
      { "id": "uuid", "name": "オリジナルタグ", "slug": "originalutagu", "color": null }
    ],
    "youtube_url": "https://www.youtube.com/watch?v=VIDEO_ID&t=123s",
    "created_at": "2026-06-20T12:00:00Z"
  }
}
```

**Errors**:
- `422` バリデーションエラー
- `403` 他ユーザーの watchItem への操作

---

### PATCH /archives/{watchItem}/memos/{memo}
タイムスタンプメモを更新する。

**Rate limit**: `memo-mutations`

**Request** (JSON):
```json
{
  "seconds": 130,
  "body": "修正後の本文",
  "tag_ids": ["uuid"],
  "new_tag_names": []
}
```

**Response** `200 OK` (JSON): `POST` と同じ `memo` 構造

**Errors**:
- `422` バリデーションエラー
- `403` 所有権なし
- `404` メモが存在しない

---

### DELETE /archives/{watchItem}/memos/{memo}
タイムスタンプメモを削除する（関連タグの紐付けも削除）。

**Rate limit**: `memo-mutations`

**Response** `204 No Content`

**Errors**:
- `403` 所有権なし
- `404` メモが存在しない

---

### PATCH /archives/{watchItem}/memos/{memo}/favorite
お気に入りフラグをトグルする。

**Rate limit**: `memo-mutations`

**Request**: なし（ボディ不要）

**Response** `200 OK` (JSON):
```json
{ "is_favorite": true }
```

---

## 動画ノート

### PUT /archives/{watchItem}/note
動画ノートを upsert する（存在しなければ作成、あれば上書き）。

**Rate limit**: `memo-mutations`

**Request** (JSON):
```json
{ "body": "全体感想のテキスト" }
```

**バリデーション**:
- `body`: string, 1〜5000 文字, required

**Response** `200 OK` (JSON):
```json
{ "status": "saved", "updated_at": "2026-06-20T12:00:00Z" }
```

**Errors**:
- `422` バリデーションエラー
- `403` 所有権なし

---

### DELETE /archives/{watchItem}/note
動画ノートを削除する。

**Rate limit**: `memo-mutations`

**Response** `204 No Content`

**Errors**:
- `403` 所有権なし
- `404` ノートが存在しない

---

## 神回・お気に入り一覧

### GET /favorites
お気に入り登録済みのタイムスタンプメモ一覧（サーバーサイドレンダリング）。

**Rate limit**: なし（通常 throttle:60,1 を適用）

**Query Parameters**:
| パラメータ | 型 | 説明 |
|---|---|---|
| `oshi_id` | uuid | 推しでフィルタ |
| `tag_id` | uuid | タグでフィルタ |
| `month` | string `YYYY-MM` | 年月でフィルタ |
| `page` | integer | ページネーション |

**Response**: HTML（Blade ビュー `favorites/index.blade.php`）

ビュー変数:
- `$favorites` — `Paginator<TimestampMemo>`（with tags, youtubeVideo.youtubeChannel, oshi）
- `$oshis` — フィルタ選択肢
- `$tags` — フィルタ選択肢（システムタグ + ユーザー固有タグ）
- `$months` — フィルタ選択肢（`YYYY-MM` 一覧）
- `$filters` — 現在のフィルタ値

---

## レートリミッター（追加定義）

`app/Providers/AppServiceProvider.php`（または `RouteServiceProvider.php`）に追記:

```php
RateLimiter::for('memo-mutations', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id);
});
```

---

## ルートグループ追加（web.php）

```php
// メモ・ノート操作（JSON API）
Route::middleware(['auth.supabase', 'throttle:memo-mutations'])->group(function () {
    Route::post('/archives/{watchItem}/memos', [TimestampMemoController::class, 'store'])
        ->name('archives.memos.store');
    Route::patch('/archives/{watchItem}/memos/{memo}', [TimestampMemoController::class, 'update'])
        ->name('archives.memos.update');
    Route::delete('/archives/{watchItem}/memos/{memo}', [TimestampMemoController::class, 'destroy'])
        ->name('archives.memos.destroy');
    Route::patch('/archives/{watchItem}/memos/{memo}/favorite', [TimestampMemoFavoriteController::class, 'update'])
        ->name('archives.memos.favorite.update');
    Route::put('/archives/{watchItem}/note', [VideoNoteController::class, 'upsert'])
        ->name('archives.note.upsert');
    Route::delete('/archives/{watchItem}/note', [VideoNoteController::class, 'destroy'])
        ->name('archives.note.destroy');
});

// 神回・お気に入り一覧（通常ページ）
Route::middleware(['auth.supabase', 'throttle:60,1'])->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index'])
        ->name('favorites.index');
});
```

# Route Contracts: プレイヤーと再生進捗管理 (Feature 005)

## 既存ルートへの追加

### GET /archives/{watchItem}

| 項目 | 値 |
|------|---|
| **ルート名** | `archives.show` |
| **Controller** | `ArchiveController::show` |
| **Middleware** | `auth.supabase`, `throttle:60,1` |
| **Route Model Binding** | `UserWatchItem` |
| **認可** | `UserWatchItemPolicy::view` — 他ユーザーの watch_item は 403 |
| **成功レスポンス** | 200 HTML（`archives.show` Blade ビュー） |
| **404** | watch_item が存在しない場合 |
| **403** | 他ユーザーの watch_item にアクセスした場合 |

---

### PATCH /watch-items/{watchItem}/position

| 項目 | 値 |
|------|---|
| **ルート名** | `watch-items.position.update` |
| **Controller** | `PlaybackPositionController::update` |
| **Middleware** | `auth.supabase`, `throttle:playback-position`（10 req/分/ユーザー） |
| **Route Model Binding** | `UserWatchItem` |
| **認可** | `UserWatchItemPolicy::update` — 他ユーザーの watch_item は 403 |

**リクエストボディ（JSON）**:

```json
{
  "last_position_seconds": 185,
  "is_ended": false
}
```

| フィールド | 型 | 必須 | バリデーション |
|-----------|-----|------|--------------|
| `last_position_seconds` | integer | YES | `min:0`、`max:duration_seconds`（取得できる場合） |
| `is_ended` | boolean | YES | — |

**レスポンス**:

| ステータス | 条件 |
|----------|------|
| `204 No Content` | 更新成功（ボディなし） |
| `401 Unauthorized` | 未認証 |
| `403 Forbidden` | 他ユーザーの watch_item |
| `404 Not Found` | watch_item が存在しない |
| `422 Unprocessable Entity` | バリデーションエラー |
| `429 Too Many Requests` | レートリミット超過 |

**サーバー側の副作用**（レスポンスボディには含まれない）:

- `is_ended: false` かつ status == `want_to_watch` → status を `watching` へ変更、`started_at` を設定
- `is_ended: true` かつ status != `watched` → status を `watched` へ変更、`watched_at` を設定
- 上書き防止: `last_position_seconds` は `is_ended: false` 時、現在値より大きい場合のみ更新

---

## クライアントサイド JavaScript コントラクト

### `onYouTubeIframeAPIReady` コールバック

```
グローバルスコープに定義。
YouTube IFrame API スクリプト読み込み完了後に呼ばれる。
Alpine コンポーネントの init() 内で設定する。
```

### Alpine コンポーネント: `youtubePlayer`

```js
// x-data="youtubePlayer({ watchItemId, videoId, startSeconds, csrfToken, positionUrl })"
{
  player: null,
  lastSavedPosition: startSeconds,
  isFirstPlay: true,
  saveTimer: null,

  init() { ... },              // YT.Player 初期化 + pagehide 登録
  initPlayer() { ... },        // new YT.Player(...)
  onStateChange(event) { ... },// PLAYING / PAUSED / ENDED 分岐
  onPlaying() { ... },         // タイマー開始
  periodicSave() { ... },      // 60s ごとの保存（diff < 5s は省略）
  savePosition(isEnded) { ... }, // fetch PATCH 送信
  seekTo(seconds) { ... },     // 「アプリ内で続きから再生」
  openInYoutube(videoId, seconds) { ... }, // YouTube URL を新しいタブで開く
}
```

### YouTube 外部リンク形式（FR-013）

```
https://www.youtube.com/watch?v={videoId}&t={lastPositionSeconds}s
```

新しいタブで開く（`target="_blank" rel="noopener noreferrer"`）。

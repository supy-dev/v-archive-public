# Job Contracts: 動画同期（Video Sync）

**Feature**: 003-video-sync
**Date**: 2026-06-20

---

## InitialSyncYoutubeChannelJob

**目的**: チャンネル登録後の初回動画取得（最新50件）

**実装インターフェース**: `ShouldQueue`, `ShouldBeUnique`

**コンストラクタ引数**:
```
YoutubeChannel $youtubeChannel
```

**一意キー**: `$youtubeChannel->id`（同一チャンネルの重複Job防止）

**処理フロー**:
```
1. youtubeChannel.uploads_playlist_id が null → skip & log warning
2. FetchUploadedVideosService::fetchLatest($channel, maxPages=1) を呼び出し
3. FetchVideoDetailsService::fetchBatch($videoIds) を呼び出し
4. SyncChannelVideosService::upsert($channel, $videoDetails) を実行
5. youtubeChannel.sync_status = 'synced'、last_synced_at = now() に更新
```

**エラーハンドリング**:
- 429 / 5xx → リトライ（最大3回、exponential backoff: 60s, 120s, 240s）
- 4xx（チャンネル不存在など） → リトライなし、sync_status = 'error'、sync_error_message に記録
- APIキーをログに出力しない

**テスト検証点**:
- [ ] `youtube_videos` に最大50件が作成される
- [ ] 同一`youtube_video_id`の重複レコードが作成されない
- [ ] `sync_status`が`synced`に更新される
- [ ] APIキーがログに出力されない（Http::assertNothingSent 以外の方法で確認）
- [ ] 429エラー時にリトライされる

---

## SyncYoutubeChannelJob

**目的**: 定期同期（新着動画のみ取得）

**実装インターフェース**: `ShouldQueue`, `ShouldBeUnique`

**コンストラクタ引数**:
```
YoutubeChannel $youtubeChannel
```

**一意キー**: `$youtubeChannel->id`

**処理フロー**:
```
1. FetchUploadedVideosService::fetchUntilKnown($channel) を呼び出し
   ─ 既存の youtube_video_id に到達した時点でフェッチ終了
2. FetchVideoDetailsService::fetchBatch($newVideoIds) を呼び出し
3. SyncChannelVideosService::upsert($channel, $videoDetails) を実行
4. live_status=live の動画 → videos.list で詳細を再取得して更新
5. last_synced_at = now() に更新
```

**テスト検証点**:
- [ ] 既存videoIdに到達したらAPIページネーションが停止する
- [ ] 新着動画のみが追加される（既存は上書きのみ）
- [ ] `live_status=live`の動画が更新される

---

## FetchOlderYoutubeVideosJob

**目的**: ユーザー操作による過去動画の追加取得

**実装インターフェース**: `ShouldQueue`, `ShouldBeUnique`

**コンストラクタ引数**:
```
YoutubeChannel $youtubeChannel
```

**一意キー**: `$youtubeChannel->id`（取得中の重複操作防止）

**処理フロー**:
```
1. youtubeChannel.oldest_page_token を取得
   ─ null かつ oldest_fetched_at が null → 初回（エラー: 初回同期が必要）
   ─ oldest_page_token が null かつ oldest_fetched_at が存在 → 全件取得済み
2. FetchUploadedVideosService::fetchPage($channel, $pageToken) を呼び出し
3. FetchVideoDetailsService::fetchBatch($videoIds) を呼び出し
4. SyncChannelVideosService::upsert($channel, $videoDetails) を実行
5. youtubeChannel.oldest_page_token = 次ページトークン（またはnull）に更新
6. youtubeChannel.oldest_fetched_at = 取得した最古動画の published_at に更新
```

**テスト検証点**:
- [ ] 既存より古い動画が追加される
- [ ] `oldest_page_token`が次ページトークンで更新される
- [ ] 全件取得済み時（nextPageToken=null）に`oldest_page_token`がnullになる
- [ ] 共有マスタとして複数ユーザーが参照できる

---

## RefreshYoutubeVideoDetailsJob

**目的**: ライブ配信終了後の動画詳細更新（尺・`actual_end_at`・`live_status`）

**実装インターフェース**: `ShouldQueue`

**コンストラクタ引数**:
```
YoutubeVideo $video
```

**処理フロー**:
```
1. videos.list?id={youtube_video_id} で最新詳細を取得
2. live_status、duration_seconds、actual_end_at を更新
3. video_type を再判定して更新
```

**トリガー**: `SyncYoutubeChannelJob`内で`live_status=live`を検出時にdispatch

**テスト検証点**:
- [ ] `actual_end_at`が更新される
- [ ] `live_status`が`completed`に更新される
- [ ] `duration_seconds`が設定される

---

## MarkUnavailableYoutubeVideosJob

**目的**: 削除・非公開になった動画を`is_available=false`に更新

**実装インターフェース**: `ShouldQueue`

**コンストラクタ引数**:
```
// バッチ処理: Artisanコマンドからdispatch（チャンネル単位またはバッチ単位）
array $youtubeVideoIds  // youtube_video_id（文字列）の配列（最大50件）
```

**処理フロー**:
```
1. videos.list?id={ids} で最大50件を一括取得
2. APIが返さなかったID（削除済み）→ is_available = false
3. privacy_status == 'private' だったが現在 != 'public' → is_available = false
4. 更新があった場合のみDEレコードを更新
```

**テスト検証点**:
- [ ] APIが返さない動画IDの`is_available`が`false`になる
- [ ] 利用可能な動画の`is_available`は変更されない
- [ ] ユーザーのメモ・タイムスタンプレコードは削除されない

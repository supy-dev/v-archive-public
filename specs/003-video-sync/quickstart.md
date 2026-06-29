# Quickstart: 動画同期（Video Sync）検証ガイド

**Feature**: 003-video-sync
**Date**: 2026-06-20

---

## 前提条件

- Feature 002（oshi-and-channel-registration）が実装済みであること
- `youtube_channels` / `user_channels` / `oshis` テーブルが存在すること
- `php artisan test`が90/90 PASSであること
- `.env`に`YOUTUBE_API_KEY=`（テストはモック使用のため空でよい）

---

## セットアップ

```bash
# マイグレーション実行（youtube_videos + youtube_channels へのカラム追加）
php artisan migrate

# テスト実行（全スイート）
php artisan test
```

---

## 検証シナリオ

### シナリオ 1: 初回同期Job（US1）

**目的**: チャンネル登録後にJobが実行されると`youtube_videos`に動画が作成されることを確認

```bash
php artisan test tests/Feature/Sync/InitialSyncTest.php
```

**期待される出力**:
```
✓ チャンネル登録後に初回同期Jobがdispatchされる
✓ 初回同期で最新50件の動画が作成される
✓ 同一youtube_video_idで重複レコードが作成されない（upsert冪等性）
✓ sync_statusがsynced に更新される
✓ 429エラー時にリトライされる
✓ APIキーがログに出力されない
```

### シナリオ 2: 定期同期（US2）

**目的**: 既存IDに到達したら取得を停止し、新着のみ追加されることを確認

```bash
php artisan test tests/Feature/Sync/PeriodicSyncTest.php
```

**期待される出力**:
```
✓ 既存videoIdに到達した時点でフェッチが停止する
✓ 新着動画のみが追加される
✓ last_synced_at が更新される
✓ live_status=live の動画が更新される
✓ dispatch-syncsコマンドがsync_enabled=trueチャンネルのみ対象にする
✓ ShouldBeUniqueにより同一チャンネルの重複Jobがキューされない
```

### シナリオ 3: 過去動画取得（US3）

**目的**: `oldest_page_token`を使って過去ページを取得できることを確認

```bash
php artisan test tests/Feature/Sync/FetchOlderVideosTest.php
```

**期待される出力**:
```
✓ 既存より古い動画が追加される
✓ oldest_page_token が次ページトークンで更新される
✓ 全件取得済み時にoldest_page_tokenがnullになる
✓ 共有マスタとして全ユーザーが参照できる
```

### シナリオ 4: 削除動画の検出（US4）

**目的**: `is_available=false`への更新でメモが保持されることを確認

```bash
php artisan test tests/Feature/Sync/MarkUnavailableTest.php
```

**期待される出力**:
```
✓ APIが返さない動画IDのis_availableがfalseに更新される
✓ 利用可能な動画のis_availableは変更されない
```

### シナリオ 5: Unit Tests

```bash
php artisan test tests/Unit/YouTube/
```

**期待される出力**:
```
✓ ISO 8601 duration → 秒数変換（PT1H23M45S → 5025）
✓ video_type判定ロジック
✓ live_status判定ロジック
✓ playlistItems.list呼び出しパラメータ検証
✓ videos.list バッチ取得（50件）
✓ description先頭500文字切り詰め
```

---

## Artisan コマンド手動検証

```bash
# dispatch-syncsコマンドのドライラン確認（Jobはdispatchされるが実行はQueueに委ねる）
php artisan youtube:dispatch-syncs

# mark-unavailableコマンドの動作確認
php artisan youtube:mark-unavailable

# Scheduler定義の確認
php artisan schedule:list
```

**`schedule:list` の期待出力**:
```
* * * * *   php artisan schedule:run
...
*/30 * * * *  php artisan youtube:dispatch-syncs  (withoutOverlapping)
0 3 * * *     php artisan youtube:mark-unavailable  (withoutOverlapping)
```

---

## データ確認クエリ

```sql
-- youtube_videosの件数確認
SELECT youtube_channel_id, COUNT(*), MIN(published_at), MAX(published_at)
FROM youtube_videos
GROUP BY youtube_channel_id;

-- 重複チェック（0件であること）
SELECT youtube_video_id, COUNT(*) as cnt
FROM youtube_videos
GROUP BY youtube_video_id
HAVING cnt > 1;

-- 非公開動画の確認
SELECT id, title, is_available
FROM youtube_videos
WHERE is_available = FALSE;

-- チャンネルの同期状態
SELECT id, title, sync_status, last_synced_at, oldest_fetched_at
FROM youtube_channels;
```

---

## 参照ドキュメント

- データモデル: [data-model.md](data-model.md)
- Job契約: [contracts/jobs.md](contracts/jobs.md)
- Service契約: [contracts/services.md](contracts/services.md)
- 研究メモ: [research.md](research.md)

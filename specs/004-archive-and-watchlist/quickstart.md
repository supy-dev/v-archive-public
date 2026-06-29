# Quickstart Validation Guide: Feature 004

Feature 003 が完了し `youtube_videos` にデータが存在する前提で、Feature 004 の動作を検証するシナリオをまとめる。

## 前提条件

1. Feature 001〜003 が実装済みであること（認証 / 推しチャンネル管理 / 動画同期）
2. 少なくとも1名のユーザーが推しチャンネルを登録し、`youtube_videos` に1件以上のデータが存在すること
3. Docker Compose が起動しており、`php artisan migrate` が済んでいること

---

## セットアップ

```bash
# マイグレーション実行（Feature 004 追加分）
php artisan migrate

# ローカルサーバ起動
php artisan serve
```

---

## シナリオ 1 — 新着アーカイブ一覧（US1）

### 1-a. 未整理動画が一覧に表示される

1. ブラウザで `/archive` を開く
2. **期待**: 登録チャンネルの `is_available=true` の動画が `published_at` 降順で一覧表示される
3. **確認項目**: サムネイル・タイトル・推し名・公開日時・動画時間が各カードに表示されること（FR-013）
4. **確認項目**: ページネーションが表示され、1ページに最大20件であること（FR-012）

### 1-b. 「見るリストに追加」ボタンで追加される

1. 任意の動画カードの「見るリストに追加」ボタンを押す
2. **期待**: `user_watch_items` に `status='want_to_watch'` のレコードが作成される
   ```sql
   SELECT * FROM user_watch_items WHERE youtube_video_id = '<動画ID>' AND profile_id = '<ユーザーID>';
   -- status='want_to_watch', added_at が現在時刻に近い値
   ```
3. **期待**: その動画がアーカイブ一覧から消える（未整理フィルタから除外）

### 1-c. 「見送る」ボタンで見送り登録される

1. 別の動画カードの「見送る」ボタンを押す
2. **期待**: `user_watch_items` に `status='skipped'` で作成、`skipped_at` が設定される
3. **期待**: その動画がアーカイブ一覧から消える

### 1-d. 推しフィルタ

1. フィルタで特定の推しを選択する
2. **期待**: 選択した推しの `user_channels → youtube_channel_id` の動画のみ表示される（FR-004）

### 1-e. 空状態

1. 全動画を操作して未整理がなくなった場合
2. **期待**: 「新着の未整理動画はありません」などの空状態メッセージが表示される（US1 AC-6）

---

## シナリオ 2 — 見るリスト管理（US2）

### 2-a. 「未視聴」タブに追加済み動画が表示される

1. `/watchlist` を開く（または `/watchlist?status=want_to_watch`）
2. **期待**: シナリオ 1-b で追加した動画が表示される（FR-006）

### 2-b. ステータスを「視聴済み」に変更する

1. 見るリストの動画カードの「視聴済み」ボタンまたはドロップダウンで変更する
2. **期待**: DBで `status='watched'`、`watched_at` が現在時刻に更新される
   ```sql
   SELECT status, watched_at FROM user_watch_items WHERE id = '<ID>';
   ```
3. **期待**: 「未視聴」タブから消え、「視聴済み」タブへ移動する

### 2-c. ステータスを「見送る」に変更する

1. 別の動画を「見送り」に変更する
2. **期待**: `status='skipped'`、`skipped_at` が設定される

### 2-d. 「未視聴」に戻す

1. 「視聴済み」タブの動画を「未視聴」に戻す
2. **期待**: `status='want_to_watch'` に更新、「視聴済み」タブから消える（US2 AC-4）

### 2-e. 削除して未整理に戻す

1. 見るリストの動画を削除する
2. **期待**: `user_watch_items` から削除される
3. **期待**: `/archive` を開くとその動画が再び「未整理」として表示される（FR-015）

### 2-f. 所有権保護

1. 別ユーザーの `user_watch_items.id` を直接 URL に入力して PATCH を試みる
2. **期待**: 403 Forbidden が返る（FR-009）

---

## シナリオ 3 — ホームサマリー（US3）

1. `/` (ホーム画面) を開く
2. **期待**: 以下のカウントが正確に表示される（FR-010）
   - 未整理件数: 登録チャンネルの `is_available=true` 動画数 - 自分の `user_watch_items` 数
   - 見るリスト: `status='want_to_watch'` の件数
   - 視聴中: `status='watching'` の件数（0件でも表示）
   - 視聴済み: `status='watched'` の件数

3. **期待**: 各サマリーカードのリンクをクリックすると正しいページへ遷移する（US3 AC-2/3）

---

## 重複作成防止テスト

同一動画に対して同一ユーザーが「見るリストに追加」を2回送信する:

```bash
# 1回目: 成功（201 or redirect）
# 2回目: エラーなく同じレコードを返す（upsert による冪等処理）
SELECT COUNT(*) FROM user_watch_items WHERE youtube_video_id='<ID>' AND profile_id='<UID>';
-- 結果: 1 (重複なし)
```

---

## 自動テスト実行

```bash
# Feature テスト一式
php artisan test --filter Feature004

# 個別テスト
php artisan test tests/Feature/Archive/ArchiveIndexTest.php
php artisan test tests/Feature/WatchList/StoreWatchItemTest.php
php artisan test tests/Feature/WatchList/UpdateWatchStatusTest.php
php artisan test tests/Feature/WatchList/DeleteWatchItemTest.php
php artisan test tests/Feature/Home/HomeSummaryTest.php
```

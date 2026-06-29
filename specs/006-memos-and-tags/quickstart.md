# Quickstart / Validation Guide: メモ・タグ・神回お気に入り

## 前提条件

- Feature 005（player-and-playback）実装済み
- Docker Compose ローカル環境が起動中
- 認証済みユーザーが存在し、少なくとも 1 件の `user_watch_items` がある

## セットアップ

```bash
# マイグレーション
php artisan migrate

# システムタグの投入
php artisan db:seed --class=SystemTagSeeder

# Vite アセットビルド（開発時）
npm run dev
```

---

## US1: タイムスタンプメモの記録とシーク

### シナリオ検証手順

1. `/archives/{watchItem}` を開き、YouTube プレイヤーで動画を再生する（30秒ほど再生）
2. **「現在位置をメモ」ボタンをクリック**
   - 期待値: メモ入力フォームが開き、`seconds` 欄に「現在位置 - 5 秒」が入力されている
3. 本文に「テストメモ」と入力し、システムタグ「笑った」を選択して保存
   - 期待値: フォームが閉じ、メモ一覧に新しいメモが追加される（ページリロードなし）
   - 期待値: メモに「2:25」等のタイムスタンプと「笑った」タグが表示される
4. タイムスタンプをクリック
   - 期待値: YouTube プレイヤーが対応秒数から再生を開始する
5. 本文を空にしてメモを保存しようとする
   - 期待値: バリデーションエラーが表示され、保存されない

### Feature テスト対応

```bash
php artisan test tests/Feature/Memo/TimestampMemoStoreTest.php
php artisan test tests/Feature/Memo/TimestampMemoOwnershipTest.php
```

---

## US2: 動画ノート（全体感想）の保存

### シナリオ検証手順

1. `/archives/{watchItem}` の「全体感想」セクションを確認
   - 期待値: 空のテキストエリアと無効化された「保存」ボタンが表示される
2. テキストを入力
   - 期待値: 「保存」ボタンが有効になる
3. 保存ボタンをクリック
   - 期待値: 「保存しました」フィードバックが表示される（AJAX、ページリロードなし）
4. ページをリロードして再確認
   - 期待値: 入力したテキストが表示されている
5. 本文を変更して再保存
   - 期待値: 新しい内容に上書きされる
6. 「削除」ボタンをクリック
   - 期待値: テキストエリアが空になり、保存ボタンが無効化される

### Feature テスト対応

```bash
php artisan test tests/Feature/Memo/VideoNoteTest.php
```

---

## US3: タグ付与

### シナリオ検証手順

1. 新規メモ作成フォームでシステムタグの一覧が表示されることを確認
2. 「笑った」「神シーン」を選択してメモを保存
   - 期待値: メモ一覧で 2 つのタグが表示される
3. 新規メモ作成時にタグ入力欄に「推し活メモ」と入力して Enter を押し保存
   - 期待値: メモ一覧でユーザー固有タグ「推し活メモ」が表示される
4. 別のメモを作成するとき、「推し活メモ」がユーザー固有タグとして選択肢に表示されることを確認

### Feature テスト対応

```bash
php artisan test tests/Feature/Memo/TagTest.php
```

---

## US4: お気に入りと神回一覧

### シナリオ検証手順

1. タイムスタンプメモのお気に入りボタン（☆）をクリック
   - 期待値: ボタンが★（お気に入り済み）に切り替わる（AJAX）
2. `/favorites` を開く
   - 期待値: お気に入り登録したメモのタイムスタンプ・本文・動画タイトルが表示される
3. 推しフィルタ・タグフィルタ・年月フィルタを適用
   - 期待値: 絞り込み結果が表示される
4. お気に入りを解除
   - 期待値: お気に入り一覧からそのメモが消える

### Feature テスト対応

```bash
php artisan test tests/Feature/Memo/TimestampMemoFavoriteTest.php
php artisan test tests/Feature/Favorites/FavoritesIndexTest.php
```

---

## セキュリティ・権限検証

```bash
# 所有権テスト（全 Feature テストで検証済み）
php artisan test tests/Feature/Memo/TimestampMemoOwnershipTest.php

# XSS 防止確認: メモ本文に "<script>alert(1)</script>" を入力して保存
# 期待値: 画面には "<script>alert(1)</script>" としてテキスト表示される（アラートが実行されない）
```

---

## 全テスト一括実行

```bash
php artisan test tests/Feature/Memo/ tests/Feature/Favorites/
```

---

## 参照ドキュメント

- ルート定義: [contracts/routes.md](contracts/routes.md)
- データモデル: [data-model.md](data-model.md)
- Alpine.js パターン設計: [research.md](research.md)

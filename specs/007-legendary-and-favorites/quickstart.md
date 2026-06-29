# Quickstart Validation Guide: Feature 007

## 前提

- Feature 006 の実装済み環境（Docker Compose ローカル）
- DB にテストユーザー・推し・チャンネル・動画・タイムスタンプメモが存在すること
- `php artisan serve`（またはSail）が起動済み

## シナリオ 1: 神回登録トグル（US1）

**目的**: 配信詳細ページで `user_watch_items.is_favorite` をトグルできること

1. ログイン後、`/watchlist` から任意の動画を開く → 配信詳細ページ（`/archives/{watchItem}`）
2. ページ上部またはメタ情報エリアに「神回に登録」ボタンが表示されていることを確認
3. ボタンをクリック → ページ遷移なしにボタンが「神回解除」状態に変わることを確認
4. DB を確認: `select is_favorite from user_watch_items where id = '{watchItem}';` → `true`
5. 再度クリック → `false` に戻ることを確認

**エラーケース**: 別ユーザーのセッションで `PATCH /archives/{watchItem}/favorite` へ直接リクエスト → 403 が返ること

---

## シナリオ 2: /favorites 2タブ（US2）

**目的**: `/favorites` が神回動画タブとお気に入りメモタブを正しく切り替えること

### 2a. 神回タブ（デフォルト）

1. `/favorites`（パラメータなし）にアクセス → 「神回」タブがアクティブ
2. シナリオ1で登録した動画がカード表示されること（タイトル・サムネイル・チャンネル名・登録日）
3. カードのリンクをクリック → アプリ内 `/archives/{watchItem}` へ遷移すること（YouTube ではない）
4. 「お気に入りメモ」タブをクリック → URL が `?tab=memos` に変わり、★メモ一覧が表示されること

### 2b. フィルタ（神回タブ）

1. `/favorites?tab=kamikai&oshi_id={oshi_id}` → 指定推しのチャンネルの神回のみ表示されること
2. `/favorites?tab=kamikai&month=2026-06` → 2026年6月に神回登録した動画のみ表示されること

### 2c. お気に入りメモタブ

1. `/favorites?tab=memos` → 従来の `/favorites` 表示と同等（★メモ一覧）が表示されること
2. タグフィルタ・推しフィルタ・年月フィルタが従来通り動作すること

### 2d. 空状態

1. 神回動画が0件の状態で `/favorites` を開く → 「神回登録を促す」空状態メッセージが表示されること

---

## シナリオ 3: /memos 保管庫（US3）

**目的**: `/memos` が全タイムスタンプメモを★問わず表示し、サイドバーから遷移できること

1. サイドバーの「タイムスタンプメモ」をクリック → `/memos` へ遷移すること（従来の `#timestamps` ではない）
2. `/memos` に★メモと非★メモの両方が表示されること
3. `/memos?oshi_id={oshi_id}` → 指定推しのメモのみ表示されること
4. `/memos?tag_id={tag_id}` → 指定タグのメモのみ表示されること
5. `/memos?month=2026-06` → 2026年6月作成のメモのみ表示されること
6. 各メモカードの主リンク → `/archives/{watchItem}` へ遷移すること（YouTube ではない）
7. 各メモカードに★ボタンが**存在しない**こと（FR-017）

---

## シナリオ 4: ホーム導線（US3）

1. ホームの「最近のタイムスタンプ」エリアに固定文言ではなく実データのメモが最新3件表示されること
2. 「もっと見る」をクリック → `/memos` へ遷移すること
3. 「神回リスト」バナーをクリック → `/favorites`（神回タブ）へ遷移すること

---

## 自動テスト実行

```bash
# Feature 007 関連テストのみ実行
php artisan test tests/Feature/WatchItem/WatchItemFavoriteTest.php
php artisan test tests/Feature/Favorites/FavoritesTabTest.php
php artisan test tests/Feature/Memo/MemoIndexTest.php
php artisan test tests/Feature/Home/HomeSummaryTest.php

# 全 Feature テスト（リグレッション確認）
php artisan test tests/Feature/
```

## レスポンシブ確認

- Desktop 960px: `/favorites` の2タブ表示、`/memos` の一覧が横スクロールなし
- Mobile 390px: タブ切り替えボタンが十分なタップ領域を持ち、カード一覧が収まること

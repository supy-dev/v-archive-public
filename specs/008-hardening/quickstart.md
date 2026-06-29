# Quickstart: 本番品質強化（hardening）検証ガイド

**Feature**: 008-hardening | **Date**: 2026-06-22

---

## 前提条件

- Feature 007 (`007-legendary-and-favorites`) の実装が完了していること
- `php artisan migrate` が通っていること（インデックス追加マイグレーション含む）
- `composer test` がグリーンであること

---

## シナリオ 1: 所有権チェック確認（FR-001〜FR-004）

既存テストで確認済みのため、テスト実行で代替する。

```bash
# 所有権テストのみ実行
php artisan test tests/Feature/Auth/OwnershipTest.php
php artisan test tests/Feature/Oshi/OshiOwnershipTest.php
php artisan test tests/Feature/Channel/ChannelOwnershipTest.php
php artisan test tests/Feature/Memo/TimestampMemoOwnershipTest.php
php artisan test tests/Feature/Playback/PlaybackOwnershipTest.php

# 期待結果: 全テスト PASSED
```

---

## シナリオ 2: レート制限確認（FR-003・FR-004）

```bash
# レート制限テスト実行
php artisan test tests/Feature/Hardening/RateLimitTest.php

# 期待結果:
# - fetchOlder を 6 回呼ぶと 6 回目が 429 を返す
# - memo 操作（メモ作成など）は 61 回目で 429 を返す
```

手動確認（任意）:
```bash
# テスト環境でのレート制限確認
# 別ウィンドウで php artisan serve を起動後、curl で fetchOlder を連打
# ※ 実際の curl コマンドは認証 Cookie が必要なためテストの方が確実
```

---

## シナリオ 3: API 障害フォールバック確認（FR-008・FR-009）

```bash
# YouTube API モックで障害シミュレーション
php artisan test tests/Feature/Hardening/ApiFallbackTest.php

# 期待結果:
# - YouTube API が 503 を返す状態でも GET /archive が 200 を返す
# - GET /memos が 200 を返す
# - GET /favorites が 200 を返す
# - 障害通知メッセージが表示される（手動確認はブラウザで）
```

---

## シナリオ 4: エラーページ確認（FR-006・FR-007）

```bash
# 開発サーバーを起動して手動確認
php artisan serve
```

ブラウザで以下を確認:

| URL | 期待表示 |
|-----|---------|
| `/not-a-real-page` | カスタム 404 ページ（アプリデザイン準拠・ホームへのリンクあり） |
| スタックトレース非表示 | `.env` で `APP_DEBUG=false` を設定して 500 トリガー |

```bash
# 404 の応答確認（curl）
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/not-a-real-page
# 期待: 404
```

---

## シナリオ 5: 規約ページ確認（FR-018〜FR-021）

```bash
# 未認証で規約ページにアクセスできることを確認
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/privacy
# 期待: 200

curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/terms
# 期待: 200
```

ブラウザ確認:
- フッターに「プライバシーポリシー」「利用規約」リンクが表示される
- ログインなしでリンクを開けること
- 収集情報・利用目的・免責事項・最終更新日が記載されていること

---

## シナリオ 6: パフォーマンス・インデックス確認（FR-011〜FR-013）

```bash
# インデックスが作成されていることを確認
php artisan tinker
# >>> Schema::getIndexes('user_watch_items')
# >>> Schema::getIndexes('user_channels')
# 期待: updated_at と sync_enabled を含む複合インデックスが存在する
```

```bash
# N+1 テスト（シードデータが必要な場合）
php artisan db:seed --class=HardeningTestSeeder   # 実装時に作成
php artisan test tests/Feature/Hardening/MainFlowTest.php

# 期待: 一覧クエリ件数が ページ件数（20）に比例せず一定
```

---

## 全テスト一括実行

```bash
composer test
# または
php artisan test

# 期待: 全テスト PASSED（新規 Hardening テスト含む）
```

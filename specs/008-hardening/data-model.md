# Data Model: 本番品質強化（hardening）

**Feature**: 008-hardening | **Date**: 2026-06-22

## 変更概要

本 Feature では新規テーブルの追加は行わない。変更は以下の 2 カテゴリに限定される。

1. **DB インデックス追加**（マイグレーション）: 2 件
2. **レート制限設定追加**（AppServiceProvider）: 1 件（`channel-sync`）

---

## 追加インデックス（新規マイグレーション）

### マイグレーション: `2026_06_22_000001_add_missing_indexes.php`

#### `user_watch_items(profile_id, updated_at)`

- **用途**: 神回タブ（`/favorites?tab=kamikai`）のソートキー `updated_at DESC` でのクエリ最適化
- **理由**: 神回登録・解除時に `updated_at` が更新され、その降順ソートが神回一覧の主要アクセスパターンとなる

#### `user_channels(profile_id, sync_enabled)`

- **用途**: `youtube:dispatch-syncs` コマンドが同期対象チャンネルを抽出する際の絞り込みクエリ
- **理由**: `WHERE sync_enabled = true AND profile_id = ?` の形でアクセスされるため、複合インデックスで抽出を高速化する

```text
追加インデックス一覧:
  user_watch_items: ['profile_id', 'updated_at']
  user_channels:    ['profile_id', 'sync_enabled']
```

---

## レート制限設定変更（AppServiceProvider）

### 追加: `channel-sync` リミッター（5回/分）

```text
名前:     channel-sync
制限値:   5回/分（per user ID）
対象:     POST /oshis/{oshi}/channels/{userChannel}/fetch-older
理由:     YouTube API クォータを直接消費する手動操作。60回/分の oshi-mutations グループから独立させ厳格制限を適用する。
```

### 変更: `fetchOlder` ルートのリミッター付け替え

```text
変更前: throttle:oshi-mutations (60回/分)
変更後: throttle:channel-sync   (5回/分)
対象:   POST /oshis/{oshi}/channels/{userChannel}/fetch-older
```

### 既存リミッター（変更なし）

| リミッター | 制限値 | 変更 |
|-----------|-------|------|
| `oshi-mutations` | 60回/分 | なし |
| `memo-mutations` | 60回/分 | なし |
| `playback-position` | 10回/分 | なし |
| `throttle:60,1`（閲覧ルート） | 60回/分 | なし |

---

## エラービュー（新規ファイル）

新規テーブル・カラムなし。Blade ビューのみ追加。

```text
resources/views/
├── layouts/
│   └── minimal.blade.php          # [新規] 認証不要の最小レイアウト（エラー・規約ページ用）
├── errors/
│   ├── 404.blade.php              # [新規] カスタム 404 ページ
│   └── 500.blade.php              # [新規] カスタム 500 ページ
└── legal/
    ├── privacy.blade.php          # [新規] プライバシーポリシー
    └── terms.blade.php            # [新規] 利用規約
```

---

## 既存テーブルの現状確認（変更なし）

| テーブル | 役割 | 状態 |
|---------|------|------|
| `profiles` | ユーザープロファイル | 変更なし |
| `oshis` | 推し | 変更なし |
| `youtube_channels` | 共有チャンネルマスタ | 変更なし |
| `user_channels` | ユーザー・チャンネル登録 | インデックス追加のみ |
| `youtube_videos` | 共有動画マスタ | 変更なし |
| `user_watch_items` | 視聴アイテム | インデックス追加のみ |
| `tags` | ユーザー固有タグ | 変更なし |
| `timestamp_memos` | タイムスタンプメモ | 変更なし |
| `timestamp_memo_tags` | メモ・タグ中間 | 変更なし |
| `video_notes` | 動画ノート | 変更なし |

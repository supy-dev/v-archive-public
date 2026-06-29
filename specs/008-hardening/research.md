# Research: 本番品質強化（hardening）

**Feature**: 008-hardening | **Date**: 2026-06-22 | **Plan**: [plan.md](plan.md)

## 1. Policy カバレッジ調査

### 現状

| Policy クラス | 対象モデル | AppServiceProvider 登録 | 所有権テスト |
|--------------|-----------|------------------------|------------|
| `OshiPolicy` | `Oshi` | ✅ | ✅ `Oshi/OshiOwnershipTest.php` |
| `ProfilePolicy` | `Profile` | ✅ | ✅ `Auth/OwnershipTest.php` |
| `TagPolicy` | `Tag` | ✅ | ✅ `Memo/TagTest.php` |
| `TimestampMemoPolicy` | `TimestampMemo` | ✅ | ✅ `Memo/TimestampMemoOwnershipTest.php` |
| `UserChannelPolicy` | `UserChannel` | ✅ | ✅ `Channel/ChannelOwnershipTest.php` |
| `UserWatchItemPolicy` | `UserWatchItem` | ✅ | ✅ `Playback/PlaybackOwnershipTest.php` |
| `VideoNotePolicy` | `VideoNote` | ✅ | ✅ `Memo/VideoNoteTest.php`（間接） |

**判定**: Policy カバレッジは完全。全 7 Policy が登録済みかつテスト済み。

### 判断
- Decision: Policy 追加・変更は不要。
- Rationale: 全ユーザー所有データに Policy が適用されており、未カバーの操作は存在しない。
- Alternatives considered: `VideoNote` の所有権テストを専用ファイルで追加することも検討したが、`VideoNoteTest.php` 内でカバー済みのため不要。

---

## 2. レート制限の現状と差分

### 現在の実装（`AppServiceProvider`）

| リミッター名 | 対象 | 制限値 |
|------------|------|-------|
| `oshi-mutations` | 推し/チャンネル変更操作 + `fetchOlder`（過去取得） | 60回/分 |
| `playback-position` | 再生位置保存 | 10回/分 |
| `memo-mutations` | タイムスタンプメモ CRUD + お気に入り/神回トグル | 60回/分 |
| `throttle:60,1`（inline） | 一般認証済み閲覧ルート | 60回/分 |
| `throttle:10,1`（inline） | ログインセッション作成 | 10回/分 |

### 差分・問題点

| 問題 | 詳細 | 対応 |
|-----|------|------|
| **Gap**: `fetchOlder` 専用リミッター未設定 | `fetchOlder` は現在 `oshi-mutations`（60回/分）に包まれており、YouTube API クォータ消費の手動操作への厳格制限（仕様: 5回/分）を満たさない | 専用リミッター `channel-sync` を追加、5回/分で独立適用 |
| **スペック vs 実装の差異**: 書き込み操作の制限値 | spec 008 では 30回/分を定義したが、実装は 60回/分。タイムスタンプメモを連続保存するユースケースでは 30回/分は過剰に厳しい | 既存 60回/分をそのまま維持。spec の SC-002 はそれぞれの役割で達成する |

### 判断
- Decision: `channel-sync` リミッター（5回/分）を新設し、`fetchOlder` にのみ適用する。既存 `memo-mutations`（60回/分）・`oshi-mutations`（60回/分）は変更しない。
- Rationale: ユーザーが連続でメモを保存するシナリオ（ライブ配信中の速報メモ）では 30回/分は過剰制限となる。YouTube API クォータを直接消費する `fetchOlder` のみに厳格制限を適用するのが適切。
- Alternatives considered: 全書き込みを 30回/分に統一する案 → ユーザー体験の劣化を招くため却下。

---

## 3. DB インデックス現状と差分

仕様書 §18.2 の推奨インデックス（列名は実際の DB スキーマに合わせて補正）:

| 推奨インデックス（仕様書） | 実際の列名 | 存在 |
|--------------------------|-----------|------|
| `youtube_videos(youtube_channel_id, published_at)` | 同じ | ✅ |
| `user_watch_items(user_id, status)` | `(profile_id, status)` | ✅ |
| `user_watch_items(user_id, updated_at)` | `(profile_id, updated_at)` | ❌ 未作成 |
| `timestamp_memos(user_id, youtube_video_id, seconds)` | `(profile_id, youtube_video_id, seconds)` | ✅ |
| `user_channels(user_id, is_sync_enabled)` | `(profile_id, sync_enabled)` | ❌ 未作成 |

**追加が必要なインデックス** 2件:
1. `user_watch_items(profile_id, updated_at)` — 神回タブのソート（`updated_at DESC`）に使用
2. `user_channels(profile_id, sync_enabled)` — 同期対象チャンネル抽出 Job に使用

### 判断
- Decision: 2 件のインデックスを追加マイグレーションとして作成する。
- Rationale: 特に `user_watch_items(profile_id, updated_at)` は神回一覧の主ソートキーであり、データ増加時の応答速度に直結する。
- Alternatives considered: 実際の遅延が確認されてから追加する案 → 後付けは本番データへの影響が大きいため事前対応が望ましい。

---

## 4. エラーページの現状

`resources/views/errors/` ディレクトリは存在しない。カスタムエラービューは未実装。

### 判断
- Decision: `resources/views/errors/404.blade.php` と `errors/500.blade.php` を新設する。`layouts/app.blade.php` を継承し、CLAUDE.md のデザインガイドラインに沿ったデザインを適用する。
- Rationale: Laravel は `resources/views/errors/{code}.blade.php` を自動的にエラー画面として使用する。ホームへのリンクを含む最小限の構成で十分。
- Alternatives considered: 認証不要の独立レイアウトを使う案 → 未認証時のエラーに `auth.supabase` ミドルウェアが干渉する可能性があるため、エラービューは `layouts/minimal.blade.php`（新設）を継承させる。

---

## 5. テストカバレッジ調査

### 既存カバレッジ（確認済み）

| テストシナリオ | ファイル | 状態 |
|------------|-------|------|
| 認証済みルートへの未認証アクセス → リダイレクト | `Auth/ProtectedRouteTest.php` | ✅ |
| 他ユーザーの推しへのアクセス → 403 | `Oshi/OshiOwnershipTest.php` | ✅ |
| 他ユーザーのチャンネルへのアクセス → 403 | `Channel/ChannelOwnershipTest.php` | ✅ |
| 他ユーザーのタイムスタンプメモへのアクセス → 403 | `Memo/TimestampMemoOwnershipTest.php` | ✅ |
| 他ユーザーの再生位置更新 → 403 | `Playback/PlaybackOwnershipTest.php` | ✅ |
| YouTube API モック → 同期動作確認 | `Sync/InitialSyncTest.php` 他 | ✅ |

### 不足しているテスト（追加対象）

| テストシナリオ | 追加ファイル | 対応 FR |
|------------|-----------|--------|
| `fetchOlder` が 5回/分超過で 429 → | `Hardening/RateLimitTest.php` | FR-015 |
| YouTube API エラー時も一覧・メモ閲覧 OK | `Hardening/ApiFallbackTest.php` | FR-016 |
| 主要フロー（一覧→詳細→メモ→神回）を通じた E2E 相当 | `Hardening/MainFlowTest.php` | FR-014 |

### 判断
- Decision: `tests/Feature/Hardening/` ディレクトリに 3 ファイルを追加する。
- Rationale: 既存テストで所有権チェックは十分カバーされているが、レート制限・フォールバック・フロー横断テストは未実装。
- Alternatives considered: ブラウザ自動化（Dusk）による真の E2E テスト → セットアップコストが高く CI 安定性が低いため、`Http::fake()` ベースの Feature テストで代替する。

---

## 6. 規約ページの現状

`/privacy`・`/terms` のルートおよびビューは未実装。フッターにもリンクなし。

### 判断
- Decision: 公開ルート（認証不要）として `/privacy`・`/terms` を追加し、`resources/views/legal/` 以下にビューを新設する。フッターへのリンクも `layouts/app.blade.php` に追加する。
- Rationale: §17 の公開前完了条件。最小限のドラフト文言（構造のみ）で公開し、後日法的レビュー後に内容を更新する。
- Alternatives considered: 外部 URL（Notion 等）へのリダイレクト → アプリ内デザインの一貫性を損なうため却下。

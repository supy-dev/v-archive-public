# Research: プレイヤーと再生進捗管理 (Feature 005)

## Decision 1 — YouTube IFrame Player API ロード戦略

**Decision**: CDN スクリプトタグ `<script src="https://www.youtube.com/iframe_api">` を配信詳細ページの Blade ビューに非同期で読み込む。Alpine.js コンポーネントが `onYouTubeIframeAPIReady` グローバルコールバック経由でプレイヤーを初期化する。

**Rationale**:
- YouTube 公式ドキュメントの推奨方法。iframe src 直接埋め込みと違い、`onStateChange` 等のイベントハンドラを JS で登録できる（FR-005, FR-006, FR-008 を満たすために必須）。
- Alpine.js は `x-data` ディレクティブで定義したコンポーネントから `YT.Player` インスタンスを管理する。コンポーネント外の `onYouTubeIframeAPIReady` から `player` プロパティへの参照は Alpine の `$dispatch` または直接代入で受け渡す。
- React などの SPA ランタイムは追加しない（憲法 技術制約）。

**Alternatives considered**:
- `<iframe>` の `src` に `?enablejsapi=1` を付与して直接埋め込む → `onStateChange` が登録できず PLAYING/PAUSED/ENDED イベントの検知が不可能なため却下。
- lite-youtube-embed などのライブラリ → 既存スタックに新ライブラリを追加したくないため却下。

---

## Decision 2 — 再生位置上書き防止の実装方法（FR-011）

**Decision**: サーバー側で PHP レベルの比較（`$item->last_position_seconds < $newPosition || is_null($item->last_position_seconds)`）を行い、条件を満たす場合のみ `UPDATE` を実行する。Eloquent の `update()` 呼び出し前にこの比較を Action 内で行う。動画終了（`is_ended: true`）時はこの比較を **スキップし** 常に保存する（最終位置として確定させるため）。

**Rationale**:
- 楽観的ロック（バージョンカラム追加）より単純で、新規カラムが不要。
- 「古いタブのリクエストが新しい再生位置を上書き」するケースは、タブ A で 300秒まで視聴後にタブ B で 100秒を保存しようとするパターン。`new < current` で弾ける。
- PostgreSQL の行ロックを取らずに済むため同時書き込み負荷も低い。競合が発生しても「より新しい位置」が残る。

**Alternatives considered**:
- タイムスタンプ比較（`updated_at < now()`）: サーバー時刻とクライアント時刻のずれが生じうるため rejected。
- 楽観的ロック（`version` カラム）: 衝突時 409 を返す設計が必要になり実装が複雑。MV では不要。

---

## Decision 3 — PATCH API ペイロード設計

**Decision**: `{ last_position_seconds: N, is_ended: bool }` の 2 フィールドのみ。クライアントタイムスタンプは含まない。

**Rationale**:
- `is_ended: true` の場合、サーバーは status を `watched`・`watched_at` を現在日時に設定する（FR-008）。
- `is_ended: false` の場合、サーバーは `want_to_watch` → `watching` 遷移を試みる（FR-004）。
- クライアントタイムスタンプを持ち込まないことでサーバー時刻が単一の真実点になり、改ざんリスクを排除できる（憲法 IV）。

**Alternatives considered**:
- `event: "periodic" | "pause" | "ended"` の enum フィールド: is_ended でサーバー側の動作は十分に分岐できるため不要。
- `started_at` をクライアントから送る: サーバー側で `started_at IS NULL` の場合のみ設定するため不要。

---

## Decision 4 — ステータス自動遷移ロジック（FR-004 / FR-008 / FR-010 / 仕様 Q1）

**Decision**: `UpdatePlaybackPositionAction` にステータス遷移ロジックを集約する。

遷移ルール（優先度順）:

| 現在ステータス | is_ended | 遷移後 | 根拠 |
|--------------|----------|--------|------|
| want_to_watch | false | watching（started_at 未設定時のみ設定） | FR-004 |
| watching / watched / skipped | false | 変化なし | FR-004 / 仕様 Q1 |
| want_to_watch / watching | true | watched（watched_at = now()） | FR-008 |
| watched | true | 変化なし（watched_at も上書きしない） | FR-008（冪等） |
| skipped | true | watched（watched_at = now()） | FR-008（完全視聴を優先） |

**Rationale**:
- `skipped → watched` on ended: スキップ設定後でも最後まで視聴した事実を上書きする（ユーザーが最後まで再生したのだから watched が正確）。play start の `once skipped, stays skipped` とは区別する（仕様 Q1 の適用範囲は play start に限定）。
- `watched → watched` on ended: 冪等。`watched_at` を上書きしない（初回視聴完了日時を保持）。

---

## Decision 5 — レートリミット設定（FR-017 / 仕様 Q5）

**Decision**: `RateLimiter::for('playback-position', ...)` を `AppServiceProvider::boot()` に定義し、ユーザー単位で **10 req/分** に制限する。

```php
RateLimiter::for('playback-position', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()?->id ?? $request->ip());
});
```

ルートに `throttle:playback-position` を適用する。既存の `throttle:60,1` グループ（認証済みルート全般）とは独立した上位制限として機能させる。

**Rationale**:
- 60秒間隔保存 ＋ 一時停止・離脱保存を合わせても通常 3〜5 req/分程度。10 req/分 なら余裕あり。
- 憲法「認証済みルートおよび手動同期にレート制限を設定する」に準拠。
- 429 レスポンスはクライアントが静かに無視できる（次の保存で再試行）。

---

## Decision 6 — Alpine.js プレイヤーコンポーネント設計

**Decision**: `youtubePlayer` Alpine コンポーネントを `show.blade.php` にインラインで定義する。`x-data="youtubePlayer({...})"` 形式でパラメータを Blade から渡す。

主要ロジック:
- `init()`: YouTube API のロード完了を待って `YT.Player` を生成。`pagehide` リスナーを登録。Alpine の `$cleanup` でタイマー解除。
- `onStateChange(event)`: PLAYING(1) → 60秒タイマー開始。PAUSED(2) → 即時保存。ENDED(0) → 終了保存。
- `periodicSave()`: `setInterval` 60000ms コールバック。前回保存位置との差が 5秒未満なら送信しない（FR-005）。
- `savePosition(isEnded, keepalive)`: `fetch` で PATCH 送信。`keepalive: true` は `pagehide` 時のみ（FR-007）。
- `seekTo(seconds)`: 「アプリ内で続きから再生」ボタンから呼び出し（FR-012）。

**Alternatives considered**:
- 別の JS ファイルに分離する: Vite バンドルへの追加が必要で、単一ページ向け機能のためインラインで十分。
- Livewire: 既存スタックに追加しない。Alpine.js + fetch で完結できる。

---

## Decision 7 — 配信詳細ページのビュー構成（FR-014 / 仕様 Q2）

**Decision**: `resources/views/archives/show.blade.php` を新規作成。既存 `layouts/app.blade.php` を継承。レイアウトは上下スタック（上: プレイヤー、下: 動画情報・操作UI）。デスクトップでは `max-w-4xl mx-auto` 相当でプレイヤーを中央寄せ。

セクション構成:
1. **プレイヤーセクション**: 16:9 ラッパー + `div#yt-player`（Alpine 管理）
2. **動画情報セクション**: タイトル・チャンネル名・投稿日・再生時間・ステータスバッジ
3. **操作セクション**: 「アプリ内で続きから再生」「YouTubeで続きから開く」「ステータス変更UI」
4. **メモプレースホルダー**: Feature 6 向けの UI ガイド（保存ロジックなし）

**Rationale**:
- CLAUDE.md のデザインガイドライン（上下スタック、プレイヤー 16:9、モバイル 760px 以下は全幅）に準拠。
- 既存 `<x-archive-card>` / `<x-icon>` / `<x-section-title>` コンポーネントを再利用。

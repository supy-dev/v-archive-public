# Implementation Plan: プレイヤーと再生進捗管理

**Branch**: `005-player-and-playback` | **Date**: 2026-06-20 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/005-player-and-playback/spec.md`

## Summary

Feature 4 で構築した `user_watch_items` を活用し、配信詳細ページ（`/archives/{watchItem}`）での YouTube IFrame 再生・再生位置自動保存・ステータス自動遷移を実装する。新規テーブルは追加せず、`PlaybackPositionController` / `UpdatePlaybackPositionAction` / Alpine.js コンポーネントの 3 層で実現する。技術的な詳細は [research.md](research.md) を参照。

## Technical Context

**Language/Version**: PHP 8.4 / Laravel 12

**Primary Dependencies**: Blade、Alpine.js、YouTube IFrame Player API（CDN）、Eloquent ORM、Laravel RateLimiter

**Storage**: PostgreSQL 16（本番: Supabase PostgreSQL）— 新規テーブルなし

**Testing**: Pest / PHPUnit（Feature テスト中心）

**Target Platform**: Linux サーバ（Docker Compose ローカル / Supabase 本番）

**Project Type**: Web アプリケーション（Laravel モノリス + Blade テンプレート）

**Performance Goals**:
- SC-001: 再読み込み後、前回再生位置の±10秒以内から再生開始
- SC-002: 定期保存リクエストが 60 秒間隔（±5秒）、毎秒リクエストは発生しない

**Constraints**:
- YouTube IFrame API は CDN 読み込み（追加 npm パッケージ禁止）
- Alpine.js + Vanilla JS のみ（React/Vue 追加禁止、憲法 技術制約）
- 再生位置の毎秒保存禁止（FR-005）
- 他ユーザーの watch_item への操作は Policy で 403（憲法 III）
- 新規テーブル・マイグレーション不要（既存カラムで全要件を満たす）

**Scale/Scope**:
- 1 ユーザーあたり数百〜数千件の watch_item を想定
- 再生位置 PATCH: 1 動画視聴あたり最大 10 req/分（レートリミット上限）

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| 原則 | 評価 | 対応 |
|------|------|------|
| **I. 薄い Controller** | ✅ | `PlaybackPositionController` は FormRequest → Policy → Action → 204 のみ。`ArchiveController::show()` も同様 |
| **II. 共有マスタ分離** | ✅ | `youtube_videos` は読み取りのみ。ユーザー固有状態は `user_watch_items` のみ更新 |
| **III. 所有権認可** | ✅ | `UserWatchItemPolicy::view/update` を GET/PATCH の両エンドポイントで適用。他ユーザーは 403 |
| **IV. シークレット管理** | ✅ | YouTube IFrame API は公開API。CSRF トークンは `meta[name="csrf-token"]` 経由。秘密情報なし |
| **V. YT クォータ** | ✅ | IFrame API はクライアントサイドで動作し、YouTube Data API クォータを消費しない |
| **VI. テストファースト** | ✅ | 再生位置保存・所有権保護・ステータス遷移（watch/skip 含む）を Feature テストで担保 |
| **VII. 進捗データの正直表示** | ✅ | `last_position_seconds` は本サービス独自管理。YouTube 公式との同期なし。毎秒保存しない |

**Constitution Check 結果**: ✅ 全原則クリア。`Complexity Tracking` 記入不要。

## Project Structure

### Documentation (this feature)

```text
specs/005-player-and-playback/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   └── routes.md        # Phase 1 output
└── tasks.md             # Phase 2 output (/speckit-tasks で生成)
```

### Source Code (repository root)

```text
app/
├── Actions/
│   └── WatchItem/
│       ├── UpdatePlaybackPositionAction.php   # [新規] 再生位置保存 + ステータス自動遷移
│       ├── AddToWatchListAction.php           # [既存]
│       ├── DeleteWatchItemAction.php          # [既存]
│       └── UpdateWatchStatusAction.php        # [既存]
├── Http/
│   ├── Controllers/
│   │   ├── ArchiveController.php             # [変更] show() を追加
│   │   ├── PlaybackPositionController.php    # [新規] PATCH /watch-items/{watchItem}/position
│   │   └── (その他既存)
│   └── Requests/
│       ├── UpdatePlaybackPositionRequest.php # [新規] last_position_seconds / is_ended バリデーション
│       └── (その他既存)
├── Providers/
│   └── AppServiceProvider.php               # [変更] RateLimiter::for('playback-position') 追加
└── Policies/
    └── UserWatchItemPolicy.php              # [既存] view() は追加済み確認要

resources/
└── views/
    └── archives/
        └── show.blade.php                   # [新規] 配信詳細ページ（Alpine.js プレイヤー含む）

routes/
└── web.php                                  # [変更] 2 ルート追加

tests/
└── Feature/
    └── Playback/
        ├── ArchiveShowTest.php              # [新規] 詳細ページ表示・認可
        ├── PlaybackPositionTest.php         # [新規] 再生位置保存・ステータス遷移
        └── PlaybackOwnershipTest.php        # [新規] 所有権（SC-004）
```

**Structure Decision**: Laravel 標準の単一プロジェクト構成。Feature 4 のディレクトリレイアウトを踏襲し、`Actions/WatchItem/` に Action を追加、`Feature/Playback/` に新規テストディレクトリを作成する。

## 実装の重要ポイント

### UpdatePlaybackPositionAction の状態遷移ロジック

```
is_ended = false:
  status == want_to_watch → status = watching, started_at = now()（started_at が null の場合のみ）
  status == watching / watched / skipped → 変化なし（FR-010 / 仕様 Q1）
  last_position_seconds: new > current（または current が null）の場合のみ更新

is_ended = true:
  status != watched → status = watched, watched_at = now()
  last_position_seconds: 常に保存（終了時は上書き防止なし）
```

### RateLimiter 設定（AppServiceProvider）

```php
RateLimiter::for('playback-position', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()?->id ?? $request->ip());
});
```

### YouTube IFrame API の初期化パターン（show.blade.php インライン）

```js
// Alpine コンポーネント youtubePlayer({ watchItemId, videoId, startSeconds, positionUrl })
// onYouTubeIframeAPIReady グローバルコールバックで init
// pagehide: keepalive: true で fetch
// PLAYING: 60s タイマー開始
// PAUSED: 即時 fetch
// ENDED: is_ended: true で fetch
```

### バリデーション（UpdatePlaybackPositionRequest）

```php
$duration = $this->route('watchItem')->youtubeVideo->duration_seconds;
return [
    'last_position_seconds' => ['required', 'integer', 'min:0', ...($duration ? ["max:{$duration}"] : [])],
    'is_ended'              => ['required', 'boolean'],
];
```

### UserWatchItemPolicy への view() 確認

Feature 4 実装済みの `UserWatchItemPolicy::view()` が存在することを確認。存在しない場合は追加する（`$item->profile_id === $profile->id` のみ）。

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

（記入不要 — 全原則クリア）

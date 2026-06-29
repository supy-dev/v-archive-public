# Research: アーカイブ閲覧と見るリスト管理 (Feature 004)

## Decision 1 — `user_watch_items` テーブル設計

**Decision**: UUID主キー + `(profile_id, youtube_video_id)` 複合ユニーク制約を持つ専用テーブルを作成する。`status` はカラム制約と PHP Enum で二重に保護する。

**Rationale**:
- 憲法 II: ユーザー固有状態は共有マスタ（`youtube_videos`）に載せず別テーブルで管理する。
- 憲法 III: `profile_id` の FK で所有権を明示し、Policyで強制する。
- FR-007: 同一ユーザーの同一動画への重複作成を DB レベルで防ぐ。

**Alternatives considered**:
- `youtube_videos` にステータスカラムを追加する → 共有マスタ汚染（憲法 II 違反）で却下。
- integer PK → 既存テーブルはすべて UUID のため一貫性のために却下。

---

## Decision 2 — WatchStatus PHP Enum の扱い

**Decision**: `App\Enums\WatchStatus` として Backed Enum（string）を実装し、DB には `VARCHAR(20)` + CHECK 制約を設ける。4値: `want_to_watch`, `watching`, `watched`, `skipped`。

**Rationale**:
- 憲法 III: 「列挙値は Enum/Value Object で扱い、任意文字列を保存しない」に準拠。
- Feature 005 の `watching` 自動設定との互換性を保つため4値すべてを今回定義する。
- CHECK 制約により、PHP レイヤーを迂回した書き込みも防げる。

**Alternatives considered**:
- Integer Enum → 可読性が低くラベル管理が煩雑なため却下。
- PostgreSQL `CREATE TYPE` enum → マイグレーションで値の追加変更が難しくなるため string+CHECK で代替。

---

## Decision 3 — 新着アーカイブ一覧クエリ戦略

**Decision**: `youtube_videos` に対し `user_channels` 経由でユーザーのチャンネルを LEFT JOIN し、`user_watch_items` を LEFT JOIN して `user_watch_items.id IS NULL`（未整理）でフィルタ。`published_at DESC`、`paginate(20)` を使用。

**Rationale**:
- FR-002: 未整理（`user_watch_items` が存在しない）のみ初期表示。
- FR-011: `is_available = true` を WHERE に含める。
- FR-012: `paginate(20)` でオフセットページネーション。
- 憲法 技術制約: N+1 回避のため `with(['youtubeChannel.userChannels.oshi'])` で eager load。
- `youtube_videos` の `(youtube_channel_id, published_at)` インデックスが既に存在しクエリをカバーする。

**SQL sketch**:
```sql
SELECT yv.*, uc.oshi_id
FROM youtube_videos yv
INNER JOIN youtube_channels yc ON yc.id = yv.youtube_channel_id
INNER JOIN user_channels uc ON uc.youtube_channel_id = yc.id AND uc.profile_id = :profile_id
LEFT JOIN user_watch_items uwi ON uwi.youtube_video_id = yv.id AND uwi.profile_id = :profile_id
WHERE yv.is_available = true
  AND uwi.id IS NULL          -- 未整理のみ
  AND (oshi filter if set)
  AND (video_type filter if set)
ORDER BY yv.published_at DESC
LIMIT 20 OFFSET :offset
```

**Alternatives considered**:
- カーソルベースページネーション → `published_at` の重複が起きうるため `(published_at, id)` の複合カーソルが必要。MVPでは実装コストを抑えオフセットを選択。
- `whereDoesntHave` Eloquent ヘルパ → 内部でサブクエリになり大量データでは遅くなりうる。LEFT JOIN IS NULL パターンをクエリスコープとして実装する。

---

## Decision 4 — ステータス変更のタイムスタンプ自動設定

**Decision**: `UpdateWatchStatusAction` 内でステータスごとに対応タイムスタンプをセットする。ステータス遷移を受け取り `UserWatchItem` を更新するシンプルな Action クラス。

| ステータス       | 更新されるタイムスタンプ |
|-----------------|----------------------|
| `want_to_watch` | （なし）              |
| `watching`      | `started_at`         |
| `watched`       | `watched_at`         |
| `skipped`       | `skipped_at`         |

**Rationale**:
- FR-008: タイムスタンプ自動設定の要件を Action に集約し、Controller を薄くする（憲法 I）。
- `watching` タイムスタンプも今回定義しておくことで Feature 005 の追加コストを下げる。

---

## Decision 5 — ホーム画面「未整理件数」の算出

**Decision**: 未整理件数 = ユーザーの登録チャンネルに紐づく `is_available=true` の `youtube_videos` 数 − そのユーザーの `user_watch_items` 数（全ステータス）。実装は COUNT サブクエリを2本発行する（ユーザー当り2クエリ、N+1 なし）。

**Rationale**:
- FR-010: 「全登録チャンネル・全期間」での未整理件数を正確に返す。
- `homeStats()` メソッドを `ProfileStatsService` または `HomeController` のプライベートメソッドで集約し、View に渡す。

---

## Decision 6 — ルーティングと Controller 設計

| ルート | Controller | Action |
|--------|-----------|--------|
| `GET /archive` | `ArchiveController::index` | 新着アーカイブ一覧 |
| `POST /archive/{video}/watch-item` | `UserWatchItemController::store` | 見るリスト追加・スキップ |
| `GET /watchlist` | `UserWatchItemController::index` | 見るリスト（タブ付き） |
| `PATCH /watchlist/{userWatchItem}` | `UserWatchItemController::update` | ステータス変更 |
| `DELETE /watchlist/{userWatchItem}` | `UserWatchItemController::destroy` | 削除（未整理に戻す） |

**Rationale**:
- `UserWatchItem` はリソースとして URL に現れる（RESTful）。
- `ArchiveController` は閲覧専用でフィルタ用クエリパラメータを受け取る（推し・video_type）。
- 薄い Controller: FormRequest で入力検証 → Action 呼び出し → リダイレクト/JSON（憲法 I）。

---

## Decision 7 — フロント操作（Alpine.js の役割）

**Decision**: 見るリストのタブ切替は URL クエリパラメータ（`?status=want_to_watch` 等）経由でサーバサイドレンダリング。楽観的 UI は MVP では採用せず、サーバ応答後にリダイレクトで画面更新。アーカイブ一覧のフィルタは Alpine.js で `<form>` を自動サブミット。

**Rationale**:
- サーバサイドレンダリングによりブックマーク・戻るボタンが自然に動く。
- Alpine.js の `x-data` で最小限のインタラクション（ドロップダウン開閉、フィルタ変更時の自動 submit）に留め、別 SPA ランタイムを追加しない（憲法 技術制約）。
- SC-003（2秒以内応答）: 楽観的 UI なしでも POST → redirect パターンなら応答は1〜2リクエストで完結する。

---

## Decision 8 — Policy / Authorization

**Decision**: `UserWatchItemPolicy` を新規作成し `view`、`create`（youtube_video_id を渡す）、`update`、`delete` を実装する。`create` では動画のチャンネルがユーザーのいずれかの `user_channels` に含まれることも確認する（他ユーザーチャンネルの動画への不正登録防止）。

**Rationale**:
- 憲法 III: Policy による所有権確認は MUST。
- FR-009: 他ユーザーの `user_watch_items` を操作させない。
- `ArchiveController::index` では Gate ではなくクエリレベルで所有権を強制（profile_id フィルタ）。

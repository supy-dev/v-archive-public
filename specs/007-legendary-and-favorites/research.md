# Research: 神回登録・神回お気に入りページ改修・タイムスタンプメモ保管庫の新設

## タブ切り替え方式

**Decision**: サーバーサイド GET パラメータ（`?tab=kamikai` / `?tab=memos`）

**Rationale**:
- 既存の `/favorites` フィルタ（`?oshi_id=&tag_id=&month=`）と同方式で、コード規約を壊さない。
- ブックマーク・ブラウザ履歴での再現（FR-010）を自然に満たす。
- Alpine.js の `pushState` によるクライアント側 URL 書き換えは SPA 化に近く、憲法「完全 SPA 化しない」と相反する。
- タブごとに異なるクエリが走るため、クライアント側で両方のデータを持つより DB 負荷が小さい。

**Alternatives considered**:
- Alpine.js クライアントタブ（×SPA 化、×FR-010 対応にプッシュステート必要）
- Livewire（×本プロジェクトに導入なし）

---

## 神回トグルエンドポイントのレート制限

**Decision**: 既存の `memo-mutations` グループへ追加

**Rationale**:
- `PATCH /archives/{watchItem}/favorite` は 1 回あたりの処理コストが `PATCH /archives/{watchItem}/memos/{memo}/favorite` と同等。
- 新グループ作成はスロットル定義の肥大化を招く。既存グループで十分。

---

## /memos ページネーション件数

**Decision**: `paginate(20)` — `/favorites` と統一

**Rationale**:
- 1 ページあたりの表示件数を揃えることで、将来的な共通レイアウト化が容易になる。
- メモカードは `/favorites` のメモカードと同構造のため、同一件数が自然。

---

## 神回動画タブの「年月」フィルタ基準

**Decision**: `user_watch_items.updated_at`（神回登録日）

**Rationale**:
- 「この月に神回登録した動画を振り返る」というユーザーの意図に最も合致する。
- `youtube_videos.published_at`（配信日）では「あのとき見た動画」ではなく「あのとき配信された動画」になる。
- `user_watch_items.updated_at` は `is_favorite` トグル時に自動更新される。

**Alternatives considered**:
- `youtube_videos.published_at`（×ユーザーの「いつ感動したか」とずれる）
- 専用の `favorited_at` カラム追加（×マイグレーション不要の制約に反する）

---

## /memos Controller の責務分担

**Decision**: 新規 `MemoController`（`app/Http/Controllers/MemoController.php`）を作成

**Rationale**:
- `FavoriteController` は `/favorites` の2タブ表示へ改修するため、全メモ一覧ロジックを別 Controller に分離する。
- メモ CRUD は既存の `TimestampMemoController`（`/archives/{watchItem}/memos`）が担うため名称の衝突なし。
- `MemoController` は GET のみで、書き込み操作を持たない（閲覧専用、FR-017）。

---

## /favorites Controller 刷新方針

**Decision**: 既存 `FavoriteController::index()` を拡張し、`?tab=` を分岐処理

**Rationale**:
- ルート名（`favorites.index`）・URL（`/favorites`）を変更しないため、サイドバー・配信詳細の既存リンクを壊さない。
- タブ別に異なるクエリを実行し、アクティブタブのデータのみ取得する（不要なクエリを避ける）。

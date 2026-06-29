# Phase 1 Data Model: Foundation & Authentication

本フィーチャーで導入するのはユーザーデータの `profiles` のみ。認証主体（auth.users）は Supabase 管理であり、
アプリのDBには複製しない（憲法 II / IV）。

## Entity: Profile（`profiles`）

サービス内のユーザー表現。`id` は Supabase の `auth.users.id` と同一の UUID。今後のすべてのユーザー固有
データ（推し・動画・メモ等）の所有者となる。Authenticatable として用いる。

| 列 | 型 | 制約/既定 | 説明 |
|---|---|---|---|
| id | uuid | primary key | Supabase `auth.users.id` と同一（`sub`） |
| display_name | varchar(255) | not null | 表示名。初期値は claims 由来（後述） |
| avatar_url | text | nullable | アイコンURL（Google等の `picture` claim 由来） |
| timezone | varchar(64) | not null, default `Asia/Tokyo` | 表示用タイムゾーン |
| created_at | timestamptz | not null | |
| updated_at | timestamptz | not null | |

- **主キー方針**: `id` はアプリ側で採番せず、検証済み JWT の `sub`(UUID) をそのまま用いる。
- **一意性**: `id` が一意（=Supabaseユーザーと1:1）。同一メールの複数identityはSupabase側で同一 `sub` に
  リンクされるため、`profiles` は重複しない（FR-005a / SC-010）。
- **インデックス**: 主キーのみ（本フィーチャー範囲では十分）。

### バリデーション/ルール（spec 要件との対応）

- FR-005 / FR-006: 初回ログイン時に `id=sub` で **冪等 upsert**（存在すれば作成しない）。
- display_name 初期値の導出（informed default）:
  1. claims の `name` / `user_metadata.full_name` があれば使用
  2. なければ `email` のローカル部（@より前）
  3. それも無ければ `"ユーザー"`（フォールバック）
- timezone 既定は `Asia/Tokyo`（spec Assumptions）。
- avatar_url は `picture` claim があれば設定、なければ null。

### 状態遷移

`profiles` 自体に明示的な状態機械はない。認証状態（未確認/確認済み・ログイン中/ログアウト）は Supabase 側の
ユーザー状態と Laravel セッションの有無で表現し、アプリDBには保持しない。

## 認証関連の非永続オブジェクト

DBには保存しないが設計上の中心となる値。

### VerifiedClaims（値オブジェクト）

JWT 検証成功後に生成。`sub`(UUID), `email`, `email_verified`(bool), `name?`, `picture?`, `iss`, `aud`, `exp` を保持。
`SyncProfileFromClaimsAction` がこれを入力に `profiles` を upsert する。

## Supabase 側（参考・アプリDB外）

| オブジェクト | 管理 | 備考 |
|---|---|---|
| `auth.users` | Supabase | パスワードハッシュ・メール確認状態・identity を保持。アプリは複製しない |
| identities（google / email） | Supabase | 同一確認済みメールは同一 `auth.users.id` にリンク |

## 既存スキーマへの影響

- Laravel 標準の `users` テーブルマイグレーションは `profiles`（UUID）へ置換する（research §3）。
- `password_reset_tokens` 等のLaravelパスワードブローカはSupabaseに委譲するため使用しない。
- `sessions` テーブルは Laravel セッション（database ドライバ）で使用。`user_id` は UUID(string) を許容する形にする。

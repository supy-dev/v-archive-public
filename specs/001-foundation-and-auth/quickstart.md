# Quickstart & Validation: Foundation & Authentication

本フィーチャーがエンドツーエンドで成立することを検証するためのガイド。実装の本体は `tasks.md` / 実装フェーズで行う。

## 前提

- Docker / Docker Compose
- Supabase プロジェクト（無料枠可）
- Google OAuth クライアント（Supabase の Auth Providers に設定）

### Supabase 側の必須設定（research §4 / data-model 参照）

1. Auth > Providers > **Google** を有効化（Client ID/Secret、リダイレクトURL）。
2. Auth > Email: **Confirm email = ON**（メール確認必須）。
3. Auth > Policies/Security: **Minimum password length = 8**。
4. 同一メールの identity リンクを有効化（確認済みメール前提）。
5. JWKS/issuer 情報（プロジェクトURL、`/.well-known/jwks.json`）を控える。

## ローカルセットアップ

```bash
cp .env.example .env
# .env を編集:
#   DB_CONNECTION=pgsql / DB_HOST=db / DB_PORT=5432 / DB_DATABASE / DB_USERNAME / DB_PASSWORD
#   SUPABASE_URL=...           （サーバ専用）
#   SUPABASE_JWKS_URL=...       （/.well-known/jwks.json）
#   SUPABASE_JWT_AUD=authenticated
#   VITE_SUPABASE_URL=...       （フロント公開可の anon 情報のみ）
#   VITE_SUPABASE_ANON_KEY=...  （anon key のみ。service role key は絶対に置かない）

docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app npm install && docker compose exec app npm run build
```

> service role key / secret は `.env` のサーバ専用変数にのみ置き、`VITE_` プレフィックスを付けない（憲法 IV）。

## 検証シナリオ（spec の受け入れ基準に対応）

### S1: メール＋パスワード登録 → メール確認 → ログイン（US1 / FR-001a,d）
1. `/register` で8文字以上のパスワードで登録。
2. 確認前に `/` へアクセス → `/login` に留まり、確認を促す案内が出る（SC-009）。
3. 受信メールの確認リンクをクリック。
4. `/login` でログイン → `204` 後にホーム表示。`profiles` が1件作成されている（SC-004）。

### S2: Googleログイン（US1 / FR-001）
1. `/login` の Google ボタン → OAuth → 戻り。
2. `POST /auth/session` が成功しホーム表示。`profiles` の `display_name`/`avatar_url` が claims 由来で初期化。

### S3: 認証境界（US2 / FR-002,003 / SC-002）
1. 未ログインで `/` や `/profile` に直接アクセス → `/login` へリダイレクト。
2. 改ざん/期限切れトークンで `POST /auth/session` → `401`。

### S4: 所有権（US2 / FR-009 / SC-003）
1. ユーザーAでログインし `/profile` 表示 → 自分の情報のみ。
2. （テスト）別ユーザーのプロフィール参照を試行 → Policyで拒否。

### S5: パスワード再設定（US4 / FR-001b / SC-008）
1. `/forgot-password` で登録メールを申請 → 一様な案内（アカウント有無を漏らさない）。
2. 受信メールのリンク → `/reset-password` で新パスワード設定。
3. 新パスワードでログイン成功、旧パスワードは失敗。

### S6: ログアウト（US3 / FR-008 / SC-005）
1. ログイン状態で `DELETE /auth/session`。
2. `/` へアクセス → `/login`。ブラウザ戻るでも保護コンテンツ非表示。

### S7: アカウントリンク（FR-005a / SC-010）
1. 同一メールで Google とメール＋パスワード両方を利用 → 参照される `profiles` は常に同一1件。

## 自動テストでの検証（憲法 VI）

```bash
docker compose exec app ./vendor/bin/pest
```

- Feature: ログイン/登録/セッション確立/保護リダイレクト/ログアウト/プロフィール所有権。
- Unit: `JwksSupabaseJwtVerifier`（正常/期限切れ/issuer不一致/改ざん）、`SyncProfileFromClaimsAction`（冪等・display_name導出）。
- 外部（JWKS/GoTrue）は `Http::fake` とverifierのfakeで代替し、実APIを呼ばない。

## 完了の目安

spec の SC-001〜SC-010 を満たし、上記 S1〜S7 と Pest スイートがグリーンであること。

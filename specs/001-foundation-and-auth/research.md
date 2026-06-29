# Phase 0 Research: Foundation & Authentication

本フィーチャーの Technical Context 上の要解決事項を解消する。

## 1. Supabase Auth × Laravel(Blade) の統合方式

- **Decision**: ハイブリッド方式。認証UI（ログイン/登録/パスワード再設定/Google OAuth）は **Supabase JS
  クライアント**が担当し、成功後に得た access token を Laravel の `POST /auth/session` へ送る。Laravel は
  JWT を検証して `profiles` を upsert し、**Laravel独自セッション**を確立する。以降の保護ルートは
  Laravelセッションで認可する。
- **Rationale**: Blade モノリスを維持しつつ、確認メール送信・OAuthリダイレクト・再設定メールといった
  「メール/プロバイダ連携の難所」を Supabase に委譲できる。ページ表示のたびに外部APIを呼ばない（憲法V/性能）。
  ドメイン処理は Action/Service に閉じる（憲法I）。
- **Alternatives considered**:
  - サーバ側で GoTrue REST を直接叩く（Laravelがメール/パスワードを受け取り中継）: パスワードがアプリ層を
    通過し攻撃面と責務が増える。OAuthフローの実装も重い。→却下。
  - フルSPA + Supabaseセッションのみ（Laravelセッション無し）: 指示書の「完全SPA化しない」「Laravel
    セッション確立」に反する。→却下。

## 2. JWT 署名検証方式とライブラリ

- **Decision**: **JWKS による非対称署名検証**（Supabase の signing keys エンドポイント `/.well-known/jwks.json`
  を取得し、`kid` で鍵選択）。ライブラリは **`firebase/php-jwt`**。`iss`(Supabase URL の `/auth/v1`)、`exp`、
  `aud`(`authenticated`)、`sub`(UUID) を検証。JWKS はキャッシュ（TTL付き）し毎回取得しない。
- **Rationale**: 憲法「JWKSまたは公式推奨方式で署名検証」に直接適合。非対称方式は検証側に秘密鍵を置かずに
  済み、鍵ローテーションにJWKSで追従できる。`firebase/php-jwt` は軽量でJWKS対応。
- **Alternatives considered**:
  - 共有secret(HS256)で検証: 旧方式。検証サーバに対称鍵を保持する必要があり、Supabaseも非対称鍵へ移行中。→
    MVPでも非対称を採用。
  - `lcobucci/jwt`: 高機能だが設定が冗長。MVPでは `firebase/php-jwt` で十分。→却下。
- **Note**: 署名検証なしの decode は禁止（憲法III / 指示書§5.4）。

## 3. Authenticatable の実装

- **Decision**: `profiles`（`id` = UUID 主キー）を `Authenticatable` モデルとし、**専用の user provider と
  session guard** を登録する。Laravel 標準の `users` テーブルは廃し、`profiles` へ置換する。ログインは
  `Auth::login($profile)` 相当で確立する。
- **Rationale**: 指示書 §5.3「`profiles.id` = `auth.users.id`（同一UUID）」に一致。本人識別子をUUIDで統一でき、
  所有権（Policy）も `profiles.id` を基準にできる。
- **Alternatives considered**:
  - `users` を残し `profiles` を別持ち: 二重管理になり、どちらを authenticatable にするか曖昧。→却下。
  - bigint の代理キー: SupabaseのUUIDと不一致になり対応付けが複雑化。→却下。

## 4. メール確認・アカウントリンク・パスワード強度の実現箇所

- **Decision**: いずれも **Supabase プロジェクト設定**で担保し、アプリは結果（確認済みか等）を JWT claims/
  GoTrue 応答で受けて振る舞う。
  - メール確認必須: Supabase Auth の「Confirm email」を ON。未確認ユーザーのトークンでは保護機能を許可しない。
  - パスワード最小長: Supabase の Minimum password length = **8**。アプリ側でも登録/再設定フォームで8文字を
    クライアント検証し UX を補う。
  - アカウントリンク: 同一メールの identity を同一ユーザーへリンクする Supabase の挙動を利用（確認済みメール
    前提）。アプリは `sub` 単位で `profiles` を一意管理する。
- **Rationale**: 認証主体がSupabaseである以上、メール送信・確認状態・パスワードハッシュ化はSupabaseの責務。
  アプリにパスワードを保持させない（憲法IV）。`quickstart.md` に必要な設定を明記する。
- **Alternatives considered**: アプリ側で確認メール・ハッシュ化を自前実装 → 責務重複・セキュリティ面で不利。→却下。

## 5. ローカルDB を MySQL → PostgreSQL へ是正

- **Decision**: `docker-compose.yml` の `db` を `postgres:16`（Supabase互換系列）へ変更、`Dockerfile` を
  `pdo_pgsql`/`libpq-dev` 導入へ変更、`.env.example` を `DB_CONNECTION=pgsql`（host/port/db/user/password）へ更新。
  セッション/キャッシュ/キュー/メール（確認メールはSupabase送信のため `MAIL_MAILER=log` で可）。
- **Rationale**: 憲法・指示書がPostgreSQL(Supabase本番互換)を必須とするため、基盤段階で方言を一致させる。
- **Alternatives considered**: SQLite/MySQLのまま開発 → 本番Supabaseとの差異でマイグレーション/型/関数が
  食い違うリスク。→却下。
- **Note**: テストは `pgsql` を基本とし、CIでは Postgres サービスコンテナを使用。

## 6. テストでの認証スタブ方法

- **Decision**: 2層で対応。
  - **Feature テスト**: `actingAs($profile, 'web')` で保護ルート/所有権を検証。`/auth/session` 経路は
    `SupabaseJwtVerifier` を**コンテナ差し替え（fake実装）**して、署名検証を通さずに既知claimsを返す。
  - **Unit テスト**: `JwksSupabaseJwtVerifier` 自体は、`Http::fake` で JWKS を返し、テスト用鍵で署名した
    トークンを検証する（正常/期限切れ/issuer不一致/改ざんを網羅）。
- **Rationale**: 実Supabaseを呼ばずに認証境界・所有権・JWT検証ロジックを再現可能に検証（憲法VI / 指示書§19.3）。
- **Alternatives considered**: 実Supabase接続でE2E → 不安定・禁止事項。→却下。

## 確定した依存追加（composer / npm）

- composer: `firebase/php-jwt`
- npm: `@supabase/supabase-js`, `tailwindcss`, `@tailwindcss/vite`（または postcss）, `alpinejs`

すべての NEEDS CLARIFICATION は解消済み。

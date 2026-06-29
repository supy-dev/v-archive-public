# V-アーカイブ

VTuberや配信者の視聴者が「推し・動画・メモ」を自分専用に管理するための個人用Webアプリ。
Laravel モノリス + Blade + Alpine.js + Tailwind CSS で構築し、認証は Supabase Auth（Google）を使用。※メールアドレスは後日対応予定

> **このリポジトリについて**
> 実際に稼働しているサービスのソースコードです。学習目的でコードを公開しています。
> 開発の過程はブログにまとめています → **[開発ブログ（URL後日追加）](#)**

---

## 技術スタック

| 分類 | 採用技術 |
|------|----------|
| バックエンド | PHP 8.3 / Laravel 13 |
| フロントエンド | Blade テンプレート / Alpine.js / Tailwind CSS |
| 認証 | Supabase Auth（JWT → Laravel セッション） |
| DB | PostgreSQL 16（ローカル: Docker / 本番: Supabase） |
| テスト | Pest / PHPUnit |
| 静的解析 | Laravel Pint |

---

## ローカルセットアップ

**前提**: Docker / Docker Compose、Supabase プロジェクト（無料枠可）、Google OAuth クライアント

```bash
cp .env.example .env
```

`.env` を編集して以下を設定します:

```
# データベース（Dockerコンテナ）
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

# Supabase（サーバー専用 — VITE_ を付けない）
SUPABASE_URL=...
SUPABASE_JWKS_URL=...
SUPABASE_JWT_AUD=authenticated

# Supabase（ブラウザ公開可の anon key のみ）
VITE_SUPABASE_URL=...
VITE_SUPABASE_ANON_KEY=...
```

> **注意**: `service_role` キーは絶対に `VITE_` を付けず、サーバー専用変数にのみ置いてください。

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app npm install
docker compose exec app npm run build
```

Supabase 側の設定: Auth > Providers で Google を有効化、Email の Confirm email = ON、
Minimum password length = 8、同一メールの identity リンクを有効化。
詳細は `specs/001-foundation-and-auth/quickstart.md` を参照。

---

## テスト / 静的解析

```bash
# Feature/Unit テスト（外部 API はモック）
docker compose exec app ./vendor/bin/pest

# コーディング規約チェック
docker compose exec app ./vendor/bin/pint
```

---

## 開発ブログ

このアプリケーションの設計・実装・試行錯誤の過程は、学習者向けのブログ記事としてまとめています。

→ **開発ブログ（URL後日追加）**

コードを読む際のガイドとして、あわせてご参照ください。

---

## ライセンスと権利

Copyright &copy; 2025&ndash;2026 [supy-dev](https://github.com/supy-dev)

**All Rights Reserved.**

本リポジトリのソースコードは、個人の学習・参照目的に限り閲覧を許可します。
以下の行為はいかなる場合も禁止します:

- コードの複製・転載・再配布（一部または全部）
- 本コードをベースにしたサービスの構築・公開
- 商用目的での利用

フレームワークや依存ライブラリ（Laravel 等）はそれぞれのライセンスに従います。

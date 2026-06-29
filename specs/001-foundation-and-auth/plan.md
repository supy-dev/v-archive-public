# Implementation Plan: Foundation & Authentication

**Branch**: `001-foundation-and-auth` | **Date**: 2026-06-19 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-foundation-and-auth/spec.md`

## Summary

VTuberアーカイブ手帳の基盤と認証を提供する。ユーザーは Supabase Auth を介して (a) Googleログイン、
(b) メールアドレス＋パスワード登録/ログイン（メール確認必須・パスワード8文字以上・パスワード再設定）で
サインインできる。Laravel は Supabase が発行した JWT を JWKS で署名検証し、`sub`(UUID) を本人識別子として
`profiles` に対応付け、Laravel セッションを確立する。認証境界（middleware）と所有権（Policy）を最初から
成立させ、後続フィーチャーが安全に乗る土台を作る。あわせて Phase 0 の基盤整備（PostgreSQL化、Tailwind、
CI、規約、`.env.example`）を行う。

技術アプローチ: 認証UI（ログイン/登録/再設定）では Supabase JS クライアントが OAuth リダイレクト・確認
メール送信・パスワード再設定メールを担い、取得した access token を Laravel の `/auth/session` へ POST。
Laravel は JWT を検証し、`profiles` を冪等 upsert して独自セッションguardでログインさせる。ドメイン処理は
`Services/Auth` と `Actions` に分離し、Controller は薄く保つ（憲法 I）。

## Technical Context

**Language/Version**: PHP 8.3（`^8.3`）

**Primary Dependencies**: Laravel 13.8（`^13.8`）, Blade, Tailwind CSS + Vite, Alpine.js（補助）,
Supabase JS（認証UI）, JWT検証ライブラリ（`firebase/php-jwt` を JWKS で使用）, Laravel HTTP Client

**Storage**: PostgreSQL（ローカル=Docker Compose の Postgres、本番=Supabase PostgreSQL）。
セッション/キャッシュ/キューは database ドライバ。

**Testing**: Pest（`pestphp/pest`）+ PHPUnit 12 基盤。外部（Supabase JWKS / GoTrue）は `Http::fake` と
JWT検証サービスのモックで代替（憲法 VI）。

**Target Platform**: Webアプリ（PC・スマートフォンのレスポンシブ Blade）。Linux サーバ運用。

**Project Type**: Web（Laravel モノリス。完全SPA化しない）

**Performance Goals**: ログイン完了まで通常2分以内・3ステップ以内（SC-001）。一覧等の重い要件は本フィーチャー対象外。

**Constraints**: HTTPS 必須 / CSRF 有効 / トークン・鍵をログ・フロントへ出さない（憲法 IV）/
リクエストのユーザーIDを信用しない（憲法 III）/ パスワードを保持・ログ出力しない。

**Scale/Scope**: 個人開発のMVP。本フィーチャーの画面は ログイン・登録・パスワード再設定・ホーム（最小）・
プロフィール表示の5系統程度。

### 要解決事項（research.md で解消）

1. Supabase Auth と Laravel(Blade) の統合方式（クライアントSDK＋JWT検証 vs サーバREST）
2. JWT 署名検証方式（JWKS 非対称 vs 共有secret HS256）と採用ライブラリ
3. Authenticatable をどう実装するか（`profiles` をユーザープロバイダにする）
4. メール確認・アカウントリンク・パスワード強度の実現箇所（Supabase設定 vs アプリ）
5. ローカルDBを MySQL から PostgreSQL へ是正する手順
6. テストでの認証スタブ方法（`actingAs` と JWKS モック）

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| 原則 | 適合方針 | 状態 |
|---|---|---|
| I. 薄いController・レイヤード設計 | 認証処理を `Services/Auth/*`・`Actions/Auth/*` に分離。Controllerは検証→呼び出し→応答のみ | PASS |
| II. 共有マスタとユーザーデータの分離 | 本フィーチャーは `profiles`（ユーザーデータ）のみ。YouTube系マスタは未導入で原則に違反しない | PASS |
| III. 所有権ベースの認可と型安全 | UUID識別子、`ProfilePolicy`、本人識別はサーバセッションから取得、JWTは署名検証 | PASS |
| IV. シークレット・ハイジーン | service role key/secretはサーバ専用、トークン・パスワードを非ログ・非フロント、ログアウトで両セッション破棄 | PASS |
| V. YouTube連携・クォータ規律 | 本フィーチャーは該当なし | N/A |
| VI. テストファースト & 外部APIモック | Pestで認証・所有権をテスト、JWKS/GoTrueはモック、実APIを呼ばない | PASS |
| VII. 進捗データの自前管理 | 本フィーチャーは該当なし | N/A |

**初期判定: PASS**（違反なし）。なお既存スキャフォールドの MySQL→PostgreSQL 是正は原則違反ではなく
基盤整備タスクとして扱う（Complexity Tracking 参照）。

## Project Structure

### Documentation (this feature)

```text
specs/001-foundation-and-auth/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output（認証エンドポイント/ルート契約）
│   └── auth-endpoints.md
├── checklists/
│   └── requirements.md  # /speckit-specify 由来
└── tasks.md             # /speckit-tasks で生成（本コマンドでは作成しない）
```

### Source Code (repository root)

```text
app/
├── Actions/
│   └── Auth/
│       ├── EstablishSessionAction.php      # JWT検証成功後にセッション確立
│       └── SyncProfileFromClaimsAction.php # claims から profiles を冪等 upsert
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   ├── AuthSessionController.php    # POST /auth/session, DELETE /auth/session
│   │   │   └── AuthPageController.php       # ログイン/登録/再設定ページ表示
│   │   ├── HomeController.php               # 認証後ホーム（最小）
│   │   └── ProfileController.php            # 自分のプロフィール表示
│   ├── Middleware/
│   │   └── EnsureSupabaseSession.php        # auth.supabase: 認証必須ガード
│   └── Requests/
│       └── EstablishSessionRequest.php
├── Models/
│   └── Profile.php                          # Authenticatable, id=UUID
├── Policies/
│   └── ProfilePolicy.php
├── Providers/
│   └── AppServiceProvider.php               # guard/provider/policy 登録
└── Services/
    └── Auth/
        ├── SupabaseJwtVerifier.php          # interface
        ├── JwksSupabaseJwtVerifier.php      # JWKS実装
        └── VerifiedClaims.php               # 値オブジェクト

config/
└── supabase.php                             # url, jwks, issuer, audience（secretはenv）

database/migrations/
└── XXXX_create_profiles_table.php           # users移行/置換含む

resources/
├── css/app.css                              # Tailwind
├── js/
│   ├── app.js
│   └── auth/supabase-client.js              # Supabase JS ラッパ（login/register/reset）
└── views/
    ├── layouts/{app,guest}.blade.php
    ├── auth/{login,register,forgot-password,reset-password}.blade.php
    ├── home.blade.php
    └── profile/show.blade.php

routes/web.php                               # 認証ルート + auth.supabase グループ

tests/
├── Feature/Auth/{LoginTest,RegisterTest,SessionTest,LogoutTest,ProtectedRouteTest}.php
├── Feature/ProfileTest.php
└── Unit/Auth/{SupabaseJwtVerifierTest,SyncProfileActionTest}.php

docker/ , docker-compose.yml , Dockerfile     # PostgreSQL へ是正
```

**Structure Decision**: Laravel モノリス（Web）。`app/Services/Auth` と `app/Actions/Auth` に認証ドメインを
集約し、Blade + Tailwind + 最小JS（Supabase JS）でUIを構成。完全SPA化はしない（憲法・指示書 §4.2）。

## Complexity Tracking

> 憲法違反ではないが、基盤フィーチャーとして明示的に扱う是正項目。

| 項目 | 必要理由 | 採用しなかった代替 |
|---|---|---|
| docker-compose/Dockerfile を MySQL→PostgreSQL へ変更 | 憲法・指示書がPostgreSQL(Supabase互換)を必須。早期にDB方言を一致させないと後続移行/SQLが破綻 | MySQLのまま進める案は、本番Supabaseと方言差異が出るため却下 |
| `users` テーブルを `profiles`(UUID) へ置換し独自guard/providerを使用 | 認証主体がSupabaseでIDがUUID。Laravel標準のパスワードブローカ前提と噛み合わない | Laravel標準authをそのまま使う案は、二重の認証主体になり憲法III/IVと整合しないため却下 |

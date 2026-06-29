---
description: "Task list for Foundation & Authentication implementation"
---

# Tasks: Foundation & Authentication

**Input**: Design documents from `/specs/001-foundation-and-auth/`

**Prerequisites**: plan.md (required), spec.md (required), research.md, data-model.md, contracts/auth-endpoints.md, quickstart.md

**Tests**: INCLUDED. 憲法 VI（テストファースト＆外部APIモック）と quickstart.md が Pest による
検証を必須とするため、各ストーリーにテストタスクを含める。外部（Supabase JWKS / GoTrue）は
`Http::fake` と verifier の fake 差し替えで代替し、実APIは呼ばない。

**Organization**: タスクはユーザーストーリー単位でグループ化し、各ストーリーを独立して実装・テストできる。

## Format: `[ID] [P?] [Story] Description`

- **[P]**: 並列実行可能（別ファイル・未完了タスクへの依存なし）
- **[Story]**: 対応するユーザーストーリー（US1, US2, US3, US4）
- すべて実ファイルパスを明記

## Path Conventions

Laravel モノリス（plan.md の Project Structure に準拠）。アプリは `app/`、ビューは `resources/views/`、
テストは `tests/`、ルートは `routes/web.php`（いずれもリポジトリルート `/var/www` 基準）。

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: 基盤整備（PostgreSQL化・依存追加・CSS基盤・設定・CI）。Phase 0 の是正項目を含む。

- [X] T001 [P] `docker-compose.yml` の `db` サービスを `postgres:16` に是正し、`DB_*` 環境変数を Postgres 用に揃える（/var/www/docker-compose.yml）
- [X] T002 [P] `Dockerfile` に `pdo_pgsql` / `libpq-dev` を追加して PostgreSQL 接続可能にする（/var/www/Dockerfile）
- [X] T003 [P] `.env.example` を `DB_CONNECTION=pgsql` と Supabase 変数（`SUPABASE_URL`, `SUPABASE_JWKS_URL`, `SUPABASE_JWT_AUD`, `VITE_SUPABASE_URL`, `VITE_SUPABASE_ANON_KEY`, `MAIL_MAILER=log`）に更新（service role key は置かない）（/var/www/.env.example）
- [X] T004 composer に `firebase/php-jwt` と dev の `pestphp/pest`（+`pestphp/pest-plugin-laravel`）を追加し `php artisan pest:install` で Pest を初期化（/var/www/composer.json, /var/www/tests/Pest.php）
- [X] T005 [P] npm に `@supabase/supabase-js`, `tailwindcss`, `@tailwindcss/vite`, `alpinejs` を追加（/var/www/package.json）
- [X] T006 [P] Tailwind + Vite を設定（/var/www/vite.config.js, /var/www/resources/css/app.css）
- [X] T007 [P] Supabase 設定を集約（url / jwks_url / issuer / audience を env から読む）（/var/www/config/supabase.php）
- [X] T008 [P] Laravel Pint 設定でコーディング規約を固定（/var/www/pint.json）
- [X] T009 GitHub Actions CI を追加（Postgres サービスコンテナ上で Pint と Pest を実行）（/var/www/.github/workflows/ci.yml）

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: 全ストーリーが依存する認証基盤（モデル・JWT検証・guard/provider・レイアウト・ルート骨格）。

**⚠️ CRITICAL**: このフェーズ完了まで、いかなるユーザーストーリーも開始できない。

- [X] T010 旧 `0001_01_01_000000_create_users_table.php` を削除し、`..._create_profiles_table.php` を新設して `profiles`（id=uuid PK, display_name not null, avatar_url nullable, timezone not null default `Asia/Tokyo`, timestamptz）を作成。`sessions.user_id` を string(uuid) nullable にして sessions テーブルを保持し、`password_reset_tokens` は作成しない（Supabase 委譲）（/var/www/database/migrations/）
- [X] T011 `Profile` を `Authenticatable` として実装（`HasUuids`, `$incrementing=false`, `keyType='string'`, fillable: display_name/avatar_url/timezone）（/var/www/app/Models/Profile.php）
- [X] T012 [P] テスト用 `ProfileFactory` を作成（/var/www/database/factories/ProfileFactory.php）
- [X] T013 [P] 値オブジェクト `VerifiedClaims`（sub/email/email_verified/name?/picture?/iss/aud/exp）を実装（/var/www/app/Services/Auth/VerifiedClaims.php）
- [X] T014 [P] `SupabaseJwtVerifier` インターフェースを定義（verify(string $jwt): VerifiedClaims）（/var/www/app/Services/Auth/SupabaseJwtVerifier.php）
- [X] T015 [P] Unit テスト（test-first）: `JwksSupabaseJwtVerifier` の 正常/期限切れ/issuer不一致/署名改ざん を `Http::fake` の JWKS とテスト鍵で検証（/var/www/tests/Unit/Auth/SupabaseJwtVerifierTest.php）
- [X] T016 `JwksSupabaseJwtVerifier` を実装（JWKS取得＋kid選択＋iss/exp/aud/sub検証＋TTLキャッシュ、署名検証なしdecode禁止）。T015 をグリーンにする（/var/www/app/Services/Auth/JwksSupabaseJwtVerifier.php）
- [X] T017 認証 guard/provider を `profiles` 向けに登録し、`SupabaseJwtVerifier` を JWKS実装にバインド（/var/www/config/auth.php, /var/www/app/Providers/AppServiceProvider.php）
- [X] T018 [P] 共通 Blade レイアウト `app`（認証後）と `guest`（認証前）をレスポンシブで作成（/var/www/resources/views/layouts/app.blade.php, /var/www/resources/views/layouts/guest.blade.php）
- [X] T019 [P] フロント基盤: Alpine 初期化と Supabase JS ラッパの土台を作成（/var/www/resources/js/app.js, /var/www/resources/js/auth/supabase-client.js）
- [X] T020 `routes/web.php` の骨格を作成（ゲストルートと `auth.supabase` 保護グループのプレースホルダ）（/var/www/routes/web.php）

**Checkpoint**: 基盤完成 — 以降のユーザーストーリーを実装開始できる。

---

## Phase 3: User Story 1 - アカウント登録・ログイン (Priority: P1) 🎯 MVP

**Goal**: 訪問者が Google またはメール＋パスワード（8文字以上・メール確認必須）でアカウントを作成/ログインし、
認証済みホーム画面に入れる。初回ログイン時に `profiles` が冪等 upsert される。

**Independent Test**: 未登録ユーザーが Google またはメール＋パスワードで登録・ログインし、ログイン後に
認証済みホーム画面が表示され、`profiles` が1件だけ作成されることを確認（quickstart S1/S2）。

### Tests for User Story 1 ⚠️（実装前に書き、FAIL を確認）

- [X] T021 [P] [US1] Feature: メール＋パスワード登録フロー（`GET /register` 表示・8文字未満拒否・確認前は保護不可）（/var/www/tests/Feature/Auth/RegisterTest.php）
- [X] T022 [P] [US1] Feature: ログインページ表示と認証済みリダイレクト（`GET /login`）（/var/www/tests/Feature/Auth/LoginTest.php）
- [X] T023 [P] [US1] Feature: `POST /auth/session` の 204/401/403(メール未確認)/422 と `profiles` 冪等作成（verifier を fake 差し替え）（/var/www/tests/Feature/Auth/SessionTest.php）
- [X] T024 [P] [US1] Unit: `SyncProfileFromClaimsAction` の冪等性と display_name 導出（name→email ローカル部→"ユーザー"）。さらに**同一 `sub` の異なる identity（Google/メール）由来 claims を連続 upsert しても `profiles` が常に1件（SC-010/FR-005a）**を検証（/var/www/tests/Unit/Auth/SyncProfileActionTest.php）

### Implementation for User Story 1

- [X] T025 [US1] `EstablishSessionRequest` で `access_token` 必須・文字列を検証（/var/www/app/Http/Requests/EstablishSessionRequest.php）
- [X] T026 [P] [US1] `SyncProfileFromClaimsAction` で claims から `profiles` を冪等 upsert（display_name/avatar_url/timezone 初期化）（/var/www/app/Actions/Auth/SyncProfileFromClaimsAction.php）
- [X] T027 [P] [US1] `EstablishSessionAction` で検証済み `profiles` の Laravel セッションを確立（/var/www/app/Actions/Auth/EstablishSessionAction.php）
- [X] T028 [US1] `AuthSessionController@store`（`POST /auth/session`）: 検証→email_verified確認(403)→upsert→ログイン→204。token を非ログ・本人識別は `sub` のみ採用（T016,T025,T026,T027依存）（/var/www/app/Http/Controllers/Auth/AuthSessionController.php）
- [X] T029 [P] [US1] `AuthPageController` でログイン/登録ページを表示（認証済みは `/` へ）（/var/www/app/Http/Controllers/Auth/AuthPageController.php）
- [X] T030 [P] [US1] `login.blade.php`（Google ボタン＋メール/パスワード）と `register.blade.php`（8文字クライアント検証）。8文字未満の最終的な拒否はサーバ側 Supabase（Minimum password length=8, research §4）が担保し、アプリは UX 補助のクライアント検証を行う（FR-001c）（/var/www/resources/views/auth/login.blade.php, /var/www/resources/views/auth/register.blade.php）
- [X] T031 [US1] `supabase-client.js` に login/register/Google OAuth/確認メール再送 と access token 取得→`POST /auth/session` 連携を実装（/var/www/resources/js/auth/supabase-client.js）
- [X] T032 [P] [US1] 最小ホーム画面（表示名・将来導線プレースホルダ）と `HomeController`（/var/www/app/Http/Controllers/HomeController.php, /var/www/resources/views/home.blade.php）
- [X] T033 [US1] `routes/web.php` に `GET /login`, `GET /register`, `POST /auth/session`(CSRF必須・`throttle`付与), `GET /`(ホーム) を配線（/var/www/routes/web.php）

**Checkpoint**: US1 単独でログイン→ホーム表示まで動作・テスト可能（MVP）。

---

## Phase 4: User Story 2 - 自分のデータを他人から守る (Priority: P1)

**Goal**: 未ログイン/別人のアクセスを保護境界で遮断し、本人識別はサーバ確立のセッションのみから決定。
所有権（Policy）で他人のプロフィールへのアクセスを禁止する。

**Independent Test**: 未ログインで保護URLにアクセスすると `/login` へ誘導、改ざん/期限切れトークンは拒否、
別ユーザー識別子では自分のデータにアクセスできないことを確認（quickstart S3/S4）。

### Tests for User Story 2 ⚠️（実装前に書き、FAIL を確認）

- [X] T034 [P] [US2] Feature: 未認証で保護ルートにアクセス→`/login` リダイレクト、改ざん/期限切れトークンで `POST /auth/session`→401（/var/www/tests/Feature/Auth/ProtectedRouteTest.php）
- [X] T035 [P] [US2] Feature: 別ユーザーのプロフィール参照を `ProfilePolicy` が拒否し、本人識別の偽装が無効になる（/var/www/tests/Feature/Auth/OwnershipTest.php）

### Implementation for User Story 2

- [X] T036 [US2] `EnsureSupabaseSession` ミドルウェア: 有効セッションが無ければ `/login`、期限切れ検知で再ログイン誘導（/var/www/app/Http/Middleware/EnsureSupabaseSession.php）
- [X] T037 [US2] ミドルウェアエイリアス `auth.supabase` を登録し、保護グループに `throttle`（認証済み操作, FR-013）を併用。あわせて `POST /auth/session` にもゲスト向け `throttle` を適用（不正トークン/ログイン連打の抑制, Edge Case L103）。メール/パスワードのログイン試行自体のレート制限は Supabase 委譲である旨を注記（/var/www/bootstrap/app.php）
- [X] T038 [US2] `ProfilePolicy@view` で `profiles.id` ベースの所有権確認を実装し登録（/var/www/app/Policies/ProfilePolicy.php, /var/www/app/Providers/AppServiceProvider.php）
- [X] T039 [US2] `routes/web.php` の保護グループに `auth.supabase`+`throttle` を適用し `/`（ホーム）を内包（/var/www/routes/web.php）

**Checkpoint**: US1 と US2 が独立して動作（ログイン可能かつ保護境界・所有権が成立）。

---

## Phase 5: User Story 3 - プロフィール確認・ログアウト (Priority: P2)

**Goal**: 初回ログインで自動初期化されたプロフィール（表示名・アイコン・タイムゾーン）を本人が閲覧でき、
ログアウトで Laravel と Supabase 双方のセッションを破棄できる。

**Independent Test**: ログイン後に自分のプロフィールが表示され、ログアウト後は保護画面へアクセスできない
（戻る操作でも非表示）ことを確認（quickstart S6）。

### Tests for User Story 3 ⚠️（実装前に書き、FAIL を確認）

- [X] T040 [P] [US3] Feature: 自分の `/profile` 表示（表示名・アイコン・タイムゾーン）と他人プロフィール非表示（/var/www/tests/Feature/ProfileTest.php）
- [X] T041 [P] [US3] Feature: `DELETE /auth/session` でログアウト→保護ルートが `/login` を要求（/var/www/tests/Feature/Auth/LogoutTest.php）

### Implementation for User Story 3

- [X] T042 [US3] `ProfileController@show` で自分の `profiles` を表示し `ProfilePolicy@view` で認可（/var/www/app/Http/Controllers/ProfileController.php）
- [X] T043 [P] [US3] `profile/show.blade.php`（表示名・アイコン・タイムゾーンをレスポンシブ表示）（/var/www/resources/views/profile/show.blade.php）
- [X] T044 [US3] `AuthSessionController@destroy`（`DELETE /auth/session`）で Laravel セッションを破棄し 204（/var/www/app/Http/Controllers/Auth/AuthSessionController.php）
- [X] T045 [P] [US3] `supabase-client.js` に `signOut()` を追加し、ログアウト時に `DELETE /auth/session` と Supabase signOut を両方実行（/var/www/resources/js/auth/supabase-client.js）
- [X] T046 [US3] `routes/web.php` に `GET /profile`（保護）と `DELETE /auth/session` を配線（/var/www/routes/web.php）

**Checkpoint**: US1〜US3 が独立して動作（ログイン・保護・プロフィール確認・ログアウト）。

---

## Phase 6: User Story 4 - パスワード再設定 (Priority: P2)

**Goal**: メール＋パスワードのユーザーが、登録メール宛の手続きでパスワードを再設定して再ログインでき、
申請応答はアカウントの有無を漏らさない一様な案内とする。

**Independent Test**: `/forgot-password` から申請→一様な案内→`/reset-password` で新パスワード設定→
新パスワードでログイン成功・旧は失敗を確認（quickstart S5）。

### Tests for User Story 4 ⚠️（実装前に書き、FAIL を確認）

- [X] T047 [P] [US4] Feature: `GET /forgot-password` と `GET /reset-password` の表示、一様応答方針の確認、および新パスワードの8文字クライアント検証。※新PWでのログイン可・旧PW無効化の中核挙動は Supabase 委譲のため手動検証（quickstart S5 / T056）とする（/var/www/tests/Feature/Auth/PasswordResetTest.php）

### Implementation for User Story 4

- [X] T048 [P] [US4] `AuthPageController` に forgot-password / reset-password 表示を追加（/var/www/app/Http/Controllers/Auth/AuthPageController.php）
- [X] T049 [P] [US4] `forgot-password.blade.php`（メール入力・一様案内）と `reset-password.blade.php`（新パスワード8文字のクライアント検証。最終的な拒否はサーバ側 Supabase が担保, FR-001c）（/var/www/resources/views/auth/forgot-password.blade.php, /var/www/resources/views/auth/reset-password.blade.php）
- [X] T050 [P] [US4] `supabase-client.js` に `resetPasswordForEmail()` と `updateUser({password})` を追加（/var/www/resources/js/auth/supabase-client.js）
- [X] T051 [US4] `routes/web.php` に `GET /forgot-password`, `GET /reset-password` を配線（/var/www/routes/web.php）

**Checkpoint**: 全ユーザーストーリーが独立して動作。

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: 全ストーリーに跨る仕上げ・検証。

- [X] T052 [P] エラーメッセージを内部情報非開示・一様文言に統一（ログイン/再設定失敗・アカウント有無, FR-010/FR-001b）（/var/www/resources/views/auth/, /var/www/lang/）
- [X] T053 [P] シークレットハイジーン確認: access token / 鍵 / Cookie をログ・フロントに出さない（憲法 IV / FR-011）（/var/www/app/Services/Auth/, /var/www/app/Http/Controllers/Auth/）
- [X] T054 [P] レスポンシブ確認（PC・スマホでログイン/ログアウト/プロフィール, SC-006/FR-012）（/var/www/resources/views/）
- [X] T055 Pint と Pest 全スイートを実行しグリーンを確認（/var/www/）
- [ ] T056 quickstart.md の S1〜S7 を手動検証（要・実 Supabase 認証情報＋ブラウザOAuth。自動テストでは代替済みだが本番疎通は未実施）（/var/www/specs/001-foundation-and-auth/quickstart.md）
- [X] T057 [P] README にローカルセットアップ（PostgreSQL/Supabase設定）を追記（/var/www/README.md）

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: 依存なし。即着手可能。
- **Foundational (Phase 2)**: Setup 完了に依存。全ユーザーストーリーをブロックする。
- **User Stories (Phase 3-6)**: すべて Foundational 完了に依存。優先度順 P1(US1→US2) → P2(US3→US4)。
- **Polish (Phase 7)**: 対象ストーリー完了に依存。

### User Story Dependencies

- **US1 (P1)**: Foundational 後に着手可。他ストーリー非依存。MVP。
- **US2 (P1)**: Foundational 後に着手可。保護境界はセッション確立（US1の成果）と組み合わさるが、`actingAs` で独立テスト可能。
- **US3 (P2)**: Foundational 後に着手可。プロフィール自動初期化は US1 の SyncProfile を利用するが、`actingAs` で独立テスト可能。
- **US4 (P2)**: Foundational 後に着手可。他ストーリー非依存。

### Within Each User Story

- テストを先に書き FAIL を確認 → 実装。
- Model → Action/Service → Controller → ルート → ビュー の順。
- 同一ファイル（特に `routes/web.php`, `supabase-client.js`, `AuthSessionController.php`, `AuthPageController.php`）を触るタスクは [P] を付けず直列化。

### Parallel Opportunities

- Setup の [P] タスク（T001-T003, T005-T008）は並列可。
- Foundational の [P] タスク（T012-T015, T018, T019）は並列可。
- Foundational 完了後、US1〜US4 は人員があれば並列着手可能。
- 各ストーリーのテストタスク（[P]）は相互に並列可能。

---

## Parallel Example: User Story 1

```bash
# US1 のテストをまとめて起動（実装前・FAIL確認）:
Task: "Feature RegisterTest in tests/Feature/Auth/RegisterTest.php"
Task: "Feature LoginTest in tests/Feature/Auth/LoginTest.php"
Task: "Feature SessionTest in tests/Feature/Auth/SessionTest.php"
Task: "Unit SyncProfileActionTest in tests/Unit/Auth/SyncProfileActionTest.php"

# 別ファイルの実装をまとめて起動:
Task: "SyncProfileFromClaimsAction in app/Actions/Auth/SyncProfileFromClaimsAction.php"
Task: "EstablishSessionAction in app/Actions/Auth/EstablishSessionAction.php"
Task: "login/register blade in resources/views/auth/"
```

---

## Implementation Strategy

### MVP First (User Story 1 のみ)

1. Phase 1: Setup を完了。
2. Phase 2: Foundational を完了（全ストーリーをブロックする重要フェーズ）。
3. Phase 3: User Story 1 を完了。
4. **STOP & VALIDATE**: US1 を単独検証（登録/ログイン→ホーム、profiles 1件）。
5. 準備できればデプロイ/デモ。

### Incremental Delivery

1. Setup + Foundational → 基盤完成。
2. US1（P1）→ 独立検証 → デモ（MVP）。
3. US2（P1）→ 保護境界・所有権 → 独立検証。
4. US3（P2）→ プロフィール・ログアウト → 独立検証。
5. US4（P2）→ パスワード再設定 → 独立検証。

### Parallel Team Strategy

1. Setup + Foundational をチームで完了。
2. 完了後、US1/US2 を P1 担当が、US3/US4 を P2 担当が並列着手。
3. 各ストーリーは独立して完成・統合。

---

## Notes

- [P] = 別ファイル・依存なし。同一ファイルを触るタスクは直列。
- [Story] ラベルでストーリーへのトレーサビリティを担保。
- 外部（Supabase JWKS/GoTrue）は実呼び出し禁止。`Http::fake` と verifier の fake で代替（憲法 VI）。
- access token / 鍵 / パスワードはログ・フロントに出さない（憲法 IV）。
- テストは FAIL を確認してから実装。タスク単位でコミット。

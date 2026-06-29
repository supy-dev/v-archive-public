# Contract: Authentication & Foundation Endpoints

Laravel が公開するルート契約。認証UIの実処理（OAuth/確認メール/再設定メール送信）は Supabase JS
クライアントが担い、Laravel はセッション確立・保護・プロフィール参照を担当する。

## 公開（ゲスト）ルート

### `GET /login`
- ログインページ（Blade, `guest` レイアウト）。Google ボタン＋メール/パスワードフォーム。
- 認証済みでアクセスした場合は `/` へリダイレクト。

### `GET /register`
- 新規登録ページ。メール＋パスワード（8文字以上のクライアント検証）。

### `GET /forgot-password`
- パスワード再設定申請ページ（メール入力）。

### `GET /reset-password`
- 再設定リンクからの遷移先。新パスワード設定ページ。

> 上記フォーム送信先は Supabase JS（クライアント）。これらは画面表示のみで外部APIをサーバから呼ばない。

## セッション確立 / 破棄

### `POST /auth/session`
クライアントが Supabase 認証成功後に取得した access token を渡し、Laravelセッションを確立する。

- **Request (JSON)**:
  ```json
  { "access_token": "<supabase JWT>" }
  ```
  - CSRF トークン必須。`access_token` は必須・文字列。
- **処理**:
  1. `SupabaseJwtVerifier` が JWKS で署名検証（`iss`/`exp`/`aud`/`sub`）。
  2. `email_verified` が false の場合は確立を拒否（FR-001d）。
  3. `SyncProfileFromClaimsAction` が `profiles` を冪等 upsert（FR-006）。
  4. `EstablishSessionAction` が当該 `profiles` で Laravel セッションを確立。
- **Responses**:
  | 状況 | ステータス | 本文 |
  |---|---|---|
  | 成功 | `204 No Content` | （クライアントは `/` へ遷移） |
  | トークン不正/署名検証失敗/期限切れ | `401 Unauthorized` | ユーザー向け汎用メッセージ |
  | メール未確認 | `403 Forbidden` | 確認を促すメッセージ（再送導線） |
  | 入力不正（access_token欠如） | `422 Unprocessable Entity` | バリデーションエラー |
- **禁止**: access_token をログ出力しない（憲法 IV）。本人識別は検証済み `sub` のみを採用し、リクエストの
  他フィールドのユーザーIDを信用しない（FR-004）。

### `DELETE /auth/session`（ログアウト）
- Laravel セッションを破棄。クライアントは併せて Supabase `signOut()` を呼ぶ（FR-008）。
- **Response**: `204 No Content`。以後の保護ルートはログインを要求。

## 保護ルート（`middleware: auth.supabase`）

### `GET /`（ホーム）
- 認証必須。最小のホーム（プロフィール表示名・各機能への将来導線のプレースホルダ）。
- 未認証時は `/login` へリダイレクト（FR-002 / SC-002）。

### `GET /profile`
- 認証必須。**自分の** `profiles`（表示名・アイコン・タイムゾーン）を表示（FR-007）。
- 他ユーザーのプロフィールは参照不可。`ProfilePolicy@view` で所有権確認（FR-009 / SC-003）。

## ミドルウェア契約: `EnsureSupabaseSession`（`auth.supabase`）

- 有効な Laravel セッション（=検証済みSupabaseユーザー）が無ければ `/login` へリダイレクト。
- セッションが確立済みでも、保護操作時に期限切れ等を検知した場合は再ログインへ誘導（Edge Case）。
- 認証済みルートにレート制限（`throttle`）を併用（FR-013）。

## エラーメッセージ方針

- ログイン/再設定失敗・アカウント有無は推測させない一様な文言（FR-001b / FR-010）。
- 内部理由（署名不一致・issuer不一致等）はユーザーへ出さず、サーバログにのみ（トークン本体は除く）記録。

# Route Contracts: 本番品質強化（hardening）

**Feature**: 008-hardening | **Date**: 2026-06-22

---

## 新規ルート（認証不要・公開）

### GET /privacy

| 項目 | 値 |
|------|---|
| URL | `/privacy` |
| Method | GET |
| Auth | 不要（ゲスト含む全ユーザー） |
| Controller | `LegalController@privacy` |
| View | `legal/privacy.blade.php` |
| Layout | `layouts/minimal.blade.php` |
| 名前 | `legal.privacy` |

**レスポンス**: プライバシーポリシーページ（200 OK）

---

### GET /terms

| 項目 | 値 |
|------|---|
| URL | `/terms` |
| Method | GET |
| Auth | 不要（ゲスト含む全ユーザー） |
| Controller | `LegalController@terms` |
| View | `legal/terms.blade.php` |
| Layout | `layouts/minimal.blade.php` |
| 名前 | `legal.terms` |

**レスポンス**: 利用規約ページ（200 OK）

---

## 変更ルート

### POST /oshis/{oshi}/channels/{userChannel}/fetch-older（リミッター変更）

| 項目 | 変更前 | 変更後 |
|------|-------|-------|
| ミドルウェア | `auth.supabase`, `throttle:oshi-mutations` | `auth.supabase`, `throttle:channel-sync` |
| レート制限 | 60回/分（oshi-mutations グループ共通） | **5回/分**（channel-sync 専用） |
| Controller | `ChannelSyncController@fetchOlder` | 変更なし |
| 名前 | `oshis.channels.fetchOlder` | 変更なし |

**429 レスポンス例:**
```json
{
  "message": "Too Many Requests"
}
```
または Blade ページにリダイレクト（Blade ルートの場合 Laravel デフォルト動作）

---

## 変更なしルート（参考）

以下は本 Feature で変更しない既存ルートを参考として記載する。

| ルート | リミッター | 備考 |
|-------|----------|------|
| `GET /archive` 他閲覧系 | `throttle:60,1` | 変更なし |
| `POST/PATCH/DELETE /oshis/**` | `throttle:oshi-mutations` | 変更なし（60回/分） |
| `POST/PATCH/DELETE /archives/**/memos/**` | `throttle:memo-mutations` | 変更なし（60回/分） |
| `PATCH /watch-items/{watchItem}/position` | `throttle:playback-position` | 変更なし（10回/分） |

---

## エラーページ（ルートなし・自動解決）

Laravel の例外ハンドラが自動的に以下のビューを使用する。追加ルート定義は不要。

| HTTP ステータス | ビューパス |
|--------------|----------|
| 404 Not Found | `resources/views/errors/404.blade.php` |
| 500 Internal Server Error | `resources/views/errors/500.blade.php` |

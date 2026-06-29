# Phase 1 Data Model: Oshi & Channel Registration

本フィーチャーで導入するエンティティは3つ。

- `oshis`（ユーザー固有・所有権保護）
- `youtube_channels`（全ユーザー共有マスタ・ユーザーが直接更新不可）
- `user_channels`（ユーザー固有・`oshis` と `youtube_channels` を結ぶ中間テーブル）

---

## Entity 1: Oshi（`oshis`）

ユーザーが追う対象の単位。`profiles` に紐づく。1人のユーザーが複数の推しを持てる。

| 列 | 型 | 制約 / 既定 | 説明 |
|---|---|---|---|
| id | uuid | PK, default gen_random_uuid() | |
| profile_id | uuid | NOT NULL, FK → profiles.id ON DELETE CASCADE | 所有ユーザー |
| name | varchar(100) | NOT NULL | 推し名（必須、最大100文字） |
| group_name | varchar(100) | nullable | 所属グループ名（任意） |
| color_id | varchar(50) | nullable | テーマカラー識別子（`OshiColor` Enum の値。NULL = 未設定） |
| memo | text | nullable | メモ（任意） |
| created_at | timestamptz | NOT NULL | |
| updated_at | timestamptz | NOT NULL | |

**インデックス**:
- `(profile_id)` — ユーザーの推し一覧取得（N+1 防止）

**バリデーション / ルール**（spec 要件との対応）:
- FR-001: `name` は必須・最大100文字。`color_id` は `OshiColor` Enum に含まれる値か NULL のみ許容
  （FormRequest で `Rule::enum(OshiColor::class)` または `nullable`）。パレット外の値はバリデーション段階で拒否。
- FR-010: `profile_id` はリクエストボディから取得せず、`auth()->id()` で設定する（憲法 III）。

**状態遷移**: 明示的な状態機械なし。削除時は `ON DELETE CASCADE` で紐づく `user_channels` を削除する（FR-002 / spec edge cases）。

---

## Entity 2: YoutubeChannel（`youtube_channels`）

全ユーザー共通の YouTube チャンネル情報。`youtube_channel_id`（YouTube 側の ID）で一意。
ユーザーが直接更新・削除できない（憲法 II / FR-011）。

| 列 | 型 | 制約 / 既定 | 説明 |
|---|---|---|---|
| id | uuid | PK, default gen_random_uuid() | |
| youtube_channel_id | varchar(255) | NOT NULL, UNIQUE | YouTube のチャンネル ID（`UCxxxxxxxxx`） |
| title | varchar(255) | NOT NULL | チャンネル名 |
| description | text | nullable | チャンネル説明（一覧取得時は SELECT しない） |
| handle | varchar(100) | nullable | @handle から @ を除いた識別子（API レスポンスの `customUrl` を正規化） |
| thumbnail_url | text | nullable | サムネイル URL（中サイズ） |
| uploads_playlist_id | varchar(50) | nullable | uploads プレイリスト ID（Feature 003 の動画同期で使用） |
| published_at | timestamptz | nullable | YouTube 上のチャンネル開設日時 |
| sync_status | varchar(20) | NOT NULL, default 'pending' | `ChannelSyncStatus` Enum: pending / synced / error |
| sync_error_message | text | nullable | 同期エラー時のメッセージ（429・5xx 等の理由を記録） |
| last_synced_at | timestamptz | nullable | 最終同期日時（後続フィーチャーが更新） |
| created_at | timestamptz | NOT NULL | |
| updated_at | timestamptz | NOT NULL | |

**インデックス**:
- `UNIQUE (youtube_channel_id)` — 重複作成防止 + チャンネル検索

**バリデーション / ルール**:
- FR-005: 全ユーザー共通で1レコード。`RegisterChannelAction` が `youtube_channel_id` で upsert する。
- FR-011: 一般ユーザー向けルートでは CREATE/UPDATE/DELETE ルートを公開しない。更新は後続フィーチャーの同期処理のみ。
- FR-013: 登録直後は `sync_status = 'pending'`。動画取得・同期は Feature 003 が担う。
- 一覧表示時に `description` を SELECT しない（憲法 技術制約「description全文を一覧で取得しない」）。

**保存しない情報**（憲法 II / spec Assumptions）:
- 動画ファイル・サムネイル画像本体・コメント本文・API レスポンス全文は保存しない。

---

## Entity 3: UserChannel（`user_channels`）

「どのユーザーが・どの推しに・どのチャンネルを」登録したかという関連と、ユーザー固有の設定。

| 列 | 型 | 制約 / 既定 | 説明 |
|---|---|---|---|
| id | uuid | PK, default gen_random_uuid() | |
| profile_id | uuid | NOT NULL, FK → profiles.id ON DELETE CASCADE | 所有ユーザー |
| oshi_id | uuid | NOT NULL, FK → oshis.id ON DELETE CASCADE | 紐づく推し |
| youtube_channel_id | uuid | NOT NULL, FK → youtube_channels.id | 共有マスタへの参照 |
| is_main | boolean | NOT NULL, default false | この推しのメインチャンネルか |
| sync_enabled | boolean | NOT NULL, default true | 同期対象か |
| notify_enabled | boolean | NOT NULL, default false | 通知対象か（設定値のみ保存。配信は後続） |
| registered_at | timestamptz | NOT NULL, default now() | ユーザーが登録した日時 |
| created_at | timestamptz | NOT NULL | |
| updated_at | timestamptz | NOT NULL | |

**インデックス**:
- `UNIQUE (profile_id, youtube_channel_id)` — 同一ユーザーが同一チャンネルを重複登録しない（FR-007 / FR-008）
- `(profile_id, oshi_id)` — 推し詳細でのチャンネル一覧取得（N+1 防止）
- 部分ユニークインデックス: `UNIQUE (profile_id, oshi_id) WHERE is_main = TRUE` — メインの一意性を DB で担保（research §3）

**バリデーション / ルール**:
- FR-006: `profile_id`・`oshi_id`・`youtube_channel_id` を保持。`RegisterChannelAction` が作成する。
- FR-007: UNIQUE `(profile_id, youtube_channel_id)` 制約で重複登録を防止。
- FR-008 MVP 制約: 同一ユーザーが同一チャンネルを複数の推しに登録できない（上記 UNIQUE 制約で実現）。
- FR-009: 推しへの最初のチャンネル登録時は `is_main = true`。部分ユニークインデックスでDB担保。
  メイン変更は `SetMainChannelAction` がトランザクション内で旧メインを `false` にしてから新メインを `true` にする。
- FR-012: `notify_enabled` は設定値保存のみ。通知配信ロジックは後続フィーチャーが本列を参照して実装する。
- FR-017: `DeregisterChannelAction` は `user_channels` レコードのみ削除。`youtube_channels` は削除しない。
  他ユーザーの `user_channels` も不変（FK は `profile_id` ベースで絞り込まれる）。
- `profile_id` はリクエストボディから取得せず `auth()->id()` で設定（憲法 III）。

**状態遷移（is_main）**:

```
チャンネル登録（推しへの初回）  →  is_main = true
チャンネル登録（2件目以降）     →  is_main = false
メイン変更                       →  旧メイン: false / 新メイン: true（トランザクション）
メインチャンネル解除             →  残存チャンネルあり: 最古の registered_at を新メインへ / なし: そのまま削除
```

---

## 値オブジェクト / Enum

### OshiColor（PHP backed Enum）

`app/Enums/OshiColor.php` に定義。識別子（string）をDB に保存する。

| 識別子 | 表示名 | Tailwind クラス例 |
|---|---|---|
| rose | ローズ | bg-rose-400 |
| pink | ピンク | bg-pink-400 |
| fuchsia | フューシャ | bg-fuchsia-400 |
| purple | パープル | bg-purple-400 |
| violet | バイオレット | bg-violet-400 |
| blue | ブルー | bg-blue-400 |
| cyan | シアン | bg-cyan-400 |
| teal | ティール | bg-teal-400 |
| green | グリーン | bg-green-400 |
| lime | ライム | bg-lime-400 |
| yellow | イエロー | bg-yellow-400 |
| orange | オレンジ | bg-orange-400 |
| red | レッド | bg-red-400 |
| slate | スレート | bg-slate-400 |
| gray | グレー | bg-gray-400 |

パレット外の値は FormRequest のバリデーション段階で `422` を返す。

### ChannelSyncStatus（PHP backed Enum）

`app/Enums/ChannelSyncStatus.php` に定義。`youtube_channels.sync_status` 列に保存する。

| 識別子 | 意味 |
|---|---|
| pending | 登録済み・動画同期待ち（Feature 002 では常にこの初期状態） |
| synced | 同期完了（Feature 003 以降が設定） |
| error | 同期エラー（Feature 003 以降が設定） |

---

## 既存スキーマへの影響

- `profiles` テーブル: 変更なし（`oshis.profile_id` が FK として参照するのみ）。
- `sessions` テーブル: 変更なし。
- 新規テーブル3件を本フィーチャーのマイグレーションで追加する。全テーブルの先行一括マイグレーションはしない（憲法・開発ワークフロー）。

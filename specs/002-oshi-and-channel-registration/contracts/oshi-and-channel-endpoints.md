# Contract: Oshi & Channel Registration Endpoints

Laravel が公開するルート契約。全ルートは `middleware: auth.supabase`（Feature 001 で確立した認証ガード）で
保護され、未認証時は `/login` へリダイレクトする。共有マスタ（`youtube_channels`）の CRUD ルートは
一般ユーザーへ公開しない（憲法 II / FR-011）。

---

## 推し（Oshi）ルート

ベースパス: `/oshis`
ミドルウェア: `auth.supabase`, `throttle:oshi-mutations`（変更系のみ）

### `GET /oshis`（一覧）

- **説明**: 自分の推し一覧を表示。各推しのメインチャンネルのタイトル・サムネイル・チャンネル登録数を含む。
- **所有権**: `profile_id = auth()->id()` で絞り込む（他ユーザーの推しは表示しない）。
- **Eager load**: `userChannels.youtubeChannel`（N+1 防止）
- **Response**: Blade ビュー `oshis.index`。

### `GET /oshis/create`（作成フォーム）

- **説明**: 推し作成フォームページ。テーマカラーパレット (`OshiColor::cases()`) を Blade コンポーネントで表示。

### `POST /oshis`（作成）

ミドルウェア: `throttle:oshi-mutations` を適用。

- **Request (form)**:

  | フィールド | 型 | バリデーション |
  |---|---|---|
  | name | string | required, max:100 |
  | group_name | string | nullable, max:100 |
  | color_id | string | nullable, `Rule::enum(OshiColor::class)` |
  | memo | string | nullable |

- **処理**: `CreateOshiAction` が `profile_id = auth()->id()` でレコード作成。
- **禁止**: リクエストボディの `profile_id` を信用しない。
- **Responses**:

  | 状況 | ステータス | 挙動 |
  |---|---|---|
  | 成功 | `302 Redirect` | `/oshis/{oshi}` へリダイレクト |
  | バリデーション失敗 | `422` | フォームエラー付きで `oshis.create` を再表示 |

### `GET /oshis/{oshi}`（詳細）

- **説明**: 推し詳細ページ。紐づくチャンネル一覧（タイトル・サムネイル・同期状態・設定）・チャンネル登録フォームを含む。
- **所有権**: `OshiPolicy@view` で `oshi->profile_id === auth()->id()` を確認。不一致は `403`。
- **Eager load**: `userChannels.youtubeChannel`

### `GET /oshis/{oshi}/edit`（編集フォーム）

- **所有権**: `OshiPolicy@update` を確認。

### `PUT /oshis/{oshi}`（更新）

ミドルウェア: `throttle:oshi-mutations` を適用。

- **Request**: `POST /oshis` と同じフィールド（`name` は required）。
- **所有権**: `OshiPolicy@update`。
- **処理**: `UpdateOshiAction`。
- **Response**: 成功時 `/oshis/{oshi}` へリダイレクト。

### `DELETE /oshis/{oshi}`（削除）

ミドルウェア: `throttle:oshi-mutations` を適用。

- **所有権**: `OshiPolicy@delete`。
- **処理**: `DeleteOshiAction` が `oshis` レコードを削除（`user_channels` は `ON DELETE CASCADE` で削除）。
  共有マスタ `youtube_channels` は削除しない（FR-017 準拠・所有権外）。
- **Response**: 成功時 `/oshis` へリダイレクト。

---

## チャンネル登録（UserChannel）ルート

ベースパス: `/oshis/{oshi}/channels`
ミドルウェア: `auth.supabase`, `throttle:oshi-mutations`（変更系のみ）

### `POST /oshis/{oshi}/channels`（チャンネル登録）

- **所有権前提**: `{oshi}` が `auth()->id()` 所有であることを `OshiPolicy@update` または
  `UserChannelPolicy@create` で確認。他ユーザーの推しにチャンネルを登録できない。
- **Request (form)**:

  | フィールド | 型 | バリデーション |
  |---|---|---|
  | channel_url | string | required, max:500 |

- **処理順序** (`RegisterChannelAction`):
  1. `channel_url` を解析 → `ChannelInput` 値オブジェクト（形式外なら `422`）。
  2. `youtube_channels` を `youtube_channel_id` で検索（存在すれば API 呼び出し不要）。
  3. 存在しない場合: YouTube API `channels.list` を呼ぶ（`Http::fake` でモック可）。
     - チャンネルが見つからない場合: `422`（ユーザー向けメッセージ）。
     - API エラー/クォータ超過: `422`（再試行を促すユーザー向けメッセージ。内部詳細は露出しない）。
  4. `youtube_channels` を upsert（`youtube_channel_id` をキーに）。新規は `sync_status = 'pending'`。
  5. `user_channels` の `(profile_id, youtube_channel_id)` を確認 → 重複なら `422`。
  6. `user_channels` を作成。当該ユーザー・当該推しの既存チャンネル数が0件なら `is_main = true`。

- **禁止**:
  - YouTube API キーをレスポンスや HTML に含めない。
  - API エラーの内部詳細（ステータスコード・キー・URL）をユーザーへ表示しない。
  - `profile_id` をリクエストから取得しない。

- **Responses**:

  | 状況 | ステータス | 挙動 |
  |---|---|---|
  | 成功 | `302 Redirect` | `/oshis/{oshi}` へリダイレクト |
  | 形式不正・チャンネル不存在・重複 | `422` | フォームエラー付きで `oshis.show` を再表示 |
  | YouTube API エラー | `422` | 再試行を促すユーザー向けメッセージ |

### `DELETE /oshis/{oshi}/channels/{userChannel}`（チャンネル登録解除）

- **所有権**: `UserChannelPolicy@delete` で `userChannel->profile_id === auth()->id()` を確認。
- **処理** (`DeregisterChannelAction`):
  1. `userChannel.is_main === true` かつ 当該ユーザー・推しの他チャンネルが存在する場合:
     最も古い `registered_at` を持つ別チャンネルを新しいメインに設定してから削除。
  2. それ以外: そのまま削除。
  3. `youtube_channels` は変更しない（FR-017）。
- **Response**: 成功時 `/oshis/{oshi}` へリダイレクト。

### `PATCH /oshis/{oshi}/channels/{userChannel}`（設定変更）

ミドルウェア: `throttle:oshi-mutations` を適用。

- **所有権**: `UserChannelPolicy@update`。
- **Request (form)**:

  | フィールド | 型 | バリデーション |
  |---|---|---|
  | sync_enabled | boolean | nullable |
  | notify_enabled | boolean | nullable |

- **処理**: `UpdateChannelSettingsAction` が `sync_enabled`/`notify_enabled` を更新。
  `notify_enabled` は設定値の保存のみ（配信は行わない・FR-012）。
- **Response**: 成功時 `/oshis/{oshi}` へリダイレクト。

### `PUT /oshis/{oshi}/channels/{userChannel}/main`（メインチャンネル変更）

ミドルウェア: `throttle:oshi-mutations` を適用。

- **所有権**: `UserChannelPolicy@update`。
- **処理** (`SetMainChannelAction`): トランザクション内で:
  1. 同一 `(profile_id, oshi_id)` の `is_main = true` のレコードを `false` に更新。
  2. 指定 `userChannel` の `is_main` を `true` に更新。
  - 部分ユニークインデックスが2重設定を DB レベルで阻止する。
- **Response**: 成功時 `/oshis/{oshi}` へリダイレクト。

---

## Policy 契約

### OshiPolicy

| メソッド | 条件 |
|---|---|
| view | `$oshi->profile_id === $user->id` |
| create | 認証済みユーザーは自分の推しを作成可（追加制限なし） |
| update | `$oshi->profile_id === $user->id` |
| delete | `$oshi->profile_id === $user->id` |

### UserChannelPolicy

| メソッド | 条件 |
|---|---|
| view | `$userChannel->profile_id === $user->id` |
| create | `$oshi->profile_id === $user->id`（oshi への登録権限） |
| update | `$userChannel->profile_id === $user->id` |
| delete | `$userChannel->profile_id === $user->id` |

---

## エラーメッセージ方針

- YouTube API が応答しない/クォータ超過 → 「チャンネル情報の取得に失敗しました。しばらく後に再度お試しください。」
- チャンネルが存在しない/特定できない → 「指定されたチャンネルが見つかりませんでした。URL または @handle を確認してください。」
- 対応形式以外の入力 → 「対応形式: チャンネル URL（`/channel/UC...` または `/@handle`）、または @handle を入力してください。」
- 重複登録 → 「このチャンネルはすでに登録されています。」
- 他ユーザーのリソースへのアクセス → `403 Forbidden`（詳細を表示しない）。
- 内部エラー情報（API キー・スタックトレース・SQL エラー等）はユーザーへ表示しない（憲法 IV）。

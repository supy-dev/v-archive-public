# Phase 0 Research: Oshi & Channel Registration

本フィーチャーの Technical Context の要解決事項と技術的不確実性を解消する。

## 1. YouTube チャンネル特定方式（channels.list パラメータと URL 解析）

- **Decision**: 入力を3種に分類して解析し、それぞれ対応する `channels.list` パラメータへマップする。

  | 入力形式 | 例 | 解析結果 | API パラメータ |
  |---|---|---|---|
  | チャンネル URL（`/channel/UC...`） | `https://youtube.com/channel/UCxxxxxx` | channel_id 直接抽出 | `id=UCxxxxxx` |
  | @handle URL（`/@handle`） | `https://youtube.com/@Kizuna_AI` | handle 抽出（@ を除く） | `forHandle=Kizuna_AI` |
  | @handle 文字列 | `@Kizuna_AI` または `Kizuna_AI`（@省略を正規化） | handle | `forHandle=Kizuna_AI` |
  | レガシー `/c/` URL | `https://youtube.com/c/Kizuna_AI` | customUrl として handle 扱い | `forHandle=Kizuna_AI` |
  | レガシー `/user/` URL | `https://youtube.com/user/username` | username | `forUsername=username` |

  `channels.list` クォータコスト: 1ユニット/呼び出し（`search.list` の100ユニットと比較して最小）。

  API レスポンスから取得する値:
  - `snippet.title` → `youtube_channels.title`
  - `snippet.description` → `youtube_channels.description`
  - `snippet.customUrl` → `youtube_channels.handle`（@ 含む場合は除去して保存）
  - `snippet.thumbnails.medium.url` → `youtube_channels.thumbnail_url`
  - `snippet.publishedAt` → `youtube_channels.published_at`
  - `contentDetails.relatedPlaylists.uploads` → `youtube_channels.uploads_playlist_id`（後続 Feature 003 が使用）

- **Rationale**: 憲法 V が `search.list` を明示禁止。`channels.list` は最小クォータで目的を達成できる。
  `/channel/` URL はチャンネル ID を直接含むため API 呼び出し不要の場合もあるが、チャンネルの存在確認と
  メタ情報取得のために 1 回は呼ぶ（既に共有マスタが存在する場合は upsert で再利用）。

- **Alternatives considered**:
  - `search.list?q=channelUrl` を使う: 100ユニット/呼び出しで仕様禁止。→却下。
  - フロントで YouTube oEmbed を使う: チャンネルIDが取れず、メタ情報も不完全。→却下。

- **Note**: handle の表記揺れ（`@` 有無・大文字小文字・末尾スラッシュ）は正規化してから API へ渡す。
  大文字小文字は YouTube 側が非感受性処理するが、保存値は API レスポンスの `customUrl`（小文字）を使う。

## 2. テーマカラーの設計（Enum + Tailwind + Blade）

- **Decision**: PHP backed Enum `OshiColor` でパレットを定義し、識別子（string）を DB に保存する。
  Blade コンポーネントがパレット全色を描画し、選択済み識別子をハイライト表示する。

  ```php
  // app/Enums/OshiColor.php（識別子例）
  enum OshiColor: string {
      case Rose    = 'rose';
      case Pink    = 'pink';
      case Fuchsia = 'fuchsia';
      case Purple  = 'purple';
      case Violet  = 'violet';
      case Blue    = 'blue';
      case Cyan    = 'cyan';
      case Teal    = 'teal';
      case Green   = 'green';
      case Lime    = 'lime';
      case Yellow  = 'yellow';
      case Orange  = 'orange';
      case Red     = 'red';
      case Slate   = 'slate';
      case Gray    = 'gray';

      public function label(): string { /* 表示名 */ }
      public function tailwindBg(): string { /* 例: 'bg-rose-400' */ }
  }
  ```

  FormRequest の `Rule::enum(OshiColor::class)` でパレット外の値を拒否する（バリデーション段階で遮断）。
  `color_id` カラムは `varchar(50) nullable`（テーマカラー未選択を許容）。

- **Rationale**: Enum で型安全を保証し（憲法 III「列挙値は Enum で扱う」）、将来のパレット変更も
  Enum 追加のみで対応できる。DB には識別子のみ保存するためパレット変更時の既存データへの影響が最小。

- **Alternatives considered**:
  - HEX 文字列を自由入力: 仕様で明示禁止（clarification 回答）。→却下。
  - int 型インデックス: 識別子の意味が失われ、Enum との対応が間接的になる。→却下。
  - DB に color テーブルを作る: MVP では過剰。Enum で十分。→却下。

## 3. メインチャンネルの一意保証（DB レベルでの制約）

- **Decision**: PostgreSQL の**部分ユニークインデックス**でメインの一意性を DB レベルで保証する。

  ```sql
  CREATE UNIQUE INDEX user_channels_main_unique
    ON user_channels (profile_id, oshi_id)
    WHERE is_main = TRUE;
  ```

  アプリフローは以下の通りトランザクション内で実行:
  1. **最初のチャンネル登録**: `user_channel` を `is_main = true` で作成（他が0件なので一意制約を満たす）。
  2. **2件目以降の登録**: `is_main = false` で作成。
  3. **メイン変更（`SetMainChannelAction`）**: 同一 `(profile_id, oshi_id)` の既存メインを `false` に更新 →
     対象を `true` に更新（順序を守ることで制約違反を回避）。
  4. **メインチャンネルの登録解除（`DeregisterChannelAction`）**: メインを削除する場合は、残存チャンネルが
     あれば最も古い登録（`registered_at ASC`）を新しいメインに自動設定してから削除する。
     残存チャンネルがなければそのまま削除（推しのチャンネルが0件になる正常系）。

- **Rationale**: 仕様「推しにチャンネルが1件以上ある限りメインは常にちょうど1つ」をアプリバグや
  並行リクエストに対してDB で強制する（Complexity Tracking 参照）。

- **Alternatives considered**:
  - `CHECK` 制約のみ: PostgreSQL の CHECK は部分インデックスほど確実でない。→部分インデックスを採用。
  - アプリレベルのみで管理: 並行リクエストで不変条件が壊れるリスク。→却下。

## 4. レート制限（認証済み変更操作への適用）

- **Decision**: Laravel の名前付きレートリミッタを `AppServiceProvider`（または `RouteServiceProvider`）
  で定義し、変更操作ルートに `throttle:oshi-mutations` を適用する。

  ```php
  // 例: 60リクエスト / 分 per ユーザー
  RateLimiter::for('oshi-mutations', fn(Request $r) =>
      Limit::perMinute(60)->by($r->user()?->id ?: $r->ip())
  );
  ```

  `GET` 系（一覧・詳細）はレート制限の対象外。`POST/PUT/PATCH/DELETE` 系（推し作成・更新・削除、
  チャンネル登録・解除・設定変更）にのみ適用する。

- **Rationale**: 仕様 FR-016「認証済み操作への濫用防止レート制限」。Feature 001 の既存パターンを踏襲。

- **Alternatives considered**:
  - グローバルな `throttle:api` のみ: 粒度が粗く、変更操作の保護が不十分。→ドメイン別リミッタを採用。

## 5. 共有マスタ upsert パターン（複数ユーザーが同一チャンネルを登録する場合）

- **Decision**: `RegisterChannelAction` 内で以下の順序で処理する。

  1. 入力を解析・正規化して `ChannelInput` 値オブジェクトを生成。
  2. `youtube_channels` を `youtube_channel_id` で検索。
     - **存在する場合**: そのレコードを再利用（API 呼び出し不要、仕様 FR-005 / SC-004）。
     - **存在しない場合**: YouTube API で `channels.list` を呼び、`ResolvedChannel` を取得し、
       `youtube_channels` に INSERT する。
  3. `user_channels` の一意制約（`profile_id, youtube_channel_id`）を確認し、重複なら拒否（FR-007）。
  4. `user_channels` を INSERT（`is_main` は当該ユーザーの当該推しに他チャンネルがなければ `true`）。
  5. 推しの `user_channels` 件数を確認して `is_main` を決定する処理はトランザクション内で行う。

  共有マスタの更新（メタ情報の再同期）は後続フィーチャーの同期処理が担い、本フィーチャーでは
  初回 INSERT のみを行う（登録後は `sync_status = 'pending'`）。

- **Rationale**: 憲法 II「ユーザー単位でチャンネル情報を複製しない」に直接適合。
  API 呼び出しの最小化（クォータ節約）にもなる（憲法 V）。

- **Alternatives considered**:
  - 常に API を呼んで upsert: 重複 API 呼び出しが発生し、クォータを消費。→却下。
  - ユーザーごとに `youtube_channels` を複製: 憲法 II 違反。→却下。

## 確定した依存追加（composer / npm）

新規追加なし。`Http::fake()` は Laravel HTTP Client に内蔵されており、追加ライブラリ不要。
YouTube API 呼び出しは `Http::get()` を使用する。YouTube APIキーは `.env` 経由で `config/youtube.php` が管理する。

すべての NEEDS CLARIFICATION は解消済み。

# Tasks: Oshi & Channel Registration（推し・チャンネル登録）

**Input**: Design documents from `/specs/002-oshi-and-channel-registration/`

**Prerequisites**: plan.md ✅ / spec.md ✅ / research.md ✅ / data-model.md ✅ / contracts/ ✅ / quickstart.md ✅

**Tests**: 憲法 VI「テストファースト & 外部APIモック」により Feature/Unit テストは必須。
YouTube API は `Http::fake()` でモックし、実 API は呼ばない。

**Organization**: ユーザーストーリー別に整理し、各ストーリーが独立してテスト・デモ可能なインクリメントを提供する。

## Format: `[ID] [P?] [Story] Description`

- **[P]**: 並列実行可（異なるファイル、依存なし）
- **[Story]**: 対応ユーザーストーリー（US1〜US4）

---

## Phase 1: Setup（共有インフラ）

**Purpose**: YouTube API 接続設定の追加（Feature 001 の基盤に追加する最小セットアップ）

- [X] T001 YouTube API 設定ファイルを作成する `config/youtube.php`（`api_key` は env 経由、`base_url` を定義）
- [X] T002 `.env.example` に `YOUTUBE_API_KEY=` を追記する

---

## Phase 2: Foundational（ブロッキング前提条件）

**Purpose**: 全ユーザーストーリーが依存するマイグレーション・Enum・モデル・Policy・レートリミッターを確立する

**⚠️ CRITICAL**: このフェーズ完了まで、いかなるユーザーストーリーも開始できない

- [X] T003 [P] `OshiColor` backed Enum を作成する `app/Enums/OshiColor.php`（識別子: rose/pink/fuchsia/purple/violet/blue/cyan/teal/green/lime/yellow/orange/red/slate/gray、`label()` と `tailwindBg()` メソッドを実装）
- [X] T004 [P] `ChannelSyncStatus` backed Enum を作成する `app/Enums/ChannelSyncStatus.php`（ケース: pending/synced/error）
- [X] T005 `oshis` テーブルのマイグレーションを作成する `database/migrations/XXXX_create_oshis_table.php`（列: id uuid PK, profile_id uuid FK→profiles.id CASCADE, name varchar(100) NOT NULL, group_name varchar(100) nullable, color_id varchar(50) nullable, memo text nullable, timestamps。インデックス: profile_id）
- [X] T006 `youtube_channels` テーブルのマイグレーションを作成する `database/migrations/XXXX_create_youtube_channels_table.php`（列: id uuid PK, youtube_channel_id varchar(255) UNIQUE NOT NULL, title varchar(255) NOT NULL, description text nullable, handle varchar(100) nullable, thumbnail_url text nullable, uploads_playlist_id varchar(50) nullable, published_at timestamptz nullable, sync_status varchar(20) NOT NULL default 'pending', sync_error_message text nullable, last_synced_at timestamptz nullable, timestamps）
- [X] T007 `user_channels` テーブルのマイグレーションを作成する `database/migrations/XXXX_create_user_channels_table.php`（列: id uuid PK, profile_id uuid FK→profiles.id CASCADE, oshi_id uuid FK→oshis.id CASCADE, youtube_channel_id uuid FK→youtube_channels.id, is_main boolean NOT NULL default false, sync_enabled boolean NOT NULL default true, notify_enabled boolean NOT NULL default false, registered_at timestamptz NOT NULL default now(), timestamps。インデックス: UNIQUE(profile_id, youtube_channel_id), (profile_id, oshi_id), 部分ユニーク UNIQUE(profile_id, oshi_id) WHERE is_main=true）
- [X] T008 [P] `Oshi` モデルを作成する `app/Models/Oshi.php`（`$fillable`=name/group_name/color_id/memo、`$casts`=color_id→OshiColor::class、`belongsTo(Profile::class,'profile_id')`, `hasMany(UserChannel::class)`）
- [X] T009 [P] `YoutubeChannel` モデルを作成する `app/Models/YoutubeChannel.php`（`$fillable`=全列、`$casts`=sync_status→ChannelSyncStatus::class/published_at/last_synced_at→datetime、`hasMany(UserChannel::class)`）
- [X] T010 [P] `UserChannel` モデルを作成する `app/Models/UserChannel.php`（`$fillable`=全列、`$casts`=is_main/sync_enabled/notify_enabled→boolean/registered_at→datetime、`belongsTo(Oshi::class)`, `belongsTo(Profile::class,'profile_id')`, `belongsTo(YoutubeChannel::class)`）
- [X] T011 [P] `OshiPolicy` を作成する `app/Policies/OshiPolicy.php`（view/update/delete: `$oshi->profile_id === $user->id`、create: 認証済みなら許可）
- [X] T012 [P] `UserChannelPolicy` を作成する `app/Policies/UserChannelPolicy.php`（view/update/delete: `$userChannel->profile_id === $user->id`、create: 対象 Oshi の所有者かを確認）
- [X] T013 Policy 登録とレートリミッター定義を追加する `app/Providers/AppServiceProvider.php`（`Gate::policy(Oshi::class, OshiPolicy::class)` / `Gate::policy(UserChannel::class, UserChannelPolicy::class)` / `RateLimiter::for('oshi-mutations', ...)` 60リクエスト/分 per ユーザー）

**Checkpoint**: マイグレーション適用（`php artisan migrate`）と Enum・モデル・Policy が正常に動作すること

---

## Phase 3: User Story 1 - 推しを登録して管理する（Priority: P1）🎯 MVP

**Goal**: ユーザーが推しを作成・編集・削除でき、自分の推し一覧を確認できる（チャンネルなし状態）

**Independent Test**: ログイン後に推しを1人作成し、一覧に表示・編集・削除できれば単独でデモ可能

### Tests for US1（先にテストを書き、RED を確認してから実装へ進む）

- [X] T014 [P] [US1] 推し作成テストを作成する `tests/Feature/Oshi/CreateOshiTest.php`（正常作成・必須項目 name のバリデーション・パレット外 color_id の拒否・profile_id が auth から設定されることを確認）
- [X] T015 [P] [US1] 推し更新テストを作成する `tests/Feature/Oshi/UpdateOshiTest.php`（正常更新・バリデーション）
- [X] T016 [P] [US1] 推し削除テストを作成する `tests/Feature/Oshi/DeleteOshiTest.php`（正常削除・削除時に紐づく user_channels も消えることを確認）
- [X] T017 [P] [US1] 推し所有権テストを作成する `tests/Feature/Oshi/OshiOwnershipTest.php`（他ユーザーの推しへの view/update/delete が 403 になること・未認証は /login へリダイレクト）

### Implementation for US1

- [X] T018 [P] [US1] `StoreOshiRequest` を作成する `app/Http/Requests/StoreOshiRequest.php`（rules: name=required|max:100, group_name=nullable|max:100, color_id=nullable|Rule::enum(OshiColor::class), memo=nullable）
- [X] T019 [P] [US1] `UpdateOshiRequest` を作成する `app/Http/Requests/UpdateOshiRequest.php`（StoreOshiRequest と同一ルール）
- [X] T020 [US1] `CreateOshiAction` を作成する `app/Actions/Oshi/CreateOshiAction.php`（引数: Profile, StoreOshiRequest。`profile_id = $profile->id` でレコード作成。リクエストから profile_id を取得しない）
- [X] T021 [P] [US1] `UpdateOshiAction` を作成する `app/Actions/Oshi/UpdateOshiAction.php`（引数: Oshi, UpdateOshiRequest。`update()` のみ。所有権確認は Policy 側で実施済み前提）
- [X] T022 [P] [US1] `DeleteOshiAction` を作成する `app/Actions/Oshi/DeleteOshiAction.php`（引数: Oshi。`$oshi->delete()`。user_channels は CASCADE で削除。youtube_channels は削除しない）
- [X] T023 [US1] `OshiController` を作成する `app/Http/Controllers/OshiController.php`（メソッド: index/create/store/show/edit/update/destroy。index は `auth()->user()->oshis()->with(['userChannels'])->get()` で取得。各メソッドは Policy を authorize して Action を呼び出す薄い実装）
- [X] T024 [P] [US1] カラーピッカーコンポーネントを作成する `resources/views/components/oshi-color-picker.blade.php`（`OshiColor::cases()` を Alpine.js で選択 UI に描画。selected 状態をリングで表示）
- [X] T025 [P] [US1] 推し作成フォームビューを作成する `resources/views/oshis/create.blade.php`（name/group_name/color_id（カラーピッカーコンポーネント使用）/memo フィールド。app レイアウト使用）
- [X] T026 [P] [US1] 推し編集フォームビューを作成する `resources/views/oshis/edit.blade.php`（create と同じ構造、既存値を初期値として設定）
- [X] T027 [US1] 推し一覧ビューを作成する `resources/views/oshis/index.blade.php`（各推しのカード: name/group_name/color_id（テーマカラー表示）/チャンネル登録数。「推しを追加」ボタン。レスポンシブグリッド）
- [X] T028 [US1] 推し詳細ビューを作成する `resources/views/oshis/show.blade.php`（推し情報（name/group_name/color/memo）・編集/削除ボタン。Phase 4 でチャンネルセクションを追加予定のプレースホルダー付き）
- [X] T029 [US1] `/oshis` リソースルートを追加する `routes/web.php`（`Route::resource('oshis', OshiController::class)` を `auth.supabase` ミドルウェアグループ内に追加。PUT を throttle:oshi-mutations で保護）

**Checkpoint**: `php artisan test --filter=Oshi` が全 PASS。`/oshis` で推し一覧・作成・編集・削除が動作する

---

## Phase 4: User Story 2 - 推しの YouTube チャンネルを登録する（Priority: P1）

**Goal**: ユーザーが推しに YouTube チャンネルを URL または @handle で登録・解除できる

**Independent Test**: チャンネル URL を入力して登録し、推し詳細に表示されること・重複登録と無効入力が拒否されることを確認

### Tests for US2

- [X] T030 [P] [US2] URL 解析ユニットテストを作成する `tests/Unit/YouTube/ChannelInputParserTest.php`（各 URL 形式: `/channel/UC...`, `/@handle`, `/c/name`, `/user/name`, `@handle` 文字列を解析して正しい ChannelInput が生成されることをテスト。対応外形式は例外またはnullを返すことをテスト）
- [X] T031 [P] [US2] YouTube API リゾルバーユニットテストを作成する `tests/Unit/YouTube/ApiYouTubeChannelResolverTest.php`（`Http::fake` で channels.list のレスポンスをモック。正常取得・チャンネル未発見・429 エラー・5xx エラー・クォータ超過の各ケースをテスト）
- [X] T032 [P] [US2] チャンネル登録フィーチャーテストを作成する `tests/Feature/Channel/RegisterChannelTest.php`（`Http::fake` 使用。正常登録・共有マスタ再利用（2ユーザーが同一チャンネルを登録しても youtube_channels は1件）・重複登録拒否・対応外 URL 拒否・API エラー時のユーザー向けメッセージ）
- [X] T033 [P] [US2] チャンネル解除フィーチャーテストを作成する `tests/Feature/Channel/DeregisterChannelTest.php`（自分の登録のみ削除・youtube_channels は残存・メインチャンネル解除時の自動昇格・他ユーザーの登録は影響なし）

### Implementation for US2

- [X] T034 [P] [US2] `ChannelInput` 値オブジェクトを作成する `app/Services/YouTube/ChannelInput.php`（プロパティ: type（'channel_id'|'handle'|'username'）と value。静的ファクトリ `fromUrl(string $url): ?self` で URL を解析・正規化）
- [X] T035 [P] [US2] `ResolvedChannel` 値オブジェクトを作成する `app/Services/YouTube/ResolvedChannel.php`（プロパティ: youtube_channel_id/title/description/handle/thumbnail_url/uploads_playlist_id/published_at。channels.list レスポンスから生成する静的ファクトリ `fromApiResponse(array $item): self`）
- [X] T036 [P] [US2] `YouTubeChannelResolverInterface` を作成する `app/Services/YouTube/YouTubeChannelResolverInterface.php`（メソッド: `resolve(ChannelInput $input): ?ResolvedChannel`。テストでのモック差し替えを可能にする）
- [X] T037 [US2] `ApiYouTubeChannelResolver` を作成する `app/Services/YouTube/ApiYouTubeChannelResolver.php`（`YouTubeChannelResolverInterface` を実装。`Http::get()` で `channels.list?part=snippet,contentDetails&{param}={value}` を呼ぶ。429/5xx を `YouTubeApiException` でラップ。`search.list` は使用禁止）
- [X] T038 [US2] `AppServiceProvider` に DI バインディングを追加する `app/Providers/AppServiceProvider.php`（`$this->app->bind(YouTubeChannelResolverInterface::class, ApiYouTubeChannelResolver::class)`）
- [X] T039 [P] [US2] `StoreUserChannelRequest` を作成する `app/Http/Requests/StoreUserChannelRequest.php`（rules: channel_url=required|string|max:500。形式の詳細バリデーションは Action 内で行う）
- [X] T040 [US2] `RegisterChannelAction` を作成する `app/Actions/Channel/RegisterChannelAction.php`（手順: ① `ChannelInput::fromUrl()` で解析（失敗なら ValidationException）② `YoutubeChannel::where('youtube_channel_id', ...)` で共有マスタ検索 ③ なければ `YouTubeChannelResolverInterface->resolve()` 呼び出し（失敗なら ValidationException・API エラーと入力不正を区別） ④ `YoutubeChannel::firstOrCreate()` で upsert ⑤ `user_channels` の重複確認（重複なら ValidationException） ⑥ トランザクション内で `UserChannel::create()`・`is_main` は当該ユーザー/推しの既存件数で決定）
- [X] T041 [US2] `DeregisterChannelAction` を作成する `app/Actions/Channel/DeregisterChannelAction.php`（手順: ① `is_main` かつ残存チャンネルが存在する場合、`registered_at ASC` で最古の別チャンネルを取得して `is_main=true` に更新 ② `$userChannel->delete()`。youtube_channels は削除しない）
- [X] T042 [US2] `UserChannelController` を作成する `app/Http/Controllers/UserChannelController.php`（メソッド: store（Oshi に対する登録）, destroy（登録解除）。store は `authorize('create', [UserChannel::class, $oshi])` で所有権確認後 RegisterChannelAction を呼ぶ。destroy は `authorize('delete', $userChannel)` 後 DeregisterChannelAction を呼ぶ）
- [X] T043 [US2] `oshis/show.blade.php` にチャンネル登録フォームとチャンネル一覧セクションを追加する `resources/views/oshis/show.blade.php`（登録フォーム: channel_url テキスト入力 + 送信ボタン + エラー表示。チャンネル一覧: タイトル・サムネイル・「同期待ち」バッジ・解除ボタン。Phase 5/6 でさらに拡充）
- [X] T044 [US2] チャンネル登録・解除ルートを追加する `routes/web.php`（`POST /oshis/{oshi}/channels` → UserChannelController@store、`DELETE /oshis/{oshi}/channels/{userChannel}` → UserChannelController@destroy。throttle:oshi-mutations を適用）

**Checkpoint**: `php artisan test --filter=Channel` が全 PASS（Http::fake 使用）。推し詳細でチャンネル URL を入力して登録・解除が動作する

---

## Phase 5: User Story 3 - 登録した推しとチャンネルを一覧・確認する（Priority: P2）

**Goal**: 推し一覧と詳細ページでチャンネルの視覚的な情報（サムネイル・タイトル・同期状態・メイン表示）が確認できる

**Independent Test**: 推しとチャンネルを登録した状態で `/oshis` 一覧と `/oshis/{id}` 詳細を開き、チャンネル情報が正しく表示されることを確認

- [X] T045 [US3] `oshis/index.blade.php` を更新してメインチャンネルのサムネイル・タイトル・チャンネル登録数を表示する `resources/views/oshis/index.blade.php`（Eager load: `oshis()->with(['userChannels' => fn($q) => $q->where('is_main', true), 'userChannels.youtubeChannel'])` で N+1 防止。description は SELECT しない）
- [X] T046 [P] [US3] `OshiController@index` の Eager load クエリを最適化する `app/Http/Controllers/OshiController.php`（`with(['userChannels' => fn($q) => $q->where('is_main', true)->with('youtubeChannel')])` を適用。`OshiController@show` も `with(['userChannels.youtubeChannel'])` で最適化）
- [X] T047 [US3] `oshis/show.blade.php` のチャンネル一覧を拡充する `resources/views/oshis/show.blade.php`（各チャンネルにサムネイル画像・タイトル・handle・sync_status バッジ（pending=グレー/synced=グリーン/error=レッド）・is_main バッジを表示）

**Checkpoint**: `/oshis` と `/oshis/{id}` でチャンネル情報が正しく表示され、N+1 クエリが発生しないこと（`debugbar` 等で確認推奨）

---

## Phase 6: User Story 4 - チャンネルの同期・通知設定とメインチャンネルを管理する（Priority: P2）

**Goal**: 登録済みチャンネルの sync/notify トグル・メインチャンネル変更・所有権保護が機能する

**Independent Test**: 複数チャンネルを登録した状態でメイン変更・設定トグルが正しく保存され、他ユーザーの操作が拒否されることを確認

### Tests for US4

- [X] T048 [P] [US4] メインチャンネル管理テストを作成する `tests/Feature/Channel/MainChannelTest.php`（最初の登録で is_main=true になること・2件目は is_main=false・メイン変更後は新しい1件だけが true・メインチャンネルを解除すると最古のチャンネルが自動昇格すること・部分ユニークインデックスで複数 main が DB レベルで防止されることを確認）
- [X] T049 [P] [US4] 設定変更テストを作成する `tests/Feature/Channel/ChannelSettingsTest.php`（sync_enabled OFF で保存・notify_enabled ON で保存・notify 変更時に通知配信が発生しないことを確認）
- [X] T050 [P] [US4] チャンネル所有権テストを作成する `tests/Feature/Channel/ChannelOwnershipTest.php`（他ユーザーの user_channel への PATCH/PUT/DELETE が 403 になること）

### Implementation for US4

- [X] T051 [P] [US4] `UpdateChannelSettingsRequest` を作成する `app/Http/Requests/UpdateChannelSettingsRequest.php`（rules: sync_enabled=nullable|boolean, notify_enabled=nullable|boolean。少なくとも1つ存在することをバリデーション）
- [X] T052 [US4] `SetMainChannelAction` を作成する `app/Actions/Channel/SetMainChannelAction.php`（DB::transaction 内で: ① 同一 profile_id/oshi_id の全 user_channels の is_main を false に update ② 指定 userChannel の is_main を true に update。部分ユニークインデックスが2重設定を阻止）
- [X] T053 [P] [US4] `UpdateChannelSettingsAction` を作成する `app/Actions/Channel/UpdateChannelSettingsAction.php`（sync_enabled/notify_enabled のみ更新。notify 配信は行わない・FR-012）
- [X] T054 [US4] `UserChannelController` に update と setMain メソッドを追加する `app/Http/Controllers/UserChannelController.php`（update: `authorize('update', $userChannel)` → UpdateChannelSettingsAction。setMain: `authorize('update', $userChannel)` → SetMainChannelAction。各 Action 後は `/oshis/{oshi}` へリダイレクト）
- [X] T055 [US4] `oshis/show.blade.php` に設定トグルとメイン指定 UI を追加する `resources/views/oshis/show.blade.php`（各チャンネル行に sync_enabled チェックボックス（PATCH フォーム）・notify_enabled チェックボックス（PATCH フォーム）・「メインに設定」ボタン（PUT フォーム、is_main=true のチャンネルはボタンを無効化またはバッジのみ表示））
- [X] T056 [US4] 設定変更・メイン指定ルートを追加する `routes/web.php`（`PATCH /oshis/{oshi}/channels/{userChannel}` → UserChannelController@update、`PUT /oshis/{oshi}/channels/{userChannel}/main` → UserChannelController@setMain。throttle:oshi-mutations を適用）

**Checkpoint**: `php artisan test` が全 PASS。メインチャンネル変更・設定トグルが推し詳細ページで動作する

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: N+1 防止・セキュリティ・レスポンシブ対応の最終確認

- [X] T057 レスポンシブ確認: PC とスマートフォン幅（375px・768px・1280px）で `/oshis` 一覧・`/oshis/create`・`/oshis/{id}` の各画面を確認し、Tailwind ブレークポイントで崩れがないことを修正する
- [X] T058 セキュリティチェックリストを確認する（YouTube API キーが HTML/JS に出力されないこと・CSRF が全フォームに機能すること・未認証アクセスが /login へリダイレクトされること・他ユーザーのリソースへのアクセスが 403 になること・API エラーの内部詳細がユーザー画面に表示されないこと）
- [X] T059 `quickstart.md` の全シナリオを手動またはテストで検証し、差分があれば修正する
- [X] T060 `php artisan test` を実行してテストスイート全体が GREEN になることを確認する

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup（Phase 1）**: 依存なし。即開始可能
- **Foundational（Phase 2）**: Phase 1 完了後。全 US をブロック
- **US1（Phase 3）**: Phase 2 完了後。US2/US3/US4 と独立
- **US2（Phase 4）**: Phase 2 完了後・US1 と並列可能だが、`show.blade.php` を共有するため US1 完了後が推奨
- **US3（Phase 5）**: US1 完了後（oshis/index, show を拡充するため）
- **US4（Phase 6）**: US2 完了後（user_channels が存在する前提）
- **Polish（Phase 7）**: 全 US 完了後

### User Story Dependencies

```
Phase 1 (Setup)
    ↓
Phase 2 (Foundational: migrations, enums, models, policies, rate limiter)
    ↓
Phase 3 (US1: 推し CRUD) ← MVP ここで止まることが可能
    ↓
Phase 4 (US2: チャンネル登録) ← show.blade.php を US1 のものを拡充
    ↓
Phase 5 (US3: 一覧・確認の拡充) ← index.blade.php と show.blade.php を拡充
    ↓
Phase 6 (US4: 設定・メイン管理)
    ↓
Phase 7 (Polish)
```

### Within Each Phase の並列実行

**Phase 2** で並列実行可能なもの:
- T003（OshiColor Enum）+ T004（ChannelSyncStatus Enum）— 同時作成可
- T008（Oshi モデル）+ T009（YoutubeChannel モデル）+ T010（UserChannel モデル）— マイグレーション後に並列で作成可
- T011（OshiPolicy）+ T012（UserChannelPolicy）— 同時作成可

**Phase 3** で並列実行可能なもの:
- T014/T015/T016/T017（各テストファイル）— 同時作成可
- T018（StoreOshiRequest）+ T019（UpdateOshiRequest）— 同時作成可
- T020/T021/T022（各 Action）— 同時作成可

**Phase 4** で並列実行可能なもの:
- T030/T031/T032/T033（各テストファイル）— 同時作成可
- T034/T035/T036（値オブジェクト・Interface）— 同時作成可

---

## Parallel Example: Phase 2（Foundation）

```bash
# マイグレーション作成を直列で（依存順）:
T005 → T006 → T007

# マイグレーション作成後、以下を並列で:
Task: T003 OshiColor Enum
Task: T004 ChannelSyncStatus Enum

# php artisan migrate 後、以下を並列で:
Task: T008 Oshi モデル
Task: T009 YoutubeChannel モデル
Task: T010 UserChannel モデル
Task: T011 OshiPolicy
Task: T012 UserChannelPolicy
```

## Parallel Example: User Story 4（Phase 6）

```bash
# 以下を並列で作成:
Task: T048 MainChannelTest.php
Task: T049 ChannelSettingsTest.php
Task: T050 ChannelOwnershipTest.php
Task: T051 UpdateChannelSettingsRequest.php
Task: T053 UpdateChannelSettingsAction.php

# 上記完了後:
Task: T052 SetMainChannelAction.php
Task: T054 UserChannelController に update/setMain を追加
Task: T055 show.blade.php に設定 UI を追加
Task: T056 ルートを追加
```

---

## Implementation Strategy

### MVP First（User Story 1 のみ）

1. Phase 1: Setup（T001–T002）
2. Phase 2: Foundational（T003–T013）← ここが最重要ブロッカー
3. Phase 3: US1（T014–T029）
4. **STOP & VALIDATE**: 推しの作成・編集・削除・一覧が動作することを確認
5. 必要であればここでデモ・レビュー可能

### Incremental Delivery

1. Setup + Foundational → 基盤確立
2. US1 完了 → 推し管理 MVP デモ可能
3. US2 完了 → チャンネル登録が使えるコア機能完成
4. US3 完了 → 一覧・確認 UI が整備される
5. US4 完了 → 設定・メイン管理が揃い Feature 002 完結

---

## Notes

- `[P]` タスクは異なるファイルを対象とし、依存関係がないため並列実行可
- `[USN]` ラベルでどのユーザーストーリーに属するかを追跡
- 各フェーズのチェックポイントで `php artisan test` を実行し GREEN を確認してから次へ進む
- YouTube API 呼び出しは全テストで `Http::fake()` でモック（実 API 呼び出し禁止・憲法 VI）
- `profile_id` はリクエストボディから取得せず常に `auth()->id()` を使用（憲法 III）
- `description` 列は一覧クエリで SELECT しない（憲法 技術制約）

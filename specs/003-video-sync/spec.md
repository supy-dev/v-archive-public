# Feature Specification: 動画同期（Video Sync）

**Feature Branch**: `003-video-sync`

**Created**: 2026-06-20

**Status**: Draft

**Input**: Feature 3 — youtube_videos、初回同期Job、定期同期Scheduler、過去動画取得、クォータ対策、削除・非公開動画の管理

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - チャンネル登録後に最新動画が自動取得される（Priority: P1）

ユーザーが推しのYouTubeチャンネルを登録した直後、そのチャンネルの最新動画（最大50件）がバックグラウンドで自動的に取得され、動画一覧で確認できる状態になる。登録中にユーザーを待たせない。

**Why this priority**: 動画一覧・視聴ステータス管理（Feature 4以降）の前提となる。チャンネルを登録したのに動画が表示されないとサービスの価値が成立しない。

**Independent Test**: チャンネル登録後にJobが実行されると、`youtube_videos` に最大50件のレコードが作成され、推しの動画一覧ページで動画タイトル・サムネイル・公開日が表示できることで独立してテスト可能。

**Acceptance Scenarios**:

1. **Given** ユーザーが推しチャンネルを登録した, **When** バックグラウンドJobが完了した, **Then** そのチャンネルの最新50件の動画が一覧に表示される
2. **Given** 同一チャンネルを複数ユーザーが登録している, **When** いずれかのJobが実行された, **Then** `youtube_videos` は1チャンネルにつき1セットのみ保持され重複しない
3. **Given** 初回同期が完了していない, **When** 動画一覧を開く, **Then** 「同期中」の状態が表示される
4. **Given** YouTubeチャンネルが存在する, **When** 初回同期Jobが実行される, **Then** 動画タイトル・サムネイルURL・公開日時・動画尺・種別（live/archive/short等）が保存される
5. **Given** YouTube APIが一時的に5xxエラーを返した, **When** Jobがリトライした, **Then** APIキーをログに出力せずリトライし、最終的に成功または失敗を記録する

---

### User Story 2 - 登録チャンネルの新着動画が定期的に自動更新される（Priority: P1）

登録済みの全チャンネルに対して、一定時間ごと（30分〜1時間）に新着動画が自動で取得・追加される。同じチャンネルを多数のユーザーが登録していても、チャンネルごとに1回しか同期しない。

**Why this priority**: サービスの継続的な価値を提供する根幹機能。定期同期がなければユーザーは毎回手動操作が必要になり実用性が失われる。

**Independent Test**: スケジューラーを手動実行すると、既存の`youtube_videos`より新しい動画IDが追加され、既存レコードは重複しないことで独立してテスト可能。

**Acceptance Scenarios**:

1. **Given** 100チャンネルが登録されている, **When** 定期同期が実行される, **Then** 各チャンネルに対して重複なく1回ずつJobがdispatchされる
2. **Given** チャンネルの最新50件の中に既存の動画IDが含まれる, **When** 同期Jobが実行される, **Then** 既存のvideoIDに到達した時点で取得を終了し、新着分のみが追加される
3. **Given** 前回同期から30分が経過した, **When** スケジューラーが起動する, **Then** 全同期対象チャンネルの新着動画が取得される
4. **Given** 同一チャンネルの同期Jobが既に実行中, **When** スケジューラーが再起動する, **Then** 同一チャンネルの重複Jobは発行されない
5. **Given** 定期同期が成功した, **When** 動画一覧を開く, **Then** 最終同期日時が表示される

---

### User Story 3 - 過去の動画を遡って追加取得できる（Priority: P2）

ユーザーが動画一覧で「過去の動画をもっと見る」を実行したとき、既に取得済みの動画よりさらに古い動画をページ単位で追加取得できる。

**Why this priority**: 初回同期は最新50件に限定しているため、古いアーカイブを見たいユーザーには手動で追加取得できる手段が必要。ただし初回同期があれば最低限使えるため P2。

**Independent Test**: 「もっと見る」操作を実行するとFetchOlderYoutubeVideosJobがdispatchされ、既存より古い動画が`youtube_videos`に追加されることで独立してテスト可能。

**Acceptance Scenarios**:

1. **Given** チャンネルに初回同期済みの動画がある, **When** ユーザーが「過去の動画をもっと見る」を実行する, **Then** 取得済みの最古動画より古い動画が最大50件追加される
2. **Given** 過去動画取得が完了した, **When** `oldest_fetched_at` を確認する, **Then** 取得済み最古動画の公開日時が記録されている
3. **Given** チャンネルの全動画を取得し終えた, **When** 「もっと見る」を実行する, **Then** 「これ以上動画はありません」が表示される

---

### User Story 4 - 削除・非公開になった動画でもメモが保持される（Priority: P2）

YouTube側で動画が削除または非公開になった場合、`youtube_videos.is_available = false` に更新し、ユーザーが保存したメモ・タイムスタンプ等はそのまま保持される。

**Why this priority**: メモ保持はサービスの核心価値の一つだが、削除動画の検出処理はビジネスインパクトが比較的低い。

**Independent Test**: YouTube APIが404を返した動画IDに対してMarkUnavailableJobが実行されると、`is_available=false` に更新され、後続のメモはそのまま参照できることで独立してテスト可能。

**Acceptance Scenarios**:

1. **Given** YouTube上で動画が削除された, **When** MarkUnavailableYoutubeVideosJobが実行される, **Then** 該当動画の`is_available`が`false`に更新される
2. **Given** `is_available=false`の動画がある, **When** 動画一覧に表示される, **Then** 「この動画は現在YouTubeで再生できません。保存済みメモは引き続き確認できます。」と表示される
3. **Given** 動画が非公開になった, **When** 動画詳細を開く, **Then** メモ・タイムスタンプは引き続き表示される

---

### Edge Cases

- 同期中にYouTube APIが429（レート制限）を返した場合、exponential backoffでリトライし、APIキーをログに出さない
- uploads_playlist_idが取得できないチャンネル（削除済みチャンネルなど）は同期をスキップし`sync_error_message`に記録する
- ネットワーク障害など一時的なエラーでJobが失敗した場合、次の定期同期サイクルで再試行される
- 動画の尺（duration_seconds）をYouTube APIが返さない場合（プレミア公開前など）はnullで保存する
- ライブ配信中（live_status=live）の動画は尺が確定しないため、定期同期Job（SyncYoutubeChannelJob）が`live_status=live`の動画を検出した際に`RefreshYoutubeVideoDetailsJob`をdispatchし、動画尺・`actual_end_at`・`live_status`を更新する（30分以内のラグを許容）
- 1つのYouTubeチャンネルの動画がDBに存在しない場合（チャンネル削除等）、定期同期はそのチャンネルをスキップする

---

## Requirements *(mandatory)*

### Functional Requirements

**動画データ取得（基本）**

- **FR-001**: 登録チャンネルのuploads playlist（`playlistItems.list`）から最新動画を取得しなければならない（`search.list`は使用しない）
- **FR-002**: 動画詳細（尺・ライブステータス・公開状態等）は`videos.list`でまとめて取得しなければならない
- **FR-003**: 同一チャンネルの動画は`youtube_videos`に1レコードのみ保持し、ユーザーごとに複製してはならない
- **FR-004**: 動画レコードは`youtube_video_id`をキーにupsertで保存し、冪等でなければならない

**初回同期（US1）**

- **FR-005**: チャンネル登録後、初回同期Jobを非同期でdispatchしなければならない（登録画面をブロックしない）
- **FR-006**: 初回同期では最新50件を取得し、最大100件まで拡張可能でなければならない
- **FR-007**: 初回同期が完了するまで、動画一覧に「同期中」状態を表示しなければならない
- **FR-008**: 初回同期完了後、`youtube_channels.sync_status`を`synced`に更新しなければならない

**定期同期（US2）**

- **FR-009**: スケジューラーは30分ごとに全同期対象チャンネルのJobをdispatchしなければならない（`withoutOverlapping`必須）
- **FR-010**: 定期同期では既存の`youtube_video_id`に到達した時点で取得を終了しなければならない
- **FR-011**: 同一チャンネルの同期Jobが実行中の場合、重複してdispatchしてはならない
- **FR-012**: 最終同期日時（`last_synced_at`）を動画一覧で表示しなければならない
- **FR-013**: 同期対象チャンネルは`sync_enabled=true`の`user_channels`が存在するチャンネルに限定しなければならない

**過去動画取得（US3）**

- **FR-014**: ユーザーが明示的に「過去の動画をもっと見る」を実行したときのみ追加取得を行わなければならない
- **FR-015**: 追加取得には`oldest_page_token`（`youtube_channels`共有マスタに保存）を使用しなければならない。1人のユーザーの取得結果は同チャンネルを登録する全ユーザーが参照できる
- **FR-016**: 追加取得の最古取得日時を`youtube_channels.oldest_fetched_at`に保持しなければならない（チャンネル単位、共有）

**削除・非公開動画（US4）**

- **FR-017**: YouTube側で削除・非公開になった動画の`is_available`を`false`に更新しなければならない（1日1回・深夜スケジュールで実行）
- **FR-018**: `is_available=false`の動画に対して、ユーザーのメモ・タイムスタンプを削除してはならない
- **FR-019**: `is_available=false`の動画は「再生不可」として一覧・詳細に表示しなければならない

**エラーハンドリング・セキュリティ**

- **FR-020**: 429・5xxと入力不正（存在しないチャンネルID等）を区別し、適切にハンドリングしなければならない
- **FR-021**: APIエラー時はexponential backoffでリトライしなければならない
- **FR-022**: 失敗内容は`sync_error_message`に記録しなければならない
- **FR-023**: YouTube APIキーをログへ出力してはならない
- **FR-024**: 同期処理の進捗・エラーはサーバー側ログに記録しなければならない（チャンネルID・Job名・HTTPステータス・処理時間）
- **FR-025**: 手動同期（将来対応の場合）にはレート制限を設けなければならない

### Key Entities *(include if feature involves data)*

- **youtube_videos（共有マスタ）**: 全ユーザー共通の動画メタ情報。youtube_video_id（ユニーク）、タイトル、description（先頭500文字、nullable）、サムネイルURL、公開日時、動画尺（秒）、video_type（archive/live/upcoming/short/video/unknown）、live_status（none/upcoming/live/completed/unknown）、is_available（bool）、last_fetched_at等を保持する。ユーザー固有状態は含まない。

- **SyncYoutubeChannelJob**: 対象チャンネルのuploads playlistを呼び出し、新着動画をupsertするJob。冪等・重複防止・リトライ対応。

- **InitialSyncYoutubeChannelJob**: チャンネル登録直後に発行される初回同期Job。最新50件を取得してupsert。SyncYoutubeChannelJobとロジックを共用または継承。

- **FetchOlderYoutubeVideosJob**: ユーザー操作で発行される過去動画取得Job。oldest_page_tokenを使いページネーションで遡る。

- **MarkUnavailableYoutubeVideosJob**: 既存動画IDをAPIに問い合わせ、削除・非公開になったものを`is_available=false`に更新する。**スケジュール**: 1日1回・深夜に実行（定期同期の30分サイクルとは独立。クォータ節約と削除検出の即時性トレードオフを許容する）。

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: チャンネル登録からバックグラウンドで動画が取得され、ユーザーが一覧を開いたときに最大50件の動画が表示されるまでのユーザー体感待ち時間が60秒以内である
- **SC-002**: 同一チャンネルを1,000ユーザーが登録しても、そのチャンネルの定期同期は1回しか実行されない（重複同期ゼロ）
- **SC-003**: `youtube_videos` テーブルに同一`youtube_video_id`の重複レコードが存在しない（upsert冪等性100%）
- **SC-004**: 定期同期の1サイクルで新着動画がDBに反映されるまでの時間が30分以内である
- **SC-005**: APIエラー発生時にYouTube APIキーがシステムログに出力されない（監査ログでゼロ件）
- **SC-006**: YouTube側で削除・非公開になった動画について、ユーザーが保存したメモが消えない（メモ保持率100%）

---

## Clarifications

### Session 2026-06-20

- Q: `youtube_videos` のプライマリキー型は uuid か bigint か → A: uuid（Feature 2の`youtube_channels`と統一し一貫性を保つ）
- Q: `youtube_videos.description` の保存方針は → A: 先頭500文字に切り詰めて保存（`varchar(500) nullable`）
- Q: `MarkUnavailableYoutubeVideosJob` の実行スケジュールは → A: 1日1回・深夜に実行（定期同期とは独立した低頻度バッチ）
- Q: `RefreshYoutubeVideoDetailsJob` のトリガー方式は → A: 定期同期Job内で`live_status=live`の動画を検出し自動dispatch（30分ラグ許容）
- Q: `oldest_page_token` の所有スコープはチャンネル単位か・ユーザー単位か → A: チャンネル単位（`youtube_channels`共有マスタに保存、全ユーザーが恩恵を受ける）

---

## Assumptions

- Feature 2（oshi-and-channel-registration）が完了しており、`youtube_channels`・`user_channels`・`oshis`テーブルが存在する
- `youtube_channels`には`uploads_playlist_id`・`sync_status`・`sync_error_message`・`last_synced_at`が既に存在する（Feature 2で追加済み）
- `youtube_channels`に`oldest_page_token`と`oldest_fetched_at`カラムを本Featureで追加する
- `youtube_videos`のプライマリキーはuuid（Feature 2の`youtube_channels`と統一。開発指示書§7.5のbigintより優先し、後続Feature全体でUUIDに統一する）
- ローカル開発ではLaravel Queue driverを`sync`（同期実行）またはDockerでRedis/Databaseドライバーを使用する
- 本番環境のQueue workerはSupabaseまたはサーバー上で常駐稼働しているものとする
- `youtube_videos`は全ユーザー共通の共有マスタであり、ユーザー固有の視聴状態は Feature 4（archive-and-watchlist）で追加する`user_watch_items`で管理する
- 動画の説明文（description）は先頭500文字に切り詰めて保存する（`varchar(500) nullable`。全文保存によるストレージ肥大化を避け、後から再取得するAPIクォータコストも排除する）
- 手動同期ボタン（ユーザーによるトリガー）はMVPスコープに含めるが、Feature 4以降の画面設計に委ねる
- `RefreshYoutubeVideoDetailsJob`（ライブ配信終了後の動画詳細更新）はMVPの基本同期に含める。トリガーは定期同期Job（SyncYoutubeChannelJob）内での`live_status=live`検出時（独立スケジューラー不要、30分ラグ許容）
- テスト環境ではYouTube API実呼び出しを行わず、モックを使用する

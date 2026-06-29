# Feature Specification: プレイヤーと再生進捗管理

**Feature Branch**: `005-player-and-playback`

**Created**: 2026-06-20

**Status**: Draft

**Input**: User description: "Feature 5のスペックを作成してください！"

## Clarifications

### Session 2026-06-20

- Q: `skipped`ステータスの動画で再生を開始した場合、ステータスはどうなるか → A: `skipped` のまま変化しない（`once skipped, stays skipped`）
- Q: デスクトップ（960px以上）の配信詳細ページレイアウトはどちらか → A: 上下スタック（上：プレイヤー、下：動画情報・操作UI）— モバイルと同じ縦構成
- Q: 配信詳細ページの URL ルート構造はどれか → A: `/archives/{watchItem}`（watch_item ID ベース、所有権確認が自然にバインドされる）
- Q: 見るリスト未登録の動画の詳細ページに「見るリストへ追加」CTAを設置するか → A: 未登録動画の詳細ページは存在しない（Policy により 403、または Model Binding により 404）— 「追加」CTAは不要
- Q: `PATCH /watch-items/{watchItem}/position` にユーザー単位のレートリミットを設けるか → A: 設ける（具体的な数値は plan 段階で確定、目安は1分間に10リクエスト）

## User Scenarios & Testing *(mandatory)*

### User Story 1 - 配信詳細ページで動画を再生する (Priority: P1)

ユーザーはアーカイブ一覧または見るリストから動画を選択し、配信詳細ページでYouTube動画をアプリ内で再生できる。再生を開始すると、視聴ステータスが「見たい（want_to_watch）」から「視聴中（watching）」へ自動的に切り替わる。

**Why this priority**: アプリ内再生はこのFeatureの核心機能。これが動作しないと、再生位置保存・ステータス管理・続きから再生のすべてが成立しない。

**Independent Test**: アーカイブ詳細ページへアクセスしてプレイヤーが表示され、動画を再生したときにステータスが watching へ変わることを確認できる。

**Acceptance Scenarios**:

1. **Given** 「見たい」ステータスの watch_item がある状態で、**When** ユーザーが配信詳細ページを開いてプレイヤーで再生を開始すると、**Then** ステータスが「視聴中」へ変わり、再生開始日時（started_at）が記録される。
2. **Given** 「視聴中」・「視聴済み」・「スキップ済み」のステータスの watch_item がある状態で、**When** ユーザーが再生を開始すると、**Then** ステータスは変化せず、started_at も上書きされない（`once skipped, stays skipped`）。
3. **Given** 配信詳細ページを開いたとき、**When** プレイヤーが読み込まれると、**Then** 保存済みの再生位置（last_position_seconds）から続きが再生される。

---

### User Story 2 - 再生位置が自動的に保存される (Priority: P1)

再生中・一時停止・ページ離脱・動画終了のタイミングで、現在の再生位置がサーバーへ保存される。次回同じ動画を開いたとき、保存した位置から続きを再生できる。

**Why this priority**: 再生位置保存はこのFeatureの主要機能であり、視聴体験の根幹。定期保存・一時停止保存・離脱保存の組み合わせで信頼性を担保する必要がある。

**Independent Test**: 動画を途中まで再生して一時停止し、ページを再読込したとき保存位置から再生が始まることで検証できる。

**Acceptance Scenarios**:

1. **Given** 動画を再生中のとき、**When** 60秒が経過すると、**Then** 現在の再生位置がサーバーへ保存される（前回保存位置との差が5秒未満の場合は省略可）。
2. **Given** 動画を再生中のとき、**When** ユーザーが一時停止すると、**Then** 現在の再生位置が即座にサーバーへ保存される。
3. **Given** 動画を再生中のとき、**When** ユーザーがページを離脱すると、**Then** `keepalive: true` を使って最終再生位置の保存が試行される。
4. **Given** 動画が最後まで再生されたとき、**When** 動画終了イベントが検知されると、**Then** 最終位置が保存され、ステータスが「視聴済み（watched）」・watched_at が現在日時で記録される。
5. **Given** ステータスが「視聴済み」の動画を再生しているとき、**When** 定期保存が走ると、**Then** ステータスは「視聴中」へ戻らない（once watched, stays watched）。

---

### User Story 3 - 続きから再生する導線 (Priority: P2)

保存された再生位置を使って、アプリ内またはYouTube公式サイトで続きから再生できる導線が設けられている。

**Why this priority**: 視聴管理の本質的な価値である「続きから見る」を実現する。再生位置保存（P1）が前提だが、表示機能として独立してテスト可能。

**Independent Test**: 再生位置が保存済みの動画の詳細ページで「続きから再生」「YouTubeで続きから開く」ボタンが表示され、それぞれ正しい位置から再生開始できることで検証できる。

**Acceptance Scenarios**:

1. **Given** last_position_seconds が保存されている動画の詳細ページで、**When** 「アプリ内で続きから再生」を押すと、**Then** プレイヤーが保存位置（秒）にシークして再生を開始する。
2. **Given** last_position_seconds が保存されている動画の詳細ページで、**When** 「YouTubeで続きから開く」を押すと、**Then** `https://www.youtube.com/watch?v={VIDEO_ID}&t={LAST_POSITION_SECONDS}s` が新しいタブで開かれる。
3. **Given** last_position_seconds が保存されていない動画の詳細ページで、**When** ページを開くと、**Then** 動画は最初から再生される。

---

### User Story 4 - 配信詳細ページのレスポンシブ表示 (Priority: P2)

配信詳細ページはデスクトップとモバイルの両方で主要な操作が快適に行える。

**Why this priority**: ユーザーはスマートフォンからも視聴管理を行う想定であり、プレイヤーを含む詳細ページのレスポンシブ対応は視聴体験を左右する。

**Independent Test**: 390px幅（モバイル）と960px幅（デスクトップ）の両方でプレイヤーと動画情報が正常に表示され、ステータス変更・再生位置保存の操作ができることで検証できる。

**Acceptance Scenarios**:

1. **Given** デスクトップ（960px以上）で配信詳細ページを開いたとき、**When** ページが表示されると、**Then** プレイヤーが上部に全幅（最大幅制限付き）で表示され、動画情報・ステータス・操作UIがその下に続く上下スタックレイアウトで見やすく表示される。
2. **Given** モバイル（760px以下）で配信詳細ページを開いたとき、**When** ページが表示されると、**Then** プレイヤーが画面幅いっぱいに16:9で表示され、動画情報がその下にスクロールで確認できる。
3. **Given** モバイルで動画を再生中のとき、**When** ステータス変更やメモ操作を行うと、**Then** タップ領域が十分に広く、誤操作なく操作できる。

---

### User Story 5 - 視聴ステータスを手動で変更する (Priority: P3)

配信詳細ページからユーザーが視聴ステータス（視聴中・視聴済み・スキップ等）を手動で変更できる。

**Why this priority**: 自動ステータス更新（P1）と組み合わせることで管理の柔軟性が増すが、手動操作は自動更新が機能した後でも独立して追加できる。

**Independent Test**: 詳細ページのステータス変更UIで「視聴済み」を手動選択し、一覧ページへ戻ったときに反映されていることで確認できる。

**Acceptance Scenarios**:

1. **Given** 配信詳細ページを開いているとき、**When** ユーザーがステータス変更UIで「視聴済み」を選択すると、**Then** 現在の再生位置が保存され、ステータスが「視聴済み」へ変わる。
2. **Given** ステータスが「視聴済み」の動画に対して、**When** 定期保存リクエストが来ると、**Then** ステータスは「視聴中」へ戻らない。

---

### Edge Cases

- 動画がYouTube側で削除・非公開になった場合、プレイヤーはエラーを表示するが、動画情報・保存メモは引き続き閲覧できる。
- ネットワーク切断時に保存リクエストが失敗した場合、次の保存タイミングで再試行される（定期保存と離脱保存の組み合わせで担保）。
- last_position_seconds が動画の実際の時間を超える値を受け取った場合、サーバー側でバリデーションエラーとする（動画時間が取得できない場合は0以上のみ検証）。
- 複数タブで同じ動画を開いた場合、古いリクエストが新しい再生位置を上書きしないようサーバー側で制御する。
- watch_item が存在しない動画（見るリスト未登録）は `/archives/{watchItem}` のルートを生成できないため、配信詳細ページへはアクセスできない。存在しない watch_item へのアクセスは 404、他ユーザーの watch_item へのアクセスは Policy により 403 を返す。

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: システムは認証済みユーザーに対して、`/archives/{watchItem}` ルートで配信詳細ページを提供しなければならない。watch_item の所有権を Policy で確認し、他ユーザーの watch_item へのアクセスは拒否する。
- **FR-002**: 配信詳細ページはYouTube IFrame Player APIを使って動画をアプリ内で再生できなければならない。
- **FR-003**: プレイヤーは保存済みの last_position_seconds がある場合、そこからシークして再生を開始しなければならない。
- **FR-004**: ユーザーが再生を開始したとき、対応する watch_item のステータスが `want_to_watch` の場合のみ `watching` へ変更し、`started_at` が未設定の場合のみ現在日時を記録しなければならない。`watching`・`watched`・`skipped` の場合はステータスを変更してはならない（`once skipped, stays skipped`）。
- **FR-005**: 動画再生中は60秒ごとに再生位置をサーバーへ送信しなければならない（毎秒送信は禁止、差分5秒未満は省略可）。
- **FR-006**: 一時停止イベント（YT.PlayerState.PAUSED）検知時に現在の再生位置を即座にサーバーへ送信しなければならない。
- **FR-007**: ページ離脱時（pagehide イベント）に `keepalive: true` を使って最終再生位置の保存を試行しなければならない。
- **FR-008**: 動画終了イベント（YT.PlayerState.ENDED）検知時に最終位置を保存し、ステータスを `watched`、`watched_at` を現在日時で更新しなければならない。
- **FR-009**: 再生位置の更新APIはサーバー側で所有権（Policy）を確認し、他ユーザーのwatch_itemを更新できてはならない。
- **FR-010**: `watched` / `skipped` ステータスの watch_item は、定期保存リクエストによって `watching` へ自動的に戻ってはならない。
- **FR-011**: 古い再生位置保存リクエストが新しい再生位置を上書きしないようサーバー側で制御しなければならない。
- **FR-012**: 配信詳細ページには「アプリ内で続きから再生」「YouTubeで続きから開く」の導線を設けなければならない。
- **FR-013**: 「YouTubeで続きから開く」は `https://www.youtube.com/watch?v={VIDEO_ID}&t={LAST_POSITION_SECONDS}s` 形式のURLを新しいタブで開かなければならない。
- **FR-014**: 配信詳細ページはデスクトップ（960px以上）とモバイル（760px以下）の両方で主要操作が可能でなければならない。デスクトップ・モバイルともに上下スタックレイアウト（上：プレイヤー、下：動画情報・操作UI）とし、デスクトップではプレイヤーに最大幅を設けて中央寄せにしてよい。
- **FR-015**: モバイルではプレイヤーが画面幅いっぱいの16:9比率で表示されなければならない。
- **FR-016**: ユーザーは配信詳細ページで視聴ステータスを手動変更できなければならない。
- **FR-017**: 再生位置を保存するAPIエンドポイント（PATCH /watch-items/{watchItem}/position）は last_position_seconds を 0以上・動画時間以下でバリデーションし、成功時は204を返さなければならない。このエンドポイントにはユーザー単位のレートリミットを設け、悪用・バグによる連打を防ぐこと（具体的な上限値は plan 段階で確定）。
- **FR-018**: YouTube動画が削除・非公開になった場合もページは表示され、「現在YouTubeで再生できません」旨のメッセージを表示しなければならない。

### Key Entities *(include if feature involves data)*

- **user_watch_items**: ユーザーと動画の対応付け。`status`（watch / watching / watched / skipped）、`last_position_seconds`（最終再生位置・秒）、`started_at`（視聴開始日時）、`watched_at`（視聴完了日時）を持つ。Feature 4（archive-and-watchlist）で作成済み。
- **youtube_videos**: 動画メタ情報の共有マスタ。`video_id`（YouTube動画ID）、`title`、`duration_seconds`（動画時間）を持つ。Feature 3（video-sync）で作成済み。
- **PlaybackPositionController**: 再生位置更新を受け取るサーバー側エンドポイント。所有権確認・バリデーション・上書き防止ロジックを担う新規コンポーネント。

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: ユーザーが動画を途中まで再生してページを離れ、再び開いたとき、前回の再生位置の±10秒以内から再生が始まる。
- **SC-002**: 再生中の定期保存リクエストが60秒間隔（±5秒）で発生し、毎秒リクエストは発生しない。
- **SC-003**: 動画終了後に視聴ステータスが「視聴済み」へ自動更新され、watched_at が記録されている（自動化テストで検証可能）。
- **SC-004**: 他ユーザーの watch_item へのPATCHリクエストが403で拒否される（Feature Test で確認）。
- **SC-005**: 390px幅のモバイルと960px幅のデスクトップの両方でプレイヤーが表示でき、再生・一時停止・ステータス変更の主要操作が可能である。
- **SC-006**: YouTube側で非公開・削除された動画の詳細ページが表示でき、メモや動画情報を確認できる。
- **SC-007**: 古い（タイムスタンプが新しいものより前の）再生位置保存リクエストが受け付けられても、より新しい保存値が上書きされない。

## Assumptions

- Feature 4（archive-and-watchlist）が完了しており、`user_watch_items` テーブルと関連するモデル・Policyが存在する。
- Feature 3（video-sync）が完了しており、`youtube_videos` に `duration_seconds`・`video_id` 等の動画メタ情報が存在する。
- タイムスタンプメモ（timestamp_memos）・全体感想（video_notes）・タグ付与・神回登録は Feature 6（memos-and-tags）のスコープであり、本Featureには含まない。配信詳細ページのUIにメモ欄のプレースホルダーは設けてよいが、実際の保存・表示ロジックは含まない。
- YouTube IFrame Player API はクライアントサイドJavaScript（Alpine.js + Vanilla JS）で制御する。Reactなど別のSPAランタイムは追加しない。
- 再生位置の上書き防止は、クライアントが送信時刻または `last_position_seconds` の大小比較をサーバー側で行うことで実現する（楽観的ロックまたはシンプルな比較）。
- YouTube公式側の視聴履歴・再生進捗との同期は行わず、本サービス独自データとして管理する。
- 90%視聴で自動的に視聴済みとする機能はMVPでは任意とし、本Featureでは実装しない。
- ユーザーは事前にSupabase Authで認証済みであり、配信詳細ページは認証済みルートのみとする。

# Research: メモ・タグ・神回お気に入り

## R-001: Alpine.js メモリスト管理パターン

**Decision**: サーバーサイドレンダリングによる初期データを JSON で Alpine に渡し、CRUD 操作後はサーバー応答を受けてからリストを更新する（楽観的更新禁止）。

**Rationale**:
- spec FR-003a「サーバーへのリクエスト完了後にレスポンスを受け取り、成功した場合のみ Alpine.js でメモ一覧を更新」が明示的に楽観的更新を禁止している。
- `x-data="memoManager({{ json_encode($memos) }})"` でサーバー生成 JSON を初期状態として注入。Blade でレンダリングされた HTML ではなく Alpine の `memos` 配列がリストのソースオブトゥルースとなる。
- 個々の `<li>` を Blade がレンダリングしてからパースする方式は避ける（Alpine の管理外になり脆弱）。

**Alpine コンポーネント構造**:
```text
memoManager(initialMemos) {
  memos: initialMemos,          // {id, seconds, body, is_favorite, tags[]}
  editingId: null,              // 編集中のメモ ID
  editDraft: {},                // 編集中の一時コピー（元データを汚染しない）
  showCreateForm: false,        // 新規作成フォームの表示状態
  createDraft: {seconds, body, tagIds, newTagNames},
  createError: null,
  editError: null,
  submitting: false,
}
```

**Inline edit pattern**: `editingId === memo.id` でテンプレートを切り替え。PATCH 成功後 `memos.map()` でインプレース更新し `editingId = null`。

**Alternatives considered**: 楽観的更新（先にリスト更新してからサーバー送信）は spec が明示的に禁止。全ページリロードはユーザー体験が劣る。

---

## R-002: プレイヤーとメモコンポーネント間の現在位置共有

**Decision**: `youtubePlayer` Alpine コンポーネントが初期化時に `window.getCurrentYoutubePosition` を公開し、`memoManager` コンポーネントがそれを呼び出す。

**Rationale**:
- Alpine.js の `$dispatch` でイベントを飛ばす方法もあるが、「現在位置を取得」は pull 型の処理なので関数公開が直接的。
- 既存コードの `getCurrentPosition()` は closure 内のプライベート関数のため、グローバル公開に修正が必要。
- FR-002: 「現在の再生位置から5秒前」を初期値に設定 → `Math.max(0, getCurrentYoutubePosition() - 5)`。
- プレイヤーが未初期化（`is_available = false` 等）の場合は 0 を初期値とする（edge case）。

**変更範囲**: `show.blade.php` 内の `youtubePlayer()` 関数で `window.getCurrentYoutubePosition = getCurrentPosition;` を追加。

**Alternatives considered**: Alpine `$store` はグローバル状態管理に適しているが、Alpine v3 の `Alpine.store()` は Vite ビルドなしでは設定が複雑。シンプルな関数公開で十分。

---

## R-003: タグ選択・インライン作成 UI

**Decision**: システムタグをトグルチップとして表示、カスタムタグは Enter/カンマで追加するテキスト入力。両方の選択状態を JSON として fetch ボディに含めてメモ保存と同時送信。

**Rationale**:
- フォームが fetch/JSON ベース（FR-003a）なので、hidden input より fetch ボディ内の JSON フィールドが自然。
- spec FR-009「入力フォーム内でタグ名を直接入力し、インラインで新規作成」→ 別途タグ作成 API を呼ばず、メモ保存時に一括処理。
- `tag_ids: [uuid, ...]` + `new_tag_names: ["カスタム", ...]` を POST/PATCH ボディに含める。

**Alpine での管理**:
```text
createDraft: {
  seconds: 0,
  body: '',
  tagIds: [],       // システムタグ・既存ユーザータグの ID
  newTagNames: [],  // インライン入力された新規タグ名
  tagInput: '',     // テキスト入力の一時値
}
```

**サーバーサイド処理**（Action 内）:
1. `new_tag_names` の各名前を `Tag::firstOrCreate(['slug' => Str::slug($name), 'profile_id' => $userId])` でユーザー固有タグ作成
2. 作成された ID と `tag_ids` を合算
3. `$memo->tags()->sync($allTagIds)`

**Alternatives considered**: タグ専用の `/tags` エンドポイントで事前作成 → ネットワークラウンドトリップ増加 + 孤立タグ（紐付けなし）が生まれるリスク。

---

## R-004: 神回・お気に入り一覧のフィルタリング方式

**Decision**: サーバーサイドフィルタリング（GET クエリパラメータ）。Alpine.js は UI 状態管理のみ。

**Rationale**:
- Laravel モノリス + Blade の原則（SPA 化禁止）。
- フィルター状態がブラウザ URL に反映されるため、ブックマーク・リロードに対して堅牢。
- フィルターが 3 種類（推し別・タグ別・年月別）あり、組み合わせを Eloquent クエリで構築するほうが Alpine での後処理より明瞭。
- N+1 回避: `with(['tags', 'youtubeVideo.youtubeChannel', 'profile'])` で eager load。

**クエリパラメータ**:
- `?oshi_id={uuid}` — 推し別
- `?tag_id={uuid}` — タグ別
- `?month={YYYY-MM}` — 年月別

**Alternatives considered**: Alpine.js のクライアントサイドフィルタ（全件取得後 JS でフィルタ）→ 件数が増えるとパフォーマンス問題、URL に状態が残らない。

---

## R-005: 動画ノート保存 UI

**Decision**: Alpine.js で `fetch` を使ったインライン保存。成功後に「保存しました」メッセージをトースト風に表示（3秒で消える）。

**Rationale**:
- FR-005: 明示的な「保存」ボタン、FR-006: 本文空のとき無効化。
- Alpine の `saving: false` / `saved: false` フラグで状態管理。
- サーバーへは `PUT /archives/{watchItem}/note` で upsert（存在しなければ作成、あれば上書き）。
- レスポンスは `{ "status": "saved" }` の JSON。

**Alternatives considered**: 通常フォーム送信（ページリロード） → ページを再ロードするとプレイヤーも再ロードされ視聴体験が壊れる。よって AJAX 必須。

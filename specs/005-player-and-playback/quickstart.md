# Quickstart Validation Guide: プレイヤーと再生進捗管理 (Feature 005)

## 前提条件

- Feature 001〜004 が完了しており、ローカル環境でアプリが動作している
- Docker Compose が起動中（`docker compose up -d`）
- `php artisan migrate` が完了している
- テスト用ユーザーと少なくとも 1 件の watch_item（status: want_to_watch）が存在する
- `php artisan serve` または `./vendor/bin/sail up` でアプリが起動している

## シナリオ 1 — 配信詳細ページへのアクセス（SC-005 / FR-001）

1. ブラウザで `/watchlist` を開き、見るリストから任意の動画を選択
2. `GET /archives/{watchItem}` へ遷移し、ページが表示される（200 OK）
3. YouTube プレイヤーが表示され、動画が `last_position_seconds` から再生開始される

**期待結果**:
- プレイヤーが表示される（読み込み中はローディング表示）
- `last_position_seconds` が設定済みの場合、その位置からシーク開始（±3秒）
- デスクトップ（960px）でプレイヤーが上部に大きく表示される
- モバイル（390px）でプレイヤーが 16:9 全幅表示される

## シナリオ 2 — 再生開始でステータスが watching へ変わる（SC-003 / FR-004）

1. `status: want_to_watch` の watch_item の詳細ページを開く
2. 再生ボタンを押す
3. `/watchlist` へ戻り「視聴中」タブを確認する

**期待結果**:
- status が `watching` へ変わっている
- `started_at` が現在日時に設定されている
- 再度再生ボタンを押しても `started_at` は上書きされない

## シナリオ 3 — 再生位置の保存と続きから再生（SC-001）

1. 動画を 2〜3 分再生する
2. 一時停止する（または 60 秒待つ）
3. ページを再読み込みする

**期待結果**:
- 一時停止または 60 秒後に `PATCH /watch-items/{watchItem}/position` が送信される（DevTools Network タブで確認）
- 再読み込み後、前回停止位置（±10秒）から再生開始される

## シナリオ 4 — 動画終了で watched に遷移（SC-003 / FR-008）

1. 短い動画（または YT プレイヤーを手動で末尾へシーク）を最後まで再生する
2. `/watchlist` で「視聴済み」タブを確認する

**期待結果**:
- status が `watched` になっている
- `watched_at` が設定されている

## シナリオ 5 — 他ユーザーの watch_item へのアクセス拒否（SC-004）

テスト用に 2 ユーザーを用意し、ユーザー A でログインした状態でユーザー B の watch_item の UUID を URL に直接入力する。

**期待結果**:
- 403 レスポンスが返り、ページが表示されない

## シナリオ 6 — 所有権確認（SC-004）

1. 認証済み状態で別ユーザーの watchItem ID を使い `PATCH /watch-items/{otherUsersId}/position` をリクエストする
2. `curl -X PATCH https://localhost/watch-items/{uuid}/position -H "Content-Type: application/json" -d '{"last_position_seconds":100,"is_ended":false}' -H "X-CSRF-TOKEN: ..."` 相当

**期待結果**:
- 403 が返る
- 対象 watch_item の `last_position_seconds` は変わっていない

## シナリオ 7 — YouTube 非公開動画の表示（SC-006 / FR-018）

`youtube_videos.is_available = false` に手動更新した動画の詳細ページを開く。

**期待結果**:
- ページが 404 にならず表示される
- 「現在 YouTube で再生できません」旨のメッセージがプレイヤー領域に表示される
- 動画情報（タイトル・チャンネル名）は表示される

## 自動テストの実行

```bash
php artisan test tests/Feature/Playback/
```

全テストが緑（PASS）であることを確認する。

詳細なテストケースの一覧は `tasks.md`（`/speckit-tasks` で生成）を参照。

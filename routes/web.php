<?php

declare(strict_types=1);

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\Auth\AuthPageController;
use App\Http\Controllers\Auth\AuthSessionController;
use App\Http\Controllers\ChannelSyncController;
use App\Http\Controllers\ImportVideoController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\MemoController;
use App\Http\Controllers\OshiController;
use App\Http\Controllers\PlaybackPositionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TimestampMemoController;
use App\Http\Controllers\TimestampMemoFavoriteController;
use App\Http\Controllers\UserChannelController;
use App\Http\Controllers\UserWatchItemController;
use App\Http\Controllers\VideoNoteController;
use App\Http\Controllers\WatchItemFavoriteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 公開ページ（認証不要）
|--------------------------------------------------------------------------
| プライバシーポリシー・利用規約は未ログインでもアクセス可（FR-018・FR-019）。
*/
Route::get('/', [WelcomeController::class, '__invoke'])->name('welcome');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');

/*
|--------------------------------------------------------------------------
| LP用デモページ（認証不要・ハードコードデータ）
|--------------------------------------------------------------------------
| スクリーンショット取得専用。本番リリース後は削除するか別ドメインへ移す。
*/
Route::prefix('demo')->name('demo.')->group(function (): void {
    Route::get('/home', fn() => view('demo.home'))->name('home');
    Route::get('/archive', fn() => view('demo.archive-index'))->name('archive');
    Route::get('/watchlist', fn() => view('demo.watchlist'))->name('watchlist');
    Route::get('/archive-show', fn() => view('demo.archive-show'))->name('archive-show');
    Route::get('/favorites', fn() => view('demo.favorites'))->name('favorites');
});

/*
|--------------------------------------------------------------------------
| ゲスト（認証UI）ルート
|--------------------------------------------------------------------------
| フォーム送信はクライアントの Supabase JS が担い、これらは画面表示のみ。
*/
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthPageController::class, 'login'])->name('login');

    // GOOGLE_ONLY_LAUNCH: メール＋パスワード認証を再開するときは、以下の3ルートを戻す。
    // Route::get('/register', [AuthPageController::class, 'register'])->name('register');
    // Route::get('/forgot-password', [AuthPageController::class, 'forgotPassword'])->name('password.request');
    // Route::get('/reset-password', [AuthPageController::class, 'resetPassword'])->name('password.reset');
});

/*
|--------------------------------------------------------------------------
| セッションの確立 / 破棄
|--------------------------------------------------------------------------
| 不正トークン・ログイン連打を抑止するため POST にレート制限を付与（Edge Case L103）。
*/
Route::post('/auth/session', [AuthSessionController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('auth.session.store');

Route::delete('/auth/session', [AuthSessionController::class, 'destroy'])
    ->name('auth.session.destroy');

/*
|--------------------------------------------------------------------------
| 保護ルート（検証済み Supabase セッションを必要とする）
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.supabase', 'throttle:60,1'])->group(function (): void {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // 新着アーカイブ一覧（US1）
    Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');

    // 配信詳細ページ（Feature 005 US1）
    Route::get('/archives/{watchItem}', [ArchiveController::class, 'show'])->name('archives.show');

    // 見るリスト一覧（US2）
    Route::get('/watchlist', [UserWatchItemController::class, 'index'])->name('watchlist.index');
});

/*
|--------------------------------------------------------------------------
| 再生位置保存 API（認証必須 + playback-position レート制限）
|--------------------------------------------------------------------------
| Feature 005: 60秒ごと・一時停止・ページ離脱・動画終了時に呼ばれる（FR-017）。
| keepalive: true でページ離脱後のリクエストも受け付けるため独立グループにする。
*/
Route::middleware(['auth.supabase', 'throttle:playback-position'])->group(function (): void {
    Route::patch(
        '/watch-items/{watchItem}/position',
        [PlaybackPositionController::class, 'update']
    )->name('watch-items.position.update');
});

/*
|--------------------------------------------------------------------------
| 新着アーカイブ・見るリスト変更ルート（認証必須 + oshi-mutations レート制限）
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.supabase', 'throttle:oshi-mutations'])->group(function (): void {
    // 見るリスト追加・見送り（US1）
    Route::post('/archive/{video}/watch-item', [UserWatchItemController::class, 'store'])
        ->name('archive.watch-item.store');

    // ステータス変更・削除（US2）
    Route::patch('/watchlist/{userWatchItem}', [UserWatchItemController::class, 'update'])
        ->name('watchlist.update');
    Route::delete('/watchlist/{userWatchItem}', [UserWatchItemController::class, 'destroy'])
        ->name('watchlist.destroy');
});

/*
|--------------------------------------------------------------------------
| 推し・チャンネル登録ルート（認証必須 + 変更操作にレート制限）
|--------------------------------------------------------------------------
*/
Route::middleware('auth.supabase')->group(function (): void {
    // 推し CRUD（GET 系はレート制限なし、変更系は oshi-mutations リミッター適用）
    Route::get('/oshis', [OshiController::class, 'index'])->name('oshis.index');
    Route::get('/oshis/create', [OshiController::class, 'create'])->name('oshis.create');
    Route::get('/oshis/{oshi}', [OshiController::class, 'show'])->name('oshis.show');
    Route::get('/oshis/{oshi}/edit', [OshiController::class, 'edit'])->name('oshis.edit');

    Route::middleware('throttle:oshi-mutations')->group(function (): void {
        Route::post('/oshis', [OshiController::class, 'store'])->name('oshis.store');
        Route::put('/oshis/{oshi}', [OshiController::class, 'update'])->name('oshis.update');
        Route::delete('/oshis/{oshi}', [OshiController::class, 'destroy'])->name('oshis.destroy');

        // チャンネル登録・解除・設定変更・メイン指定
        Route::post(
            '/oshis/{oshi}/channels',
            [UserChannelController::class, 'store']
        )->name('oshis.channels.store');

        Route::delete(
            '/oshis/{oshi}/channels/{userChannel}',
            [UserChannelController::class, 'destroy']
        )->name('oshis.channels.destroy');

        Route::patch(
            '/oshis/{oshi}/channels/{userChannel}',
            [UserChannelController::class, 'update']
        )->name('oshis.channels.update');

        Route::put(
            '/oshis/{oshi}/channels/{userChannel}/main',
            [UserChannelController::class, 'setMain']
        )->name('oshis.channels.setMain');

    });
});

/*
|--------------------------------------------------------------------------
| 手動チャンネル同期（認証必須 + channel-sync 専用レート制限 5回/分）
|--------------------------------------------------------------------------
| YouTube API クォータを直接消費するため oshi-mutations（60回/分）より厳格制限（FR-004）。
*/
Route::middleware(['auth.supabase', 'throttle:channel-sync'])->group(function (): void {
    Route::post(
        '/oshis/{oshi}/channels/{userChannel}/fetch-older',
        [ChannelSyncController::class, 'fetchOlder']
    )->name('oshis.channels.fetchOlder');

    // 動画 URL からの単体取り込み（YouTube API を呼ぶため channel-sync リミッター適用）
    Route::get('/videos/import', [ImportVideoController::class, 'create'])->name('videos.import.create');
    Route::post('/videos/import', [ImportVideoController::class, 'store'])->name('videos.import.store');
});

/*
|--------------------------------------------------------------------------
| メモ・ノート・タグ操作ルート（認証必須 + memo-mutations レート制限）
|--------------------------------------------------------------------------
| Feature 006: タイムスタンプメモ CRUD・お気に入りトグル・動画ノート upsert/destroy
*/
Route::middleware(['auth.supabase', 'throttle:memo-mutations'])->group(function (): void {
    // タイムスタンプメモ CRUD（JSON レスポンス）
    Route::post(
        '/archives/{watchItem}/memos',
        [TimestampMemoController::class, 'store']
    )->name('archives.memos.store');

    Route::patch(
        '/archives/{watchItem}/memos/{memo}',
        [TimestampMemoController::class, 'update']
    )->name('archives.memos.update');

    Route::delete(
        '/archives/{watchItem}/memos/{memo}',
        [TimestampMemoController::class, 'destroy']
    )->name('archives.memos.destroy');

    // お気に入りトグル（JSON レスポンス）
    Route::patch(
        '/archives/{watchItem}/memos/{memo}/favorite',
        [TimestampMemoFavoriteController::class, 'update']
    )->name('archives.memos.favorite.update');

    // 神回フラグトグル（JSON レスポンス）
    Route::patch(
        '/archives/{watchItem}/favorite',
        [WatchItemFavoriteController::class, 'update']
    )->name('archives.watch-item.favorite.update');

    // 動画ノート upsert / destroy（JSON レスポンス）
    Route::put(
        '/archives/{watchItem}/note',
        [VideoNoteController::class, 'upsert']
    )->name('archives.note.upsert');

    Route::delete(
        '/archives/{watchItem}/note',
        [VideoNoteController::class, 'destroy']
    )->name('archives.note.destroy');
});

/*
|--------------------------------------------------------------------------
| 神回・お気に入り一覧（認証必須）
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.supabase', 'throttle:60,1'])->group(function (): void {
    Route::get('/favorites', [FavoriteController::class, 'index'])
        ->name('favorites.index');

    // タイムスタンプメモ保管庫（Feature 007 US3）
    Route::get('/memos', [MemoController::class, 'index'])
        ->name('memos.index');
});

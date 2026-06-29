<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>V-アーカイブ — 推しの配信を、自分だけの手帳に。</title>
    <meta name="description" content="VTuber・配信者ファンのための個人アーカイブツール。タイムスタンプメモ、見るリスト、神回保存。Googleアカウントだけで無料で始められます。">
    @vite('resources/css/lp.css')
</head>
<body>

{{-- ナビバー --}}
<header class="lp-nav" role="banner">
    <div class="lp-nav-inner">
        <a href="{{ route('welcome') }}" class="lp-logo" aria-label="V-アーカイブ トップへ">
            <i class="ph ph-sparkle ph-fill" aria-hidden="true"></i>
            <span>V-アーカイブ</span>
        </a>
        <nav class="lp-nav-links" aria-label="メインナビゲーション">
            <a href="#features">できること</a>
            <a href="#usecases">ユースケース</a>
            <a href="#steps">使い方</a>
        </nav>
        <a href="{{ route('login') }}" class="lp-btn lp-btn-outline" data-google-login>
            <i class="ph ph-google-logo" aria-hidden="true"></i>
            試してみる
        </a>
    </div>
</header>

{{-- ヒーロー --}}
<section class="lp-hero" aria-labelledby="hero-heading">
    <div class="lp-hero-inner lp-wrap">
        <div class="lp-hero-text">
            <span class="lp-eyebrow">VTuberアーカイブ手帳</span>
            <h1 class="lp-hero-h1" id="hero-heading">
                推しの配信、<br>
                <em>見たいまま</em><br>
                流れていかない。
            </h1>
            <p class="lp-hero-sub">
                V-アーカイブは、配信者やVTuberなどのYouTubeアーカイブをまとめて管理できるWebサービスです。気になる配信を「見るリスト」に保存し、視聴進捗を記録。タイムスタンプメモや感想を自分だけの形で残せます。
            </p>
            <div class="lp-hero-cta">
                <a href="{{ route('login') }}" class="lp-btn lp-btn-grad lp-btn-lg" data-google-login>
                    <i class="ph ph-google-logo" aria-hidden="true"></i>
                    試してみる
                </a>
                <span class="lp-hero-note">完全無料 · クレジットカード不要</span>
            </div>
        </div>

        {{-- アプリ UI モックアップ --}}
        <div class="lp-hero-visual" aria-hidden="true">
            <div class="lp-app">
                {{-- トップバー --}}
                <div class="lp-app-topbar">
                    <span class="lp-app-brand">
                        <i class="ph ph-sparkle ph-fill"></i> V-アーカイブ
                    </span>
                    <div class="lp-app-search">
                        <i class="ph ph-magnifying-glass"></i> アーカイブを検索…
                    </div>
                    <span class="lp-app-icon"><i class="ph ph-bell"></i></span>
                    <span class="lp-app-icon lp-app-icon--av"></span>
                </div>
                {{-- ボディ: サイドバー + メイン + 右パネル --}}
                <div class="lp-app-body">
                    {{-- 左サイドバー --}}
                    <aside class="lp-app-side">
                        <div class="lp-app-nav lp-app-nav--active"><i class="ph ph-house"></i><span>ホーム</span></div>
                        <div class="lp-app-nav"><i class="ph ph-sparkle"></i><span>新着</span></div>
                        <div class="lp-app-nav"><i class="ph ph-list-checks"></i><span>見るリスト</span></div>
                        <div class="lp-app-nav"><i class="ph ph-timer"></i><span>タイムスタンプ</span></div>
                        <div class="lp-app-nav"><i class="ph ph-crown"></i><span>神回</span></div>
                        <div class="lp-app-side-user">
                            <span class="lp-app-user-av"></span>
                            <span>マイページ</span>
                        </div>
                    </aside>
                    {{-- メインコンテンツ --}}
                    <main class="lp-app-main">
                        <span class="lp-app-cat-tag">雑談</span>
                        <div class="lp-app-player">
                            <div class="lp-app-player-ph">配信サムネイル</div>
                            <div class="lp-app-player-ctrl">
                                <div class="lp-app-pbar"><div class="lp-app-pbar-fill"></div></div>
                            </div>
                        </div>
                        <div class="lp-app-meta">
                            <span class="lp-app-av-sm"></span>
                            <span class="lp-app-ch-name">星乃ララ / Hoshino Lala</span>
                        </div>
                        <div class="lp-app-stats">
                            <div>配信日<b>2024/06/05</b></div>
                            <div>メモ<b>3件</b></div>
                            <div>ステータス<b>視聴中</b></div>
                            <div>進捗<b>35%</b></div>
                        </div>
                        <div class="lp-app-prog"><div class="lp-app-prog-fill"></div></div>
                        <div class="lp-app-prog-label"><span>視聴進捗 35%</span><span>00:42:15 / 2:18:45</span></div>
                        <div class="lp-app-crown-cta">
                            <i class="ph ph-crown ph-fill"></i> 神回に追加する
                        </div>
                    </main>
                    {{-- 右パネル --}}
                    <aside class="lp-app-right">
                        <div class="lp-app-panel-hd">感想メモ <span>編集</span></div>
                        <div class="lp-app-memo">ゆるい雑談がやっぱり好きだった…！聞いてると自然とリラックスできる。しっかり見返そ〜！</div>
                        <div class="lp-app-panel-hd">タイムスタンプ <span>＋追加</span></div>
                        <div class="lp-app-ts-row"><span class="lp-app-ts">12:34</span> ここの消費意識w</div>
                        <div class="lp-app-ts-row"><span class="lp-app-ts">46:10</span> 神トークすぎる…</div>
                        <div class="lp-app-ts-row"><span class="lp-app-ts">1:12:05</span> この歌やばい！</div>
                    </aside>
                </div>
            </div>

            {{-- フローティングメモカード --}}
            <div class="lp-memo-card">
                <div class="lp-memo-oshi">
                    <span class="lp-memo-dot"></span>
                    <span class="lp-memo-channel">〇〇ちゃん / 歌枠</span>
                </div>
                <div class="lp-memo-ts">1:23:45</div>
                <div class="lp-memo-body">ここのトーク最高すぎる…絶対また見る</div>
            </div>

            {{-- フローティング見るリストバッジ --}}
            <div class="lp-watch-pill">
                <i class="ph ph-list-checks"></i>
                見るリスト <strong>12</strong>件
            </div>
        </div>
    </div>
</section>

{{-- あるある (Problems) --}}
<section class="lp-scene" aria-labelledby="scene-heading">
    <div class="lp-wrap">
        <div class="lp-section-head">
            <span class="lp-eyebrow">あるある</span>
            <h2 class="lp-sh" id="scene-heading">こんなこと、ありませんか？</h2>
            <p class="lp-st">視聴ライフをもっと快適にするために、V-アーカイブが生まれました。</p>
        </div>
        <div class="lp-scene-grid">
            <div class="lp-scene-card lp-fadein">
                <span class="lp-scene-pic"><i class="ph ph-clock" aria-hidden="true"></i></span>
                <span>長時間配信、どこまで見たか分からなくなる</span>
            </div>
            <div class="lp-scene-card lp-fadein">
                <span class="lp-scene-pic"><i class="ph ph-broadcast" aria-hidden="true"></i></span>
                <span>あとで見ようと思った配信が流れていく</span>
            </div>
            <div class="lp-scene-card lp-fadein">
                <span class="lp-scene-pic"><i class="ph ph-note-pencil" aria-hidden="true"></i></span>
                <span>コメント欄に書くほどではない感想を残したい</span>
            </div>
            <div class="lp-scene-card lp-fadein">
                <span class="lp-scene-pic"><i class="ph ph-magnifying-glass" aria-hidden="true"></i></span>
                <span>好きな場面をもう一度探すのが大変</span>
            </div>
            <div class="lp-scene-card lp-fadein">
                <span class="lp-scene-pic"><i class="ph ph-music-notes" aria-hidden="true"></i></span>
                <span>歌枠の好きな曲だけ見返したい</span>
            </div>
            <div class="lp-scene-card lp-fadein">
                <span class="lp-scene-pic"><i class="ph ph-scissors" aria-hidden="true"></i></span>
                <span>切り抜き候補のタイムスタンプを忘れる</span>
            </div>
        </div>
    </div>
</section>

{{-- できること (Features) --}}
<section class="lp-features" id="features" aria-labelledby="features-heading">
    <div class="lp-wrap">
        <div class="lp-section-head">
            <span class="lp-eyebrow">できること</span>
            <h2 class="lp-sh" id="features-heading">V-アーカイブでできること</h2>
            <p class="lp-st">推しの配信を、あなたのペースで、もっと楽しく。</p>
        </div>
        <div class="lp-feat-grid">
            {{-- 新着アーカイブ管理 --}}
            <div class="lp-feat-card lp-fadein">
                <h3 class="lp-feat-title"><span class="lp-feat-bar"></span>新着アーカイブを管理</h3>
                <span class="lp-feat-screen"><i class="ph ph-monitor" aria-hidden="true"></i>新着アーカイブ一覧</span>
                <button class="lp-feat-img-link" type="button" data-modal-image="/images/demo/feature-1.png" data-modal-title="新着アーカイブ一覧" data-modal-alt="新着アーカイブ一覧の画面">
                    <span class="lp-feat-img-frame">
                        <img src="/images/demo/feature-1.png" alt="新着アーカイブ一覧の画面">
                        <span class="lp-feat-zoom"><i class="ph ph-magnifying-glass" aria-hidden="true"></i>拡大</span>
                    </span>
                </button>
                <ul class="lp-feat-points">
                    <li>登録したチャンネルの新着を一覧で確認</li>
                    <li>あとで見る配信をすぐ保存</li>
                </ul>
                <p class="lp-feat-body">観たいチャンネルをまとめて登録。新着アーカイブを見逃さずチェック。</p>
            </div>
            {{-- 見るリスト --}}
            <div class="lp-feat-card lp-fadein">
                <h3 class="lp-feat-title"><span class="lp-feat-bar"></span>見るリストで管理</h3>
                <span class="lp-feat-screen"><i class="ph ph-monitor" aria-hidden="true"></i>見るリスト</span>
                <button class="lp-feat-img-link" type="button" data-modal-image="/images/demo/feature-2.png" data-modal-title="見るリスト" data-modal-alt="見るリストの画面">
                    <span class="lp-feat-img-frame">
                        <img src="/images/demo/feature-2.png" alt="見るリストの画面">
                        <span class="lp-feat-zoom"><i class="ph ph-magnifying-glass" aria-hidden="true"></i>拡大</span>
                    </span>
                </button>
                <ul class="lp-feat-points">
                    <li>未視聴・視聴中・視聴済みで整理</li>
                    <li>気分に合わせて次に見る配信を選べる</li>
                </ul>
                <p class="lp-feat-body">見たい配信をリストに追加して管理。未視聴・視聴中・視聴済みで整理。</p>
            </div>
            {{-- 視聴進捗 --}}
            <div class="lp-feat-card lp-fadein">
                <h3 class="lp-feat-title"><span class="lp-feat-bar"></span>視聴進捗を保存</h3>
                <span class="lp-feat-screen"><i class="ph ph-monitor" aria-hidden="true"></i>配信詳細</span>
                <button class="lp-feat-img-link" type="button" data-modal-image="/images/demo/feature-3.png" data-modal-title="視聴進捗" data-modal-alt="視聴進捗の画面">
                    <span class="lp-feat-img-frame">
                        <img src="/images/demo/feature-3.png" alt="視聴進捗の画面">
                        <span class="lp-feat-zoom"><i class="ph ph-magnifying-glass" aria-hidden="true"></i>拡大</span>
                    </span>
                </button>
                <ul class="lp-feat-points">
                    <li>どこまで見たかを配信ごとに保存</li>
                    <li>長時間配信も途中から戻りやすい</li>
                </ul>
                <p class="lp-feat-body">どこまで見たかを記録。途中で止めても続きから再開できる。</p>
            </div>
            {{-- タイムスタンプメモ --}}
            <div class="lp-feat-card lp-fadein">
                <h3 class="lp-feat-title"><span class="lp-feat-bar"></span>タイムスタンプメモを残す</h3>
                <span class="lp-feat-screen"><i class="ph ph-monitor" aria-hidden="true"></i>配信詳細</span>
                <button class="lp-feat-img-link" type="button" data-modal-image="/images/demo/feature-4.png" data-modal-title="タイムスタンプメモ" data-modal-alt="タイムスタンプメモの画面">
                    <span class="lp-feat-img-frame">
                        <img src="/images/demo/feature-4.png" alt="タイムスタンプメモの画面">
                        <span class="lp-feat-zoom"><i class="ph ph-magnifying-glass" aria-hidden="true"></i>拡大</span>
                    </span>
                </button>
                <ul class="lp-feat-points">
                    <li>好きな場面の時刻と感想を残せる</li>
                    <li>あとからメモ保管庫で探せる</li>
                </ul>
                <p class="lp-feat-body">気になった場面にタイムスタンプとメモを残して、あとで見返せる。</p>
            </div>
            {{-- 神回保存 --}}
            <div class="lp-feat-card lp-fadein">
                <h3 class="lp-feat-title"><span class="lp-feat-bar"></span>神回・お気に入りを振り返る</h3>
                <span class="lp-feat-screen"><i class="ph ph-monitor" aria-hidden="true"></i>神回一覧</span>
                <button class="lp-feat-img-link" type="button" data-modal-image="/images/demo/feature-5.png" data-modal-title="神回一覧" data-modal-alt="神回一覧の画面">
                    <span class="lp-feat-img-frame">
                        <img src="/images/demo/feature-5.png" alt="神回一覧の画面">
                        <span class="lp-feat-zoom"><i class="ph ph-magnifying-glass" aria-hidden="true"></i>拡大</span>
                    </span>
                </button>
                <ul class="lp-feat-points">
                    <li>特別な配信を神回として保存</li>
                    <li>お気に入りの場面だけ振り返れる</li>
                </ul>
                <p class="lp-feat-body">特に印象に残った配信を神回として保存。いつでも見返せる。</p>
            </div>
            {{-- 推し別管理 --}}
            <div class="lp-feat-card lp-fadein">
                <h3 class="lp-feat-title"><span class="lp-feat-bar"></span>推し別に管理する</h3>
                <span class="lp-feat-screen"><i class="ph ph-monitor" aria-hidden="true"></i>ホーム</span>
                <button class="lp-feat-img-link" type="button" data-modal-image="/images/demo/feature-6.png" data-modal-title="ホーム" data-modal-alt="推し別に整理されたホーム画面">
                    <span class="lp-feat-img-frame">
                        <img src="/images/demo/feature-6.png" alt="推し別に整理されたホーム画面">
                        <span class="lp-feat-zoom"><i class="ph ph-magnifying-glass" aria-hidden="true"></i>拡大</span>
                    </span>
                </button>
                <ul class="lp-feat-points">
                    <li>複数のチャンネルを色と名前で見分けられる</li>
                    <li>チャンネルごとの新着や進捗を確認</li>
                </ul>
                <p class="lp-feat-body">複数のチャンネルを登録しても見やすく管理。チャンネルごとにデータを整理。</p>
            </div>
        </div>
    </div>
</section>

{{-- ユースケース --}}
<section class="lp-usecases" id="usecases" aria-labelledby="uc-heading">
    <div class="lp-wrap">
        <div class="lp-section-head">
            <span class="lp-eyebrow">ユースケース</span>
            <h2 class="lp-sh" id="uc-heading">ユースケースで見る使い方</h2>
            <p class="lp-st">いろんなシーンで、あなたの推し活をサポートします。</p>
        </div>
        <div class="lp-uc-grid">
            <div class="lp-uc-card lp-fadein">
                <div class="lp-uc-top">
                    <div>
                        <h3 class="lp-uc-title">雑談配信</h3>
                        <p class="lp-uc-tagline">気になった話題をメモ</p>
                    </div>
                    <div class="lp-uc-pic"><i class="ph ph-note-pencil" aria-hidden="true"></i></div>
                </div>
                <p class="lp-uc-desc"><i class="ph ph-note-pencil" aria-hidden="true"></i>お気に入りのトークをタイムスタンプで記録。あとでゆっくり見返せる。</p>
            </div>
            <div class="lp-uc-card lp-fadein">
                <div class="lp-uc-top">
                    <div>
                        <h3 class="lp-uc-title">歌枠</h3>
                        <p class="lp-uc-tagline">好きな曲だけタイムスタンプで保存</p>
                    </div>
                    <div class="lp-uc-pic"><i class="ph ph-music-notes" aria-hidden="true"></i></div>
                </div>
                <p class="lp-uc-desc"><i class="ph ph-music-notes" aria-hidden="true"></i>好きな曲の開始時間をメモしてプレイリスト代わりに。何度でも楽しめる。</p>
            </div>
            <div class="lp-uc-card lp-fadein">
                <div class="lp-uc-top">
                    <div>
                        <h3 class="lp-uc-title">長時間ゲーム配信</h3>
                        <p class="lp-uc-tagline">途中で止めても続きから再開</p>
                    </div>
                    <div class="lp-uc-pic"><i class="ph ph-play" aria-hidden="true"></i></div>
                </div>
                <p class="lp-uc-desc"><i class="ph ph-play" aria-hidden="true"></i>数時間の配信も進捗を保存。忙しい日でも自分のペースで最後まで楽しめる。</p>
            </div>
            <div class="lp-uc-card lp-fadein">
                <div class="lp-uc-top">
                    <div>
                        <h3 class="lp-uc-title">記念配信</h3>
                        <p class="lp-uc-tagline">誕生日・周年・3D お披露目を神回として保存</p>
                    </div>
                    <div class="lp-uc-pic"><i class="ph ph-trophy" aria-hidden="true"></i></div>
                </div>
                <p class="lp-uc-desc"><i class="ph ph-trophy" aria-hidden="true"></i>特別な日の配信を神回登録。思い出をいつまでも大切に残せる。</p>
            </div>
            <div class="lp-uc-card lp-fadein">
                <div class="lp-uc-top">
                    <div>
                        <h3 class="lp-uc-title">切り抜き候補</h3>
                        <p class="lp-uc-tagline">あとで使いたい場面を忘れない</p>
                    </div>
                    <div class="lp-uc-pic"><i class="ph ph-scissors" aria-hidden="true"></i></div>
                </div>
                <p class="lp-uc-desc"><i class="ph ph-scissors" aria-hidden="true"></i>面白いシーンや名言をタイムスタンプでメモ。切り抜き作成がスムーズに。</p>
            </div>
            <div class="lp-uc-card lp-fadein">
                <div class="lp-uc-top">
                    <div>
                        <h3 class="lp-uc-title">見る専</h3>
                        <p class="lp-uc-tagline">コメントしない感想も自分だけのメモに残す</p>
                    </div>
                    <div class="lp-uc-pic"><i class="ph ph-pencil-simple" aria-hidden="true"></i></div>
                </div>
                <p class="lp-uc-desc"><i class="ph ph-pencil-simple" aria-hidden="true"></i>コメントしなくても、自分の感想をメモに残して推し活をもっと楽しく。</p>
            </div>
        </div>
    </div>
</section>

{{-- 使い方 (Steps) --}}
<section class="lp-steps" id="steps" aria-labelledby="steps-heading">
    <div class="lp-wrap">
        <div class="lp-section-head">
            <span class="lp-eyebrow">使い方</span>
            <h2 class="lp-sh" id="steps-heading">使い方はシンプル</h2>
        </div>
        <div class="lp-steps-row">
            <div class="lp-step">
                <div class="lp-step-n">1</div>
                <div class="lp-step-icon"><i class="ph ph-plus-circle" aria-hidden="true"></i></div>
                <p>推しのYouTubeチャンネルを登録</p>
                <span class="lp-step-arr" aria-hidden="true"><i class="ph ph-caret-right"></i></span>
            </div>
            <div class="lp-step">
                <div class="lp-step-n">2</div>
                <div class="lp-step-icon"><i class="ph ph-play" aria-hidden="true"></i></div>
                <p>新着アーカイブから見たい配信を追加</p>
                <span class="lp-step-arr" aria-hidden="true"><i class="ph ph-caret-right"></i></span>
            </div>
            <div class="lp-step">
                <div class="lp-step-n">3</div>
                <div class="lp-step-icon"><i class="ph ph-pencil-simple" aria-hidden="true"></i></div>
                <p>配信を視聴しながらメモ</p>
                <span class="lp-step-arr" aria-hidden="true"><i class="ph ph-caret-right"></i></span>
            </div>
            <div class="lp-step">
                <div class="lp-step-n">4</div>
                <div class="lp-step-icon"><i class="ph ph-timer" aria-hidden="true"></i></div>
                <p>タイムスタンプを残す</p>
                <span class="lp-step-arr" aria-hidden="true"><i class="ph ph-caret-right"></i></span>
            </div>
            <div class="lp-step">
                <div class="lp-step-n">5</div>
                <div class="lp-step-icon"><i class="ph ph-crown" aria-hidden="true"></i></div>
                <p>神回として保存してあとから振り返る</p>
            </div>
        </div>
    </div>
</section>


{{-- CTA バナー (カード形式) --}}
<section class="lp-cta-wrap" aria-labelledby="cta-heading">
    <div class="lp-wrap">
        <div class="lp-cta-card">
            <div class="lp-cta-text">
                <h2 class="lp-cta-h2" id="cta-heading">VTuberの配信を、<br>自分のペースで楽しむために。</h2>
                <p class="lp-st">推し活の記録が、手帳のように積み重なっていく。</p>
            </div>
            <div class="lp-cta-action">
                <a href="{{ route('login') }}" class="lp-btn lp-btn-grad lp-btn-lg" data-google-login>
                    <i class="ph ph-google-logo" aria-hidden="true"></i>
                    試してみる
                </a>
                <p class="lp-cta-note">Googleアカウントがあればすぐに始められます。</p>
            </div>
        </div>
    </div>
</section>

{{-- フッター (4カラム) --}}
<footer class="lp-footer" role="contentinfo">
    <div class="lp-wrap">
        <div class="lp-foot-grid">
            <div class="lp-foot-brand">
                <a href="{{ route('welcome') }}" class="lp-logo" aria-label="V-アーカイブ トップへ">
                    <i class="ph ph-sparkle ph-fill" aria-hidden="true"></i>
                    <span>V-アーカイブ</span>
                </a>
                <p>VTuberのYouTubeアーカイブをもっと便利に、もっと楽しく管理するWebサービスです。</p>
            </div>
            <div class="lp-foot-col">
                <h5>サービス</h5>
                <a href="#features">できること</a>
                <a href="#usecases">ユースケース</a>
                <a href="#steps">使い方</a>
            </div>
            <div class="lp-foot-col">
                <h5>サポート</h5>
                <a href="{{ route('legal.privacy') }}">プライバシーポリシー</a>
                <a href="{{ route('legal.terms') }}">利用規約</a>
            </div>
            <div class="lp-foot-col">
                <h5>フォロー</h5>
                <a href="https://github.com/supy-dev/v-archive-public" target="_blank" rel="noopener noreferrer">GitHub</a>
                <a href="https://x.com/supy_dev" target="_blank" rel="noopener noreferrer">X</a>
            </div>
        </div>
        <p class="lp-copy">&copy; 2025&ndash;2026 supy.</p>
    </div>
</footer>

{{-- モバイル固定 CTA バー --}}
<div class="lp-sticky-bar" data-sticky-cta>
    <a href="{{ route('login') }}" class="lp-btn lp-btn-grad" data-google-login>
        <i class="ph ph-google-logo" aria-hidden="true"></i>
        試してみる
    </a>
</div>

{{-- スクリーンショット拡大モーダル --}}
<div class="lp-image-modal" data-image-modal hidden>
    <button class="lp-image-modal__backdrop" type="button" data-modal-close aria-label="画像プレビューを閉じる"></button>
    <div class="lp-image-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="lp-image-modal-title">
        <div class="lp-image-modal__head">
            <h2 id="lp-image-modal-title">画面プレビュー</h2>
            <button class="lp-image-modal__close" type="button" data-modal-close aria-label="画像プレビューを閉じる">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="lp-image-modal__body">
            <img data-modal-img src="" alt="">
        </div>
    </div>
</div>

<script>
    (() => {
        const stickyCta = document.querySelector('[data-sticky-cta]');
        const hero = document.querySelector('.lp-hero');

        if (!stickyCta || !hero) {
            return;
        }

        const toggleStickyCta = () => {
            const heroBottom = hero.getBoundingClientRect().bottom;
            stickyCta.classList.toggle('is-visible', heroBottom < 96);
        };

        toggleStickyCta();
        window.addEventListener('scroll', toggleStickyCta, { passive: true });
        window.addEventListener('resize', toggleStickyCta);
    })();

    (() => {
        const modal = document.querySelector('[data-image-modal]');
        const modalImg = modal?.querySelector('[data-modal-img]');
        const modalTitle = modal?.querySelector('#lp-image-modal-title');
        const openButtons = document.querySelectorAll('[data-modal-image]');
        const closeButtons = modal?.querySelectorAll('[data-modal-close]');
        let lastFocused = null;

        if (!modal || !modalImg || !modalTitle || !closeButtons) {
            return;
        }

        const closeModal = () => {
            modal.hidden = true;
            document.body.classList.remove('is-modal-open');
            modalImg.removeAttribute('src');
            modalImg.alt = '';
            lastFocused?.focus();
        };

        const openModal = (button) => {
            lastFocused = button;
            modalTitle.textContent = button.dataset.modalTitle || '画面プレビュー';
            modalImg.src = button.dataset.modalImage;
            modalImg.alt = button.dataset.modalAlt || '';
            modal.hidden = false;
            document.body.classList.add('is-modal-open');
            modal.querySelector('.lp-image-modal__close')?.focus();
        };

        openButtons.forEach((button) => {
            button.addEventListener('click', () => openModal(button));
        });

        closeButtons.forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', (event) => {
            if (!modal.hidden && event.key === 'Escape') {
                closeModal();
            }
        });
    })();
</script>

</body>
</html>

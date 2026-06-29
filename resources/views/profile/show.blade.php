@extends('layouts.app', [
    'title' => '設定 | V-アーカイブ',
    'pageTitle' => '設定',
])

@section('content')
<div class="settings-page" x-data="{ active: window.location.hash || '#profile-settings' }">
    <x-page-heading
        eyebrow="SETTINGS"
        title="設定"
        description="プロフィールやログイン情報、V-アーカイブの利用状況を管理します。"
    />

    <section class="settings-profile-summary" aria-label="プロフィール概要">
        @if($profile->avatar_url)
            <img src="{{ $profile->avatar_url }}" alt="{{ $profile->display_name }}のアイコン">
        @else
            <span class="settings-avatar-fallback"><x-icon name="user" /></span>
        @endif
        <div>
            <h2>{{ $profile->display_name }}</h2>
            <p><x-icon name="google" />Googleでログイン中</p>
        </div>
        <dl>
            <div><dt>推し</dt><dd>{{ $profile->oshis_count }}</dd></div>
            <div><dt>チャンネル</dt><dd>{{ $profile->user_channels_count }}</dd></div>
        </dl>
    </section>

    @if(session('status'))
        <div class="settings-success" role="status"><x-icon name="check" />{{ session('status') }}</div>
    @endif

    <div class="settings-layout">
        <nav class="settings-nav" aria-label="設定メニュー">
            <a href="#profile-settings" :class="{ 'active': active === '#profile-settings' }" @click="active = '#profile-settings'">
                <x-icon name="user" /><span>プロフィール</span><x-icon name="chevron-right" />
            </a>
            <a href="#account-settings" :class="{ 'active': active === '#account-settings' }" @click="active = '#account-settings'">
                <x-icon name="gear" /><span>アカウント・ログイン</span><x-icon name="chevron-right" />
            </a>
            <a href="#sync-settings" :class="{ 'active': active === '#sync-settings' }" @click="active = '#sync-settings'">
                <x-icon name="refresh" /><span>同期設定</span><x-icon name="chevron-right" />
            </a>
            <a href="#data-settings" :class="{ 'active': active === '#data-settings' }" @click="active = '#data-settings'">
                <x-icon name="note" /><span>データとプライバシー</span><x-icon name="chevron-right" />
            </a>
        </nav>

        <div class="settings-content">
            <section id="profile-settings" class="settings-panel">
                <div class="settings-panel-heading">
                    <span><x-icon name="user" /></span>
                    <div><h2>プロフィール</h2><p>V-アーカイブ内で表示する情報を設定します。</p></div>
                </div>

                <form method="POST" action="{{ route('profile.update') }}" class="settings-form">
                    @csrf
                    @method('PATCH')

                    <div class="settings-avatar-row">
                        @if($profile->avatar_url)
                            <img src="{{ $profile->avatar_url }}" alt="">
                        @else
                            <span class="settings-avatar-fallback"><x-icon name="user" /></span>
                        @endif
                        <div><b>プロフィール画像</b><p>Googleアカウントの画像を使用しています。</p></div>
                    </div>

                    <label class="settings-field" for="display-name">
                        <span>表示名</span>
                        <input
                            id="display-name"
                            name="display_name"
                            type="text"
                            maxlength="100"
                            required
                            value="{{ old('display_name', $profile->display_name) }}"
                            @class(['field-control', 'has-error' => $errors->has('display_name')])
                        >
                        @error('display_name')<small class="settings-field-error">{{ $message }}</small>@enderror
                    </label>

                    <label class="settings-field" for="timezone">
                        <span>タイムゾーン</span>
                        <select id="timezone" name="timezone" class="field-control">
                            @foreach([
                                'Asia/Tokyo' => '日本標準時（Asia/Tokyo）',
                                'UTC' => '協定世界時（UTC）',
                                'America/Los_Angeles' => '太平洋時間（Los Angeles）',
                                'Europe/London' => '英国時間（London）',
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected(old('timezone', $profile->timezone) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('timezone')<small class="settings-field-error">{{ $message }}</small>@enderror
                    </label>

                    <div class="settings-form-actions">
                        <button type="submit" class="button-primary"><x-icon name="check" />変更を保存</button>
                    </div>
                </form>
            </section>

            <section id="account-settings" class="settings-panel">
                <div class="settings-panel-heading">
                    <span><x-icon name="gear" /></span>
                    <div><h2>アカウント・ログイン</h2><p>現在のログイン方法とセッションを確認できます。</p></div>
                </div>

                <dl class="settings-account-list">
                    <div>
                        <dt>ログイン方法</dt>
                        <dd><span class="settings-google-badge"><x-icon name="google" />Google</span></dd>
                    </div>
                    <div>
                        <dt>メールアドレス</dt>
                        <dd data-account-email>Googleアカウントから確認中…</dd>
                    </div>
                </dl>

                <div class="settings-future-note">
                    <x-icon name="info" />
                    <p><b>メールアドレスでのログインは現在停止中です。</b><span>今後このセクションからログイン方法を追加できる設計です。</span></p>
                </div>

                <button type="button" class="settings-logout" data-logout><x-icon name="external" />ログアウト</button>
            </section>

            <section id="sync-settings" class="settings-panel">
                <div class="settings-panel-heading">
                    <span><x-icon name="refresh" /></span>
                    <div><h2>同期設定</h2><p>登録チャンネルの取り込み状況をまとめて確認できます。</p></div>
                </div>

                <div class="settings-stat-grid">
                    <div><x-icon name="heart" weight="fill" /><span><b>{{ $profile->oshis_count }}</b><small>登録中の推し</small></span></div>
                    <div><x-icon name="refresh" /><span><b>{{ $syncEnabledCount }}</b><small>自動同期中</small></span></div>
                    {{-- NOTIFICATIONS_PAUSED: 通知配信の実装後に件数表示を戻す。 --}}
                    {{--
                    <div><x-icon name="bell" /><span><b>{{ $notifyEnabledCount }}</b><small>通知設定中</small></span></div>
                    --}}
                </div>

                <p class="settings-helper">チャンネルごとの同期設定は、各推しの詳細画面で変更できます。</p>
                <a href="{{ route('oshis.index') }}" class="settings-primary-link">推し・チャンネル設定を開く<x-icon name="chevron-right" /></a>
            </section>

            <section id="data-settings" class="settings-panel">
                <div class="settings-panel-heading">
                    <span><x-icon name="note" /></span>
                    <div><h2>データとプライバシー</h2><p>サービスの方針とデータの取り扱いを確認できます。</p></div>
                </div>

                <div class="settings-document-links">
                    <a href="{{ route('legal.privacy') }}"><span><x-icon name="gear" /><b>プライバシーポリシー</b></span><x-icon name="chevron-right" /></a>
                    <a href="{{ route('legal.terms') }}"><span><x-icon name="note" /><b>利用規約</b></span><x-icon name="chevron-right" /></a>
                    <a href="https://forms.gle/jYcZjbxfyuWqNLUP6" target="_blank" rel="noopener noreferrer"><span><x-icon name="external" /><b>お問い合わせ</b></span><x-icon name="chevron-right" /></a>
                </div>

                <div class="settings-danger-note">
                    <h3>アカウントの削除</h3>
                    <p>お手数ですが、アカウント削除をご希望の場合はサポートまでご連絡ください。</p>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection

import Alpine from 'alpinejs';
import {
    supabase,
    loginWithGoogle,
    logout,
    finalizeSession,
    // GOOGLE_ONLY_LAUNCH: メール認証再開時に以下の import を戻す。
    // loginWithPassword,
    // registerWithPassword,
    // resendConfirmation,
    // requestPasswordReset,
    // updatePassword,
} from './auth/supabase-client.js';

window.Alpine = Alpine;

Alpine.data('archiveHome', () => ({
    query: '',
    saved: [3, 4],
    matches(text) {
        return !this.query || text.toLocaleLowerCase().includes(this.query.toLocaleLowerCase());
    },
    hasResults() {
        return [...this.$root.querySelectorAll('.featured-grid .archive-card')]
            .some((card) => card.style.display !== 'none');
    },
    toggleSave(id) {
        this.saved = this.saved.includes(id)
            ? this.saved.filter((savedId) => savedId !== id)
            : [...this.saved, id];
    },
}));

Alpine.start();

const $ = (sel) => document.querySelector(sel);

function showError(message) {
    const el = $('[data-auth-error]');
    if (el) {
        el.textContent = message || 'エラーが発生しました。お手数ですが、もう一度お試しください。';
        el.classList.remove('hidden');
    }
}

function showNotice(message) {
    const el = $('[data-auth-notice]');
    if (el) {
        el.textContent = message;
        el.classList.remove('hidden');
    }
}

function goHome() {
    window.location.href = '/home';
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-auto-submit]').forEach((form) => {
        form.addEventListener('change', (event) => {
            const control = event.target;
            if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement)) return;

            const status = form.querySelector('[data-submit-status]');
            form.classList.add('is-submitting');
            control.setAttribute('aria-busy', 'true');
            if (status) status.textContent = '更新中…';
            form.requestSubmit();
        });
    });

    // Google OAuth ボタン
    $('[data-google-login]')?.addEventListener('click', () => loginWithGoogle());

    // OAuth リダイレクト後、Supabase がセッションを復元するので Laravel へ引き渡す。
    if ($('[data-google-login]')) {
        let sessionHandled = false;

        async function tryFinalizeSession(session) {
            if (sessionHandled || !session) return;
            sessionHandled = true;
            const result = await finalizeSession(session);
            if (result.ok) {
                goHome();
            } else {
                sessionHandled = false;
            }
        }

        // PKCE コード交換完了後に発火する SIGNED_IN を待つ（getSession() より先に呼ばれる場合の race condition 対策）
        supabase.auth.onAuthStateChange((event, session) => {
            if (event === 'SIGNED_IN') tryFinalizeSession(session);
        });

        // ページリフレッシュ時: localStorage に既存セッションがある場合
        supabase.auth.getSession().then(({ data }) => tryFinalizeSession(data.session));
    }

    // 設定画面では、認証プロバイダのメールアドレスを読み取り専用で表示する。
    const accountEmail = $('[data-account-email]');
    if (accountEmail) {
        supabase.auth.getSession().then(({ data }) => {
            accountEmail.textContent = data.session?.user?.email || 'Googleアカウント';
        });
    }

    /* GOOGLE_ONLY_LAUNCH:
     * メール＋パスワード認証を再開するときは、このブロックと上の import を戻す。

    // メール＋パスワードでログイン
    $('[data-login-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const result = await loginWithPassword(e.target.email.value, e.target.password.value);
        if (result.ok) {
            goHome();
        } else if (result.status === 403) {
            showNotice(result.error?.message || 'メール確認を完了してください。');
            $('[data-resend-confirmation]')?.classList.remove('hidden');
        } else {
            showError(result.error?.message || 'メールアドレスまたはパスワードが正しくありません。');
        }
    });

    // 新規登録
    $('[data-register-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (e.target.password.value.length < 8) {
            showError('パスワードは8文字以上で設定してください。');
            return;
        }
        const result = await registerWithPassword(e.target.email.value, e.target.password.value);
        if (result.ok) {
            showNotice('確認メールを送信しました。メール内のリンクから認証を完了してください。');
        } else {
            showError(result.error?.message || '登録できませんでした。');
        }
    });

    // 確認メールの再送
    $('[data-resend-confirmation]')?.addEventListener('click', async () => {
        const email = $('#email')?.value;
        if (email) {
            await resendConfirmation(email);
            showNotice('確認メールを再送しました。');
        }
    });

    // パスワード再設定の申請（アカウントの有無に依らず一様な応答）
    $('[data-forgot-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await requestPasswordReset(e.target.email.value);
        showNotice('ご入力のメールアドレスが登録されている場合、再設定用のリンクを送信しました。');
    });

    // パスワードの再設定
    $('[data-reset-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (e.target.password.value.length < 8) {
            showError('パスワードは8文字以上で設定してください。');
            return;
        }
        const { error } = await updatePassword(e.target.password.value);
        if (error) {
            showError(error.message);
        } else {
            showNotice('パスワードを更新しました。新しいパスワードでログインしてください。');
        }
    });
    */

    // ログアウト
    $('[data-logout]')?.addEventListener('click', () => logout());
});

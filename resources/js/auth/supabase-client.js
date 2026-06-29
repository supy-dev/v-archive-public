import { createClient } from '@supabase/supabase-js';

// ここで使うのはブラウザ公開可の anon 値のみ（憲法 IV: service role key は絶対に置かない）。
const supabaseUrl = import.meta.env.VITE_SUPABASE_URL;
const supabaseAnonKey = import.meta.env.VITE_SUPABASE_ANON_KEY;

export const supabase = createClient(supabaseUrl, supabaseAnonKey);

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * 検証済みの Supabase アクセストークンを Laravel に送り、セッションを確立する。
 * 呼び出し側が 204 / 401 / 403 / 422 で分岐できるよう Response を返す。
 */
async function establishLaravelSession(accessToken) {
    return fetch('/auth/session', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ access_token: accessToken }),
    });
}

/* GOOGLE_ONLY_LAUNCH:
 * 以下のメール認証関数は再開しやすいよう保持しているが、現在のUIからは呼び出さない。
 * Supabase Dashboard 側の Email provider も初期リリース中は無効にする。
 */

/** メール＋パスワードでログインし、トークンを Laravel へ渡す。 */
export async function loginWithPassword(email, password) {
    const { data, error } = await supabase.auth.signInWithPassword({ email, password });
    if (error) {
        return { ok: false, error };
    }
    return finalizeSession(data.session);
}

/** メール＋パスワードで新規登録する。ログイン前にメール確認が必須。 */
export async function registerWithPassword(email, password) {
    const { data, error } = await supabase.auth.signUp({ email, password });
    if (error) {
        return { ok: false, error };
    }
    // 確認が必要な場合、Supabase はまだセッションを返さない。
    return { ok: true, needsEmailConfirmation: !data.session };
}

/** Google OAuth（リダイレクト方式）。戻り先ページで finalizeSession() を再実行する。 */
export async function loginWithGoogle() {
    return supabase.auth.signInWithOAuth({
        provider: 'google',
        options: { redirectTo: window.location.origin + '/login' },
    });
}

/** 未確認アドレス宛に確認メールを再送する。 */
export async function resendConfirmation(email) {
    return supabase.auth.resend({ type: 'signup', email });
}

/** パスワード再設定メールを申請する（アカウントの有無に依らず一様なUX）。 */
export async function requestPasswordReset(email) {
    return supabase.auth.resetPasswordForEmail(email, {
        redirectTo: window.location.origin + '/reset-password',
    });
}

/** 再設定リンク経由で新しいパスワードを設定する。 */
export async function updatePassword(password) {
    return supabase.auth.updateUser({ password });
}

/** ログアウト: Supabase と Laravel の双方のセッションを破棄する（FR-008）。 */
export async function logout() {
    await supabase.auth.signOut();
    await fetch('/auth/session', {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    });
    window.location.href = '/login';
}

/** 既存の Supabase セッション（例: OAuth からの戻り）に対し Laravel セッションを確立する。 */
export async function finalizeSession(session) {
    if (!session) {
        return { ok: false, error: { message: 'セッションを取得できませんでした。' } };
    }
    const response = await establishLaravelSession(session.access_token);
    if (response.status === 204) {
        return { ok: true };
    }
    const body = await response.json().catch(() => ({}));
    return { ok: false, status: response.status, error: { message: body.message } };
}

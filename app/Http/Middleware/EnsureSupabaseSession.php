<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 確立済みの Laravel セッション（＝検証済み Supabase ユーザー）を必要とする
 * ルートを保護する。未認証のリクエストは /login へ送る（FR-002 / SC-002）。
 */
class EnsureSupabaseSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('web')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'ログインが必要です。'], Response::HTTP_UNAUTHORIZED);
            }

            return redirect()->guest('/login');
        }

        return $next($request);
    }
}

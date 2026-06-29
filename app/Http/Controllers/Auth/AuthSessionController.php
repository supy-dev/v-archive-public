<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\EstablishSessionAction;
use App\Actions\Auth\SyncProfileFromClaimsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\EstablishSessionRequest;
use App\Services\Auth\InvalidTokenException;
use App\Services\Auth\SupabaseJwtVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthSessionController extends Controller
{
    public function __construct(private readonly SupabaseJwtVerifier $verifier) {}

    /**
     * 検証済み Supabase アクセストークンを Laravel セッションへ引き換える。
     *
     * 本人識別は検証済みの `sub` のみから決定し、リクエストに含まれるユーザーIDは
     * 一切信用しない（FR-004）。トークン自体はログ出力しない（FR-011）。
     */
    public function store(
        EstablishSessionRequest $request,
        SyncProfileFromClaimsAction $syncProfile,
        EstablishSessionAction $establishSession,
    ): JsonResponse|Response {
        try {
            $claims = $this->verifier->verify($request->accessToken());
        } catch (InvalidTokenException) {
            return response()->json([
                'message' => 'ログインできませんでした。お手数ですが、もう一度お試しください。',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $claims->emailVerified) {
            return response()->json([
                'message' => 'メールアドレスの確認が完了していません。確認メールのリンクから認証を完了してください。',
            ], Response::HTTP_FORBIDDEN);
        }

        $profile = $syncProfile->execute($claims);
        $establishSession->execute($profile, $request);

        return response()->noContent();
    }

    /**
     * Laravel セッションを破棄する（ログアウト）。クライアントは併せて Supabase の
     * signOut() を呼び、双方のセッションを破棄する（FR-008）。
     */
    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}

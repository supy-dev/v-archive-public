<?php

declare(strict_types=1);

namespace App\Services\Auth;

interface SupabaseJwtVerifier
{
    /**
     * Supabase アクセストークンの署名と標準クレーム（iss / exp / aud / sub）を
     * 検証し、検証済みクレームを返す。
     *
     * @throws InvalidTokenException トークンが不正・署名無効・期限切れ、または
     *                               クレーム検証に失敗した場合。
     */
    public function verify(string $jwt): VerifiedClaims;
}

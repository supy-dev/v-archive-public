<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Auth\InvalidTokenException;
use App\Services\Auth\SupabaseJwtVerifier;
use App\Services\Auth\VerifiedClaims;

/**
 * 署名検証を行わず、あらかじめ用意したクレームを返す（または例外を投げる）テストダブル。
 * Feature テストが実 Supabase の JWKS に触れないようにする（憲法 VI）。
 */
class FakeSupabaseJwtVerifier implements SupabaseJwtVerifier
{
    public function __construct(private readonly VerifiedClaims|InvalidTokenException $result) {}

    public function verify(string $jwt): VerifiedClaims
    {
        if ($this->result instanceof InvalidTokenException) {
            throw $this->result;
        }

        return $this->result;
    }
}

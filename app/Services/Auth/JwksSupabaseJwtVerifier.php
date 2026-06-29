<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

/**
 * プロジェクトの JWKS（非対称鍵）を用いて Supabase アクセストークンを検証する。
 * 署名検証は必須であり、検証なしのデコードは禁止（憲法 III）。
 * 生のトークンはログ出力しない（憲法 IV）。
 */
class JwksSupabaseJwtVerifier implements SupabaseJwtVerifier
{
    private const CACHE_KEY = 'supabase:jwks';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly Cache $cache,
        private readonly string $jwksUrl,
        private readonly string $issuer,
        private readonly string $audience,
        private readonly int $cacheTtl = 3600,
    ) {}

    public function verify(string $jwt): VerifiedClaims
    {
        if (trim($jwt) === '') {
            throw new InvalidTokenException('Empty access token.');
        }

        try {
            $keys = JWK::parseKeySet($this->jwks());
            JWT::$leeway = 10; // Supabaseサーバーとのクロックスキュー対策（最大10秒）
            $decoded = JWT::decode($jwt, $keys);
        } catch (Throwable $e) {
            // ライブラリの失敗（期限切れ・署名不一致・不正形式）を単一のサーバ側理由に集約する。
            // トークンや内部詳細は外部に出さない。
            throw new InvalidTokenException('Token verification failed: '.$e->getMessage(), previous: $e);
        }

        $payload = (array) $decoded;

        $this->assertIssuer($payload);
        $this->assertAudience($payload);
        $this->assertSubject($payload);

        return VerifiedClaims::fromPayload($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function jwks(): array
    {
        return $this->cache->remember(self::CACHE_KEY, $this->cacheTtl, function (): array {
            $response = $this->http->acceptJson()->get($this->jwksUrl);

            if (! $response->successful()) {
                throw new InvalidTokenException('Unable to fetch JWKS.');
            }

            return $response->json();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertIssuer(array $payload): void
    {
        if (($payload['iss'] ?? null) !== $this->issuer) {
            throw new InvalidTokenException('Issuer mismatch.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertAudience(array $payload): void
    {
        $aud = $payload['aud'] ?? null;
        $audiences = is_array($aud) ? $aud : [$aud];

        if (! in_array($this->audience, $audiences, true)) {
            throw new InvalidTokenException('Audience mismatch.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertSubject(array $payload): void
    {
        if (! isset($payload['sub']) || ! is_string($payload['sub']) || $payload['sub'] === '') {
            throw new InvalidTokenException('Missing subject.');
        }
    }
}

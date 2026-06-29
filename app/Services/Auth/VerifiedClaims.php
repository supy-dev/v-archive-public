<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Supabase JWT の検証成功後に生成する不変の値オブジェクト。
 * 検証済みクレームのみを引き回し、生のトークンは verifier の外へ出さない。
 */
final readonly class VerifiedClaims
{
    public function __construct(
        public string $sub,
        public ?string $email,
        public bool $emailVerified,
        public ?string $name,
        public ?string $picture,
        public string $issuer,
        public string $audience,
        public int $expiresAt,
    ) {}

    /**
     * デコード済みの JWT ペイロード（連想配列）から生成する。
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $metadata = (array) ($payload['user_metadata'] ?? []);

        $audience = $payload['aud'] ?? '';
        if (is_array($audience)) {
            $audience = (string) ($audience[0] ?? '');
        }

        return new self(
            sub: (string) ($payload['sub'] ?? ''),
            email: isset($payload['email']) ? (string) $payload['email'] : null,
            emailVerified: (bool) ($payload['email_verified'] ?? $metadata['email_verified'] ?? false),
            name: isset($payload['name'])
                ? (string) $payload['name']
                : (isset($metadata['full_name']) ? (string) $metadata['full_name'] : (isset($metadata['name']) ? (string) $metadata['name'] : null)),
            picture: isset($payload['picture'])
                ? (string) $payload['picture']
                : (isset($metadata['avatar_url']) ? (string) $metadata['avatar_url'] : null),
            issuer: (string) ($payload['iss'] ?? ''),
            audience: (string) $audience,
            expiresAt: (int) ($payload['exp'] ?? 0),
        );
    }
}

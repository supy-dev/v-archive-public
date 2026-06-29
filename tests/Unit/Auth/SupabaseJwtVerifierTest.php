<?php

declare(strict_types=1);

use App\Services\Auth\InvalidTokenException;
use App\Services\Auth\JwksSupabaseJwtVerifier;
use Firebase\JWT\JWT;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;

const JWKS_URL = 'https://project.supabase.co/auth/v1/.well-known/jwks.json';
const ISSUER = 'https://project.supabase.co/auth/v1';
const KID = 'test-key-1';

function b64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * @return array{0: string, 1: array<string, mixed>} [秘密鍵PEM, 鍵の詳細]
 */
function makeRsaKey(): array
{
    $res = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($res, $privatePem);

    return [$privatePem, openssl_pkey_get_details($res)];
}

/**
 * @param  array<string, mixed>  $details
 * @return array<string, mixed>
 */
function jwksFor(array $details, string $kid = KID): array
{
    return ['keys' => [[
        'kty' => 'RSA',
        'alg' => 'RS256',
        'use' => 'sig',
        'kid' => $kid,
        'n' => b64url($details['rsa']['n']),
        'e' => b64url($details['rsa']['e']),
    ]]];
}

/**
 * @param  array<string, mixed>  $jwks
 */
function makeVerifier(array $jwks): JwksSupabaseJwtVerifier
{
    $http = new HttpFactory;
    $http->fake([JWKS_URL => $http->response($jwks, 200)]);

    return new JwksSupabaseJwtVerifier(
        http: $http,
        cache: new CacheRepository(new ArrayStore),
        jwksUrl: JWKS_URL,
        issuer: ISSUER,
        audience: 'authenticated',
        cacheTtl: 3600,
    );
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function claims(array $overrides = []): array
{
    return array_merge([
        'iss' => ISSUER,
        'aud' => 'authenticated',
        'sub' => '11111111-1111-1111-1111-111111111111',
        'email' => 'fan@example.com',
        'email_verified' => true,
        'exp' => time() + 3600,
    ], $overrides);
}

it('verifies a valid token and returns claims', function () {
    [$private, $details] = makeRsaKey();
    $verifier = makeVerifier(jwksFor($details));
    $token = JWT::encode(claims(), $private, 'RS256', KID);

    $result = $verifier->verify($token);

    expect($result->sub)->toBe('11111111-1111-1111-1111-111111111111')
        ->and($result->email)->toBe('fan@example.com')
        ->and($result->emailVerified)->toBeTrue();
});

it('rejects an expired token', function () {
    [$private, $details] = makeRsaKey();
    $verifier = makeVerifier(jwksFor($details));
    $token = JWT::encode(claims(['exp' => time() - 10]), $private, 'RS256', KID);

    $verifier->verify($token);
})->throws(InvalidTokenException::class);

it('rejects a token with the wrong issuer', function () {
    [$private, $details] = makeRsaKey();
    $verifier = makeVerifier(jwksFor($details));
    $token = JWT::encode(claims(['iss' => 'https://evil.example.com/auth/v1']), $private, 'RS256', KID);

    $verifier->verify($token);
})->throws(InvalidTokenException::class);

it('rejects a token with a tampered signature', function () {
    // JWKS に公開した鍵とは別の鍵で署名する。
    [, $publishedDetails] = makeRsaKey();
    [$attackerPrivate] = makeRsaKey();
    $verifier = makeVerifier(jwksFor($publishedDetails));
    $token = JWT::encode(claims(), $attackerPrivate, 'RS256', KID);

    $verifier->verify($token);
})->throws(InvalidTokenException::class);

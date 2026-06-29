<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->withoutVite())
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Services\Auth\InvalidTokenException;
use App\Services\Auth\SupabaseJwtVerifier;
use App\Services\Auth\VerifiedClaims;
use Tests\Support\FakeSupabaseJwtVerifier;

/**
 * Supabase JWT verifier を、指定クレームを返す（または例外を投げる）fake に差し替える。
 */
function fakeVerifier(VerifiedClaims|InvalidTokenException $result): void
{
    app()->instance(SupabaseJwtVerifier::class, new FakeSupabaseJwtVerifier($result));
}

/**
 * テスト用に VerifiedClaims を生成する。
 */
function verifiedClaims(array $overrides = []): VerifiedClaims
{
    return VerifiedClaims::fromPayload(array_merge([
        'sub' => '33333333-3333-3333-3333-333333333333',
        'email' => 'fan@example.com',
        'email_verified' => true,
        'iss' => 'https://project.supabase.co/auth/v1',
        'aud' => 'authenticated',
        'exp' => time() + 3600,
    ], $overrides));
}

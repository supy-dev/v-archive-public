<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Services\Auth\InvalidTokenException;

it('establishes a session and creates a profile for a verified token (204)', function () {
    fakeVerifier(verifiedClaims(['name' => 'みこ', 'picture' => 'https://img/a.png']));

    $response = $this->postJson('/auth/session', ['access_token' => 'valid-token']);

    $response->assertNoContent();
    expect(Profile::count())->toBe(1);
    $this->assertAuthenticated();
});

it('is idempotent: repeated sessions for the same sub keep a single profile', function () {
    fakeVerifier(verifiedClaims());
    $this->postJson('/auth/session', ['access_token' => 'valid-token'])->assertNoContent();

    fakeVerifier(verifiedClaims());
    $this->postJson('/auth/session', ['access_token' => 'valid-token-again'])->assertNoContent();

    expect(Profile::count())->toBe(1);
});

it('rejects an invalid token with 401', function () {
    fakeVerifier(new InvalidTokenException('bad'));

    $this->postJson('/auth/session', ['access_token' => 'tampered'])
        ->assertUnauthorized();

    $this->assertGuest();
    expect(Profile::count())->toBe(0);
});

it('forbids establishing a session when email is not verified (403)', function () {
    fakeVerifier(verifiedClaims(['email_verified' => false]));

    $this->postJson('/auth/session', ['access_token' => 'unverified'])
        ->assertForbidden();

    $this->assertGuest();
    expect(Profile::count())->toBe(0);
});

it('validates that access_token is required (422)', function () {
    $this->postJson('/auth/session', [])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('access_token');
});

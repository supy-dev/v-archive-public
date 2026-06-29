<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Services\Auth\InvalidTokenException;

it('redirects unauthenticated visitors from the home page to login', function () {
    $this->get('/')->assertRedirect('/login');
});

it('redirects unauthenticated visitors from the profile page to login', function () {
    $this->get('/profile')->assertRedirect('/login');
});

it('allows an authenticated user to reach a protected route', function () {
    $this->actingAs(Profile::factory()->create());

    $this->get('/')->assertOk();
});

it('rejects a tampered/expired token at session establishment with 401', function () {
    fakeVerifier(new InvalidTokenException('signature mismatch'));

    $this->postJson('/auth/session', ['access_token' => 'tampered'])
        ->assertUnauthorized();

    $this->assertGuest();
});

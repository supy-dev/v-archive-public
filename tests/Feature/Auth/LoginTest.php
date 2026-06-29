<?php

declare(strict_types=1);

use App\Models\Profile;

it('shows the login page to guests', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('ログイン');
});

it('redirects authenticated users away from the login page', function () {
    $this->actingAs(Profile::factory()->create());

    $this->get('/login')->assertRedirect('/');
});

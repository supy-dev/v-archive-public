<?php

declare(strict_types=1);

it('exposes only Google login during the initial launch', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Googleでログイン')
        ->assertDontSee('data-login-form', false)
        ->assertDontSee('パスワードをお忘れですか？');
});

it('temporarily disables the email registration page', function () {
    $this->get('/register')->assertNotFound();
});

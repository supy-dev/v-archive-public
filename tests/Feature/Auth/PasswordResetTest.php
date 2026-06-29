<?php

declare(strict_types=1);

it('temporarily disables the forgot-password page', function () {
    $this->get('/forgot-password')->assertNotFound();
});

it('temporarily disables the reset-password page', function () {
    $this->get('/reset-password')->assertNotFound();
});

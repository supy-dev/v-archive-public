<?php

declare(strict_types=1);

use App\Models\Profile;

it('logs out and destroys the session', function () {
    $this->actingAs(Profile::factory()->create());

    $this->deleteJson('/auth/session')->assertNoContent();

    $this->assertGuest();
});

it('requires login for protected routes after logout', function () {
    $user = Profile::factory()->create();
    $this->actingAs($user);
    $this->deleteJson('/auth/session')->assertNoContent();

    // 新規（ゲスト）リクエストでは保護コンテンツに到達できない。
    $this->get('/')->assertRedirect('/login');
});

<?php

declare(strict_types=1);

use App\Models\Oshi;
use App\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('他ユーザーの推し詳細は参照できない', function (): void {
    $user  = Profile::factory()->create();
    $other = Profile::factory()->create();
    $oshi  = Oshi::factory()->create(['profile_id' => $other->id]);

    $this->actingAs($user)
        ->get("/oshis/{$oshi->id}")
        ->assertForbidden();
});

it('他ユーザーの推し編集フォームは参照できない', function (): void {
    $user  = Profile::factory()->create();
    $other = Profile::factory()->create();
    $oshi  = Oshi::factory()->create(['profile_id' => $other->id]);

    $this->actingAs($user)
        ->get("/oshis/{$oshi->id}/edit")
        ->assertForbidden();
});

it('未認証で推し一覧を開くとログインページへリダイレクト', function (): void {
    $this->get('/oshis')->assertRedirect('/login');
});

it('未認証で推し詳細を開くとログインページへリダイレクト', function (): void {
    $oshi = Oshi::factory()->create();
    $this->get("/oshis/{$oshi->id}")->assertRedirect('/login');
});

it('自分の推しだけが一覧に表示される', function (): void {
    $user  = Profile::factory()->create();
    $other = Profile::factory()->create();

    Oshi::factory()->create(['profile_id' => $user->id, 'name' => '自分の推し']);
    Oshi::factory()->create(['profile_id' => $other->id, 'name' => '他人の推し']);

    $this->actingAs($user)
        ->get('/oshis')
        ->assertSee('自分の推し')
        ->assertDontSee('他人の推し');
});

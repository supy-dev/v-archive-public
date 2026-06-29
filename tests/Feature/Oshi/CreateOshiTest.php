<?php

declare(strict_types=1);

use App\Enums\OshiColor;
use App\Models\Oshi;
use App\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('認証済みユーザーが推しを作成できる', function (): void {
    $user = Profile::factory()->create();

    $this->actingAs($user)
        ->post('/oshis', [
            'name'       => '姫森ルーナ',
            'group_name' => 'ホロライブ',
            'color_id'   => OshiColor::Pink->value,
            'memo'       => 'メモ',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('oshis', [
        'profile_id' => $user->id,
        'name'       => '姫森ルーナ',
        'group_name' => 'ホロライブ',
        'color_id'   => 'pink',
    ]);
});

it('name のみで推しを作成できる', function (): void {
    $user = Profile::factory()->create();

    $this->actingAs($user)
        ->post('/oshis', ['name' => '星街すいせい'])
        ->assertRedirect();

    $this->assertDatabaseHas('oshis', [
        'profile_id' => $user->id,
        'name'       => '星街すいせい',
        'group_name' => null,
        'color_id'   => null,
    ]);
});

it('name が未入力だと作成を拒否する', function (): void {
    $user = Profile::factory()->create();

    $this->actingAs($user)
        ->post('/oshis', ['name' => ''])
        ->assertSessionHasErrors('name');

    $this->assertDatabaseCount('oshis', 0);
});

it('name が 101 文字以上だと作成を拒否する', function (): void {
    $user = Profile::factory()->create();

    $this->actingAs($user)
        ->post('/oshis', ['name' => str_repeat('あ', 101)])
        ->assertSessionHasErrors('name');
});

it('パレット外の color_id は拒否する', function (): void {
    $user = Profile::factory()->create();

    $this->actingAs($user)
        ->post('/oshis', ['name' => '推し', 'color_id' => '#ff0000'])
        ->assertSessionHasErrors('color_id');

    $this->assertDatabaseCount('oshis', 0);
});

it('profile_id はリクエストボディから取得しない（憲法 III）', function (): void {
    $user  = Profile::factory()->create();
    $other = Profile::factory()->create();

    $this->actingAs($user)
        ->post('/oshis', ['name' => '推し', 'profile_id' => $other->id])
        ->assertRedirect();

    // 作成された推しの profile_id は認証ユーザー自身
    $oshi = Oshi::first();
    expect($oshi->profile_id)->toBe($user->id);
});

it('未認証だとログインページへリダイレクト', function (): void {
    $this->post('/oshis', ['name' => '推し'])
        ->assertRedirect('/login');
});

<?php

declare(strict_types=1);

use App\Enums\OshiColor;
use App\Models\Oshi;
use App\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('自分の推しを編集できる', function (): void {
    $user = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $user->id, 'name' => '旧名前']);

    $this->actingAs($user)
        ->put("/oshis/{$oshi->id}", [
            'name'       => '新名前',
            'group_name' => 'ホロライブ',
            'color_id'   => OshiColor::Blue->value,
        ])
        ->assertRedirect("/oshis/{$oshi->id}");

    $this->assertDatabaseHas('oshis', [
        'id'         => $oshi->id,
        'name'       => '新名前',
        'group_name' => 'ホロライブ',
        'color_id'   => 'blue',
    ]);
});

it('パレット外の color_id は編集でも拒否する', function (): void {
    $user = Profile::factory()->create();
    $oshi = Oshi::factory()->create(['profile_id' => $user->id]);

    $this->actingAs($user)
        ->put("/oshis/{$oshi->id}", ['name' => '名前', 'color_id' => 'invalid'])
        ->assertSessionHasErrors('color_id');
});

it('他ユーザーの推しは編集できない', function (): void {
    $user  = Profile::factory()->create();
    $other = Profile::factory()->create();
    $oshi  = Oshi::factory()->create(['profile_id' => $other->id]);

    $this->actingAs($user)
        ->put("/oshis/{$oshi->id}", ['name' => '改ざん'])
        ->assertForbidden();
});

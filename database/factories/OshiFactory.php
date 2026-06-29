<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OshiColor;
use App\Models\Oshi;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Oshi>
 */
class OshiFactory extends Factory
{
    protected $model = Oshi::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'name'       => $this->faker->name(),
            'group_name' => null,
            'color_id'   => null,
            'memo'       => null,
        ];
    }

    /** テーマカラー付きの推し */
    public function withColor(OshiColor $color = OshiColor::Pink): static
    {
        return $this->state(['color_id' => $color->value]);
    }
}

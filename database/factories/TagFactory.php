<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profile;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'profile_id' => Profile::factory(),
            'name'       => $name,
            'slug'       => Str::slug($name),
            'color'      => null,
            'is_system'  => false,
        ];
    }

    /** システムタグ */
    public function system(): static
    {
        return $this->state([
            'profile_id' => null,
            'is_system'  => true,
        ]);
    }

    /** ユーザー固有タグ */
    public function forUser(string $profileId): static
    {
        return $this->state([
            'profile_id' => $profileId,
            'is_system'  => false,
        ]);
    }
}

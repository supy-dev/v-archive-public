<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profile;
use App\Models\TimestampMemo;
use App\Models\YoutubeVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimestampMemo>
 */
class TimestampMemoFactory extends Factory
{
    protected $model = TimestampMemo::class;

    public function definition(): array
    {
        return [
            'profile_id'       => Profile::factory(),
            'youtube_video_id' => YoutubeVideo::factory(),
            'seconds'          => $this->faker->numberBetween(0, 7200),
            'body'             => $this->faker->sentence(),
            'is_favorite'      => false,
        ];
    }

    /** お気に入り済みメモ */
    public function favorite(): static
    {
        return $this->state(['is_favorite' => true]);
    }
}

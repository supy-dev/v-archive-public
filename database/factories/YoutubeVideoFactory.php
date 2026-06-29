<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LiveStatus;
use App\Enums\VideoType;
use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YoutubeVideo>
 */
class YoutubeVideoFactory extends Factory
{
    protected $model = YoutubeVideo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'youtube_video_id'   => $this->faker->regexify('[A-Za-z0-9_-]{11}'),
            'youtube_channel_id' => YoutubeChannel::factory(),
            'title'              => $this->faker->sentence(6),
            'description'        => $this->faker->optional()->text(400),
            'thumbnail_url'      => 'https://i.ytimg.com/vi/' . $this->faker->regexify('[A-Za-z0-9_-]{11}') . '/hqdefault.jpg',
            'published_at'       => $this->faker->dateTimeBetween('-2 years', 'now'),
            'duration_seconds'   => $this->faker->numberBetween(60, 7200),
            'video_type'         => VideoType::Archive->value,
            'live_status'        => LiveStatus::Completed->value,
            'scheduled_start_at' => null,
            'actual_start_at'    => null,
            'actual_end_at'      => null,
            'privacy_status'     => 'public',
            'is_available'       => true,
            'last_fetched_at'    => now(),
        ];
    }

    /** 配信中ライブ動画 */
    public function live(): static
    {
        return $this->state(fn (array $attr) => [
            'video_type'      => VideoType::Live->value,
            'live_status'     => LiveStatus::Live->value,
            'duration_seconds' => null,
            'actual_start_at' => now()->subHour(),
            'actual_end_at'   => null,
        ]);
    }

    /** 削除・非公開動画 */
    public function unavailable(): static
    {
        return $this->state(fn (array $attr) => [
            'is_available' => false,
        ]);
    }
}

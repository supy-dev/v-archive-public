<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WatchStatus;
use App\Models\Profile;
use App\Models\UserWatchItem;
use App\Models\YoutubeVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserWatchItem>
 */
class UserWatchItemFactory extends Factory
{
    protected $model = UserWatchItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id'            => Profile::factory(),
            'youtube_video_id'      => YoutubeVideo::factory(),
            'status'                => WatchStatus::WantToWatch->value,
            'priority'              => 0,
            'is_favorite'           => false,
            'added_at'              => now(),
            'started_at'            => null,
            'watched_at'            => null,
            'skipped_at'            => null,
            'last_position_seconds' => null,
        ];
    }

    /** 未視聴（見るリスト追加済み） */
    public function wantToWatch(): static
    {
        return $this->state(fn (array $attr) => [
            'status'     => WatchStatus::WantToWatch->value,
            'watched_at' => null,
            'skipped_at' => null,
        ]);
    }

    /** 視聴中 */
    public function watching(): static
    {
        return $this->state(fn (array $attr) => [
            'status'     => WatchStatus::Watching->value,
            'started_at' => now(),
        ]);
    }

    /** 視聴済み */
    public function watched(): static
    {
        return $this->state(fn (array $attr) => [
            'status'     => WatchStatus::Watched->value,
            'watched_at' => now(),
        ]);
    }

    /** 見送り */
    public function skipped(): static
    {
        return $this->state(fn (array $attr) => [
            'status'     => WatchStatus::Skipped->value,
            'skipped_at' => now(),
        ]);
    }
}

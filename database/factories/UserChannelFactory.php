<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Oshi;
use App\Models\Profile;
use App\Models\UserChannel;
use App\Models\YoutubeChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserChannel>
 */
class UserChannelFactory extends Factory
{
    protected $model = UserChannel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id'         => Profile::factory(),
            'oshi_id'            => Oshi::factory(),
            'youtube_channel_id' => YoutubeChannel::factory(),
            'is_main'            => false,
            'sync_enabled'       => true,
            'notify_enabled'     => false,
            'registered_at'      => now(),
        ];
    }

    /** メインチャンネルとして作成 */
    public function main(): static
    {
        return $this->state(['is_main' => true]);
    }
}

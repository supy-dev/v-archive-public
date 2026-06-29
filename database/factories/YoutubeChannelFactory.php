<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChannelSyncStatus;
use App\Models\YoutubeChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YoutubeChannel>
 */
class YoutubeChannelFactory extends Factory
{
    protected $model = YoutubeChannel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $channelId = 'UC' . $this->faker->regexify('[A-Za-z0-9_-]{22}');

        return [
            'youtube_channel_id'  => $channelId,
            'title'               => $this->faker->company() . ' Channel',
            'description'         => null,
            'handle'              => $this->faker->slug(2),
            'thumbnail_url'       => null,
            'uploads_playlist_id' => 'UU' . substr($channelId, 2),
            'published_at'        => null,
            'sync_status'         => ChannelSyncStatus::Pending->value,
            'sync_error_message'  => null,
            'last_synced_at'      => null,
        ];
    }
}

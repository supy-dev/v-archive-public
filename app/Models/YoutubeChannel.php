<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChannelSyncStatus;
use Database\Factories\YoutubeChannelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 全ユーザー共通の YouTube チャンネル情報（共有マスタ）。
 * ユーザーが直接更新・削除できない（憲法 II / FR-011）。
 */
class YoutubeChannel extends Model
{
    /** @use HasFactory<YoutubeChannelFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'youtube_channel_id',
        'title',
        'description',
        'handle',
        'thumbnail_url',
        'uploads_playlist_id',
        'published_at',
        'sync_status',
        'sync_error_message',
        'last_synced_at',
        'oldest_page_token',
        'oldest_fetched_at',
        'is_fetching_older',
    ];

    protected function casts(): array
    {
        return [
            'sync_status'       => ChannelSyncStatus::class,
            'published_at'      => 'datetime',
            'last_synced_at'    => 'datetime',
            'oldest_fetched_at' => 'datetime',
            'is_fetching_older' => 'boolean',
        ];
    }

    /** @return HasMany<UserChannel, $this> */
    public function userChannels(): HasMany
    {
        return $this->hasMany(UserChannel::class);
    }

    /** @return HasMany<YoutubeVideo, $this> */
    public function youtubeVideos(): HasMany
    {
        return $this->hasMany(YoutubeVideo::class, 'youtube_channel_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LiveStatus;
use App\Enums\VideoType;
use Database\Factories\YoutubeVideoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * YouTube 動画の共有マスタモデル。
 *
 * 全ユーザー共通で1レコード。ユーザーが直接更新・削除できない（憲法 II）。
 * ユーザー固有の視聴状態は Feature 4 の user_watch_items で管理する。
 */
class YoutubeVideo extends Model
{
    /** @use HasFactory<YoutubeVideoFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'youtube_video_id',
        'youtube_channel_id',
        'title',
        'description',
        'thumbnail_url',
        'published_at',
        'duration_seconds',
        'video_type',
        'live_status',
        'scheduled_start_at',
        'actual_start_at',
        'actual_end_at',
        'privacy_status',
        'is_available',
        'last_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'video_type'         => VideoType::class,
            'live_status'        => LiveStatus::class,
            'published_at'       => 'datetime',
            'scheduled_start_at' => 'datetime',
            'actual_start_at'    => 'datetime',
            'actual_end_at'      => 'datetime',
            'last_fetched_at'    => 'datetime',
            'is_available'       => 'boolean',
        ];
    }

    protected static function newFactory(): YoutubeVideoFactory
    {
        return YoutubeVideoFactory::new();
    }

    /** 所属する YouTube チャンネル（共有マスタ） */
    public function youtubeChannel(): BelongsTo
    {
        return $this->belongsTo(YoutubeChannel::class, 'youtube_channel_id');
    }
}

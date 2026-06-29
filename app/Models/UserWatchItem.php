<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WatchStatus;
use Database\Factories\UserWatchItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ユーザーが操作した動画ごとに 1 件作成されるユーザー固有の視聴アイテム。
 *
 * profile_id で所有者を特定し、UserWatchItemPolicy で保護する（憲法 III）。
 * youtube_videos の共有マスタは変更せず、ユーザー固有状態をこのテーブルで管理する（憲法 II）。
 */
class UserWatchItem extends Model
{
    /** @use HasFactory<UserWatchItemFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'profile_id',
        'youtube_video_id',
        'status',
        'priority',
        'is_favorite',
        'added_at',
        'started_at',
        'watched_at',
        'skipped_at',
        'last_position_seconds',
    ];

    protected function casts(): array
    {
        return [
            'status'      => WatchStatus::class,
            'is_favorite' => 'boolean',
            'added_at'    => 'datetime',
            'started_at'  => 'datetime',
            'watched_at'  => 'datetime',
            'skipped_at'  => 'datetime',
        ];
    }

    protected static function newFactory(): UserWatchItemFactory
    {
        return UserWatchItemFactory::new();
    }

    /** 所有ユーザー */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    /** 対象 YouTube 動画（共有マスタ） */
    public function youtubeVideo(): BelongsTo
    {
        return $this->belongsTo(YoutubeVideo::class, 'youtube_video_id');
    }
}

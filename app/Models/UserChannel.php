<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserChannelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ユーザーのチャンネル登録と固有設定（ユーザー所有データ）。
 * profile_id で所有者を特定し、UserChannelPolicy で保護する（憲法 III）。
 */
class UserChannel extends Model
{
    /** @use HasFactory<UserChannelFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'profile_id',
        'oshi_id',
        'youtube_channel_id',
        'is_main',
        'sync_enabled',
        'notify_enabled',
        'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_main'       => 'boolean',
            'sync_enabled'  => 'boolean',
            'notify_enabled' => 'boolean',
            'registered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Oshi, $this> */
    public function oshi(): BelongsTo
    {
        return $this->belongsTo(Oshi::class);
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    /** @return BelongsTo<YoutubeChannel, $this> */
    public function youtubeChannel(): BelongsTo
    {
        return $this->belongsTo(YoutubeChannel::class);
    }
}

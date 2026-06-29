<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 動画全体ノートモデル。
 *
 * 1 ユーザー・1 動画につき 1 件（UNIQUE 制約）の自由記述メモ（FR-005）。
 * VideoNotePolicy で所有権を保護（憲法 III）。
 */
class VideoNote extends Model
{
    use HasUuids;

    protected $fillable = [
        'profile_id',
        'youtube_video_id',
        'body',
    ];

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

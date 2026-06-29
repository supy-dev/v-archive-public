<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TimestampMemoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * タイムスタンプメモモデル。
 *
 * 特定の動画の特定秒数に紐付けたユーザーメモ（FR-001）。
 * お気に入りフラグ（is_favorite）でお気に入り一覧に掲載（FR-010）。
 * TimestampMemoPolicy で所有権を保護（憲法 III）。
 */
class TimestampMemo extends Model
{
    /** @use HasFactory<TimestampMemoFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'profile_id',
        'youtube_video_id',
        'seconds',
        'body',
        'is_favorite',
    ];

    protected function casts(): array
    {
        return [
            'seconds'     => 'integer',
            'is_favorite' => 'boolean',
        ];
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

    /** 付与されているタグ */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            Tag::class,
            'timestamp_memo_tags',
            'timestamp_memo_id',
            'tag_id',
        );
    }

    /**
     * seconds を MM:SS（または H:MM:SS）形式で返すアクセサ。
     * 60 分以上は H:MM:SS 形式にする（FR-003）。
     */
    public function getSecondsLabelAttribute(): string
    {
        $s = (int) $this->seconds;
        if ($s >= 3600) {
            return sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60);
        }

        return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    }

    /**
     * タイムスタンプ付き YouTube URL を返すアクセサ（FR-016）。
     */
    public function getYoutubeUrlAttribute(): string
    {
        $videoId = $this->youtubeVideo?->youtube_video_id ?? '';

        return "https://www.youtube.com/watch?v={$videoId}&t={$this->seconds}s";
    }
}

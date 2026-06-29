<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * タグモデル。
 *
 * is_system=true のタグは全ユーザー共有（profile_id=NULL）。
 * is_system=false のタグはユーザー固有（profile_id=UUID）（FR-008 / FR-009）。
 * Profile 削除時はユーザー固有タグも CASCADE 削除される（憲法 II）。
 */
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'profile_id',
        'name',
        'slug',
        'color',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /** 所有ユーザー（システムタグは null） */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    /** このタグが付与されているタイムスタンプメモ */
    public function timestampMemos(): BelongsToMany
    {
        return $this->belongsToMany(
            TimestampMemo::class,
            'timestamp_memo_tags',
            'tag_id',
            'timestamp_memo_id',
        );
    }

    /** システムタグのみを取得するスコープ */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    /** 指定ユーザーの固有タグのみを取得するスコープ */
    public function scopeForUser(Builder $query, string $profileId): Builder
    {
        return $query->where('is_system', false)->where('profile_id', $profileId);
    }
}

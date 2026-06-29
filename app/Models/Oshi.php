<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OshiColor;
use Database\Factories\OshiFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Oshi extends Model
{
    /** @use HasFactory<OshiFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'profile_id',
        'name',
        'group_name',
        'color_id',
        'memo',
    ];

    protected function casts(): array
    {
        return [
            'color_id' => OshiColor::class,
        ];
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    /** @return HasMany<UserChannel, $this> */
    public function userChannels(): HasMany
    {
        return $this->hasMany(UserChannel::class);
    }
}

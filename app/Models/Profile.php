<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Profile extends Authenticatable
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * id は Supabase auth.users の UUID（JWT の `sub`）。自動採番はしない。
     */
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'display_name',
        'avatar_url',
        'timezone',
    ];

    /**
     * id はローカル生成せず、検証済み JWT の `sub` から割り当てる。
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return [];
    }

    protected static function newFactory(): ProfileFactory
    {
        return ProfileFactory::new();
    }

    /** @return HasMany<Oshi, $this> */
    public function oshis(): HasMany
    {
        return $this->hasMany(Oshi::class, 'profile_id');
    }

    /** @return HasMany<UserChannel, $this> */
    public function userChannels(): HasMany
    {
        return $this->hasMany(UserChannel::class, 'profile_id');
    }
}

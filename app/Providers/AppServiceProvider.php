<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Oshi;
use App\Models\Profile;
use App\Models\Tag;
use App\Models\TimestampMemo;
use App\Models\UserChannel;
use App\Models\UserWatchItem;
use App\Models\VideoNote;
use App\Policies\OshiPolicy;
use App\Policies\ProfilePolicy;
use App\Policies\TagPolicy;
use App\Policies\TimestampMemoPolicy;
use App\Policies\UserChannelPolicy;
use App\Policies\UserWatchItemPolicy;
use App\Policies\VideoNotePolicy;
use App\Services\Auth\JwksSupabaseJwtVerifier;
use App\Services\Auth\SupabaseJwtVerifier;
use App\Services\YouTube\ApiYouTubeChannelResolver;
use App\Services\YouTube\YouTubeChannelResolverInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            YouTubeChannelResolverInterface::class,
            ApiYouTubeChannelResolver::class,
        );

        $this->app->singleton(SupabaseJwtVerifier::class, function ($app): SupabaseJwtVerifier {
            $config = $app['config'];

            return new JwksSupabaseJwtVerifier(
                http: $app->make(HttpFactory::class),
                cache: $app->make(Cache::class),
                jwksUrl: (string) $config->get('supabase.jwks_url'),
                issuer: (string) $config->get('supabase.issuer'),
                audience: (string) $config->get('supabase.audience'),
                cacheTtl: (int) $config->get('supabase.jwks_cache_ttl'),
            );
        });
    }

    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.an');

        Gate::policy(Profile::class, ProfilePolicy::class);
        Gate::policy(Oshi::class, OshiPolicy::class);
        Gate::policy(UserChannel::class, UserChannelPolicy::class);
        Gate::policy(UserWatchItem::class, UserWatchItemPolicy::class);
        Gate::policy(TimestampMemo::class, TimestampMemoPolicy::class);
        Gate::policy(VideoNote::class, VideoNotePolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);

        // 推し・チャンネル登録の変更操作への濫用防止レート制限（FR-016）
        RateLimiter::for('oshi-mutations', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // 再生位置保存 API への濫用防止レート制限（FR-017 / 仕様 Q5）
        // 60秒間隔保存 + 一時停止・離脱保存を合わせても通常 3〜5 req/分。10 req/分で余裕を持って吸収する。
        RateLimiter::for('playback-position', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->user()?->id ?? $request->ip());
        });

        // メモ・ノート・タグ操作への濫用防止レート制限（FR-013）
        RateLimiter::for('memo-mutations', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // 手動チャンネル同期への厳格レート制限（FR-004）
        // YouTube API クォータを直接消費する操作のため oshi-mutations（60回/分）より大幅に厳しく制限する
        RateLimiter::for('channel-sync', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->user()?->id ?? $request->ip());
        });
    }
}

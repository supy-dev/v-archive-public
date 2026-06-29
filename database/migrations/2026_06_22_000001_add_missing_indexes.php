<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 仕様書 §18.2 の推奨インデックスで未追加の 2 件を追加する。
     *
     * - user_watch_items(profile_id, updated_at): 神回タブのソートキー
     * - user_channels(profile_id, sync_enabled): 同期対象チャンネル抽出 Job
     */
    public function up(): void
    {
        Schema::table('user_watch_items', function (Blueprint $table): void {
            $table->index(['profile_id', 'updated_at']);
        });

        Schema::table('user_channels', function (Blueprint $table): void {
            $table->index(['profile_id', 'sync_enabled']);
        });
    }

    public function down(): void
    {
        Schema::table('user_watch_items', function (Blueprint $table): void {
            $table->dropIndex(['profile_id', 'updated_at']);
        });

        Schema::table('user_channels', function (Blueprint $table): void {
            $table->dropIndex(['profile_id', 'sync_enabled']);
        });
    }
};

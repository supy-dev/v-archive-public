<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * youtube_channels に過去動画取得用カラムを追加する。
     *
     * oldest_page_token: 過去動画取得の継続トークン（null = 全件取得済みまたは未取得）
     * oldest_fetched_at: 取得済み最古動画の公開日時（共有マスタとして全ユーザーが参照）
     * これらは共有マスタ側に保持し、ユーザーごとに複製しない（憲法 II / clarify Q5）。
     */
    public function up(): void
    {
        Schema::table('youtube_channels', function (Blueprint $table): void {
            $table->string('oldest_page_token', 255)->nullable()->after('last_synced_at');
            $table->timestampTz('oldest_fetched_at')->nullable()->after('oldest_page_token');
        });
    }

    public function down(): void
    {
        Schema::table('youtube_channels', function (Blueprint $table): void {
            $table->dropColumn(['oldest_page_token', 'oldest_fetched_at']);
        });
    }
};

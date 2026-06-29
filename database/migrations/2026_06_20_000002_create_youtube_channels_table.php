<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * YouTube チャンネル共有マスタテーブルを作成する。
     * 全ユーザー共通で1レコード。ユーザーが直接更新・削除できない（憲法 II / FR-011）。
     * 動画ファイル・サムネイル本体・API レスポンス全文は保存しない（憲法 II）。
     */
    public function up(): void
    {
        Schema::create('youtube_channels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('youtube_channel_id', 255)->unique();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('handle', 100)->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->string('uploads_playlist_id', 50)->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->string('sync_status', 20)->default('pending');
            $table->text('sync_error_message')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_channels');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * YouTube 動画共有マスタテーブルを作成する。
     *
     * 全ユーザー共通で1レコード。ユーザーが直接更新・削除できない（憲法 II）。
     * 動画ファイル・コメント・APIレスポンス全文は保存しない。
     * description は先頭500文字のみ保存（research.md Decision 2）。
     */
    public function up(): void
    {
        Schema::create('youtube_videos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('youtube_video_id', 20)->unique();
            $table->uuid('youtube_channel_id');
            $table->string('title', 255);
            $table->string('description', 500)->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->timestampTz('published_at');
            $table->integer('duration_seconds')->nullable();
            $table->string('video_type', 20)->default('unknown');
            $table->string('live_status', 20)->default('none');
            $table->timestampTz('scheduled_start_at')->nullable();
            $table->timestampTz('actual_start_at')->nullable();
            $table->timestampTz('actual_end_at')->nullable();
            $table->string('privacy_status', 20)->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestampTz('last_fetched_at');
            $table->timestampsTz();

            $table->foreign('youtube_channel_id')
                ->references('id')
                ->on('youtube_channels')
                ->cascadeOnDelete();

            // チャンネル別・公開日降順（新着一覧取得の主インデックス）
            $table->index(['youtube_channel_id', 'published_at']);
            $table->index('video_type');
            $table->index('live_status');
            $table->index('published_at');
        });

        // 削除・非公開動画のバッチ処理用部分インデックス
        DB::statement(
            'CREATE INDEX idx_youtube_videos_unavailable ON youtube_videos (is_available) WHERE is_available = FALSE'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_videos');
    }
};

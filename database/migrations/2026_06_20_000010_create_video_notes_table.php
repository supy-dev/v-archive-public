<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 動画ノートテーブルを作成する。
     *
     * 1 ユーザー・1 動画につき 1 件の自由記述メモ（FR-005）。
     * UNIQUE 制約で upsert を安全に行える（FR-006）。
     */
    public function up(): void
    {
        Schema::create('video_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('profile_id');
            $table->uuid('youtube_video_id');
            $table->text('body');
            $table->timestampsTz();

            $table->foreign('profile_id')
                ->references('id')
                ->on('profiles')
                ->cascadeOnDelete();

            $table->foreign('youtube_video_id')
                ->references('id')
                ->on('youtube_videos')
                ->cascadeOnDelete();

            // 1 ユーザー・1 動画につき 1 件
            $table->unique(['profile_id', 'youtube_video_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_notes');
    }
};

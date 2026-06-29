<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * タイムスタンプメモテーブルを作成する。
     *
     * ユーザーが特定の動画の特定秒数に紐付けて保存する短文メモ（FR-001）。
     * 同一秒数への複数メモは許可（UNIQUE制約なし）。
     * seconds=0 は有効（動画開始直後のメモ）（edge case）。
     */
    public function up(): void
    {
        Schema::create('timestamp_memos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('profile_id');
            $table->uuid('youtube_video_id');
            $table->integer('seconds');
            $table->text('body');
            $table->boolean('is_favorite')->default(false);
            $table->timestampsTz();

            $table->foreign('profile_id')
                ->references('id')
                ->on('profiles')
                ->cascadeOnDelete();

            $table->foreign('youtube_video_id')
                ->references('id')
                ->on('youtube_videos')
                ->cascadeOnDelete();

            // 動画詳細ページのメモ一覧取得（秒数昇順）
            $table->index(['profile_id', 'youtube_video_id', 'seconds']);
            // お気に入り一覧取得
            $table->index(['profile_id', 'is_favorite', 'created_at']);
        });

        // seconds は 0 以上の整数のみ（SQLite は対応外のためスキップ）
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(
                "ALTER TABLE timestamp_memos ADD CONSTRAINT timestamp_memos_seconds_check "
                . "CHECK (seconds >= 0)"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('timestamp_memos');
    }
};

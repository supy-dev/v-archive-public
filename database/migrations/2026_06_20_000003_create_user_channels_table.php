<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ユーザー・チャンネル登録テーブルを作成する。
     *
     * 「どのユーザーが・どの推しに・どのチャンネルを」登録したかという関連と
     * ユーザー固有の設定（同期可否・通知可否・メイン指定）を保持する。
     *
     * 部分ユニークインデックス（profile_id, oshi_id WHERE is_main=true）で
     * メインチャンネルが常に1つであることを DB レベルで保証する（data-model.md §3）。
     */
    public function up(): void
    {
        Schema::create('user_channels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('profile_id');
            $table->uuid('oshi_id');
            $table->uuid('youtube_channel_id');
            $table->boolean('is_main')->default(false);
            $table->boolean('sync_enabled')->default(true);
            $table->boolean('notify_enabled')->default(false);
            $table->timestampTz('registered_at')->useCurrent();
            $table->timestampsTz();

            $table->foreign('profile_id')
                ->references('id')
                ->on('profiles')
                ->cascadeOnDelete();

            $table->foreign('oshi_id')
                ->references('id')
                ->on('oshis')
                ->cascadeOnDelete();

            $table->foreign('youtube_channel_id')
                ->references('id')
                ->on('youtube_channels');

            // 同一ユーザーが同一チャンネルを重複登録しない（FR-007 / FR-008 MVP 制約）
            $table->unique(['profile_id', 'youtube_channel_id']);

            // 推し詳細でのチャンネル一覧取得（N+1 防止）
            $table->index(['profile_id', 'oshi_id']);
        });

        // メインチャンネルは推しごとに常にちょうど1つ（DB レベルで保証）
        DB::statement(
            'CREATE UNIQUE INDEX user_channels_main_unique ON user_channels (profile_id, oshi_id) WHERE is_main = TRUE'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('user_channels');
    }
};

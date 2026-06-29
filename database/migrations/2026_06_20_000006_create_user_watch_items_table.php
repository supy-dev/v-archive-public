<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// DB インポートは CHECK 制約の DB::statement() で使用

return new class extends Migration
{
    /**
     * ユーザーごとの視聴アイテム管理テーブルを作成する。
     *
     * ユーザーが操作した YouTube 動画に対して 1 レコードを保持する
     * ユーザー固有の視聴状態テーブル（憲法 II）。
     * (profile_id, youtube_video_id) 複合ユニーク制約で重複作成を防ぐ（FR-007）。
     * status は CHECK 制約と PHP Enum で二重に保護する（FR-014）。
     */
    public function up(): void
    {
        Schema::create('user_watch_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('profile_id');
            $table->uuid('youtube_video_id');
            $table->string('status', 20)->default('want_to_watch');
            $table->integer('priority')->default(0);
            $table->boolean('is_favorite')->default(false);
            $table->timestampTz('added_at')->useCurrent();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('watched_at')->nullable();
            $table->timestampTz('skipped_at')->nullable();
            $table->integer('last_position_seconds')->nullable();
            $table->timestampsTz();

            $table->foreign('profile_id')
                ->references('id')
                ->on('profiles')
                ->cascadeOnDelete();

            $table->foreign('youtube_video_id')
                ->references('id')
                ->on('youtube_videos')
                ->cascadeOnDelete();

            // 同一ユーザーが同一動画に重複作成しない（FR-007）
            $table->unique(['profile_id', 'youtube_video_id']);

            // 見るリストタブ別取得
            $table->index(['profile_id', 'status']);
            // ページネーション付きタブ別取得
            $table->index(['profile_id', 'status', 'added_at']);
            // 未整理件数算出
            $table->index(['profile_id', 'added_at']);
        });

        // status は列挙値のみ受け付ける（FR-014、PHP レイヤーを迂回した書き込みも防ぐ）
        // SQLite はこの構文をサポートしないためテスト環境ではスキップする
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(
                "ALTER TABLE user_watch_items ADD CONSTRAINT user_watch_items_status_check "
                . "CHECK (status IN ('want_to_watch', 'watching', 'watched', 'skipped'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_watch_items');
    }
};

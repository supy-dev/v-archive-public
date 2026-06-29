<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * タイムスタンプメモとタグの中間テーブルを作成する（FR-007）。
     *
     * memo 削除時は CASCADE で自動削除。
     * tag 削除時も CASCADE（ユーザー固有タグ削除でメモとの紐付けも消える）。
     */
    public function up(): void
    {
        Schema::create('timestamp_memo_tags', function (Blueprint $table): void {
            $table->uuid('timestamp_memo_id');
            $table->uuid('tag_id');

            $table->primary(['timestamp_memo_id', 'tag_id']);

            $table->foreign('timestamp_memo_id')
                ->references('id')
                ->on('timestamp_memos')
                ->cascadeOnDelete();

            $table->foreign('tag_id')
                ->references('id')
                ->on('tags')
                ->cascadeOnDelete();

            // タグ別フィルタリング（お気に入り一覧）
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timestamp_memo_tags');
    }
};

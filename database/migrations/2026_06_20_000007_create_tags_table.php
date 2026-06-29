<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * タグテーブルを作成する。
     *
     * システムタグ（is_system=true, profile_id=NULL）と
     * ユーザー固有タグ（is_system=false, profile_id=UUID）の両方を管理する（FR-008 / FR-009）。
     * 部分ユニークインデックスで重複を防ぐ。
     */
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('profile_id')->nullable();
            $table->string('name', 100)->notNull();
            $table->string('slug', 100)->notNull();
            $table->string('color', 20)->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestampsTz();

            $table->foreign('profile_id')
                ->references('id')
                ->on('profiles')
                ->cascadeOnDelete();

            $table->index(['profile_id', 'is_system']);
        });

        // システムタグのスラッグはグローバルに一意（WHERE is_system = true）
        // ユーザー固有タグはユーザー内で一意（profile_id, slug WHERE is_system = false）
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(
                'CREATE UNIQUE INDEX tags_system_slug_unique ON tags(slug) WHERE is_system = true'
            );
            DB::statement(
                'CREATE UNIQUE INDEX tags_user_slug_unique ON tags(profile_id, slug) WHERE is_system = false'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};

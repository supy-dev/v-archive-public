<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 推しテーブルを作成する。
     * ユーザー固有データ。profile_id で所有者を特定し、Policy で保護する（憲法 III）。
     */
    public function up(): void
    {
        Schema::create('oshis', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('profile_id');
            $table->string('name', 100);
            $table->string('group_name', 100)->nullable();
            $table->string('color_id', 50)->nullable();
            $table->text('memo')->nullable();
            $table->timestampsTz();

            $table->foreign('profile_id')
                ->references('id')
                ->on('profiles')
                ->cascadeOnDelete();

            $table->index('profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oshis');
    }
};

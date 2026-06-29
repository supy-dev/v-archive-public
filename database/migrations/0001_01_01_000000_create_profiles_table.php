<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * マイグレーションを実行する。
     *
     * `profiles.id` は Supabase の `auth.users.id`（UUID / JWT の `sub`）と同一。
     * 認証主体は Supabase 側にあり、auth.users をアプリ DB に複製しない。
     */
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('display_name');
            $table->text('avatar_url')->nullable();
            $table->string('timezone', 64)->default('Asia/Tokyo');
            $table->timestampsTz();
        });

        // Laravel のセッションストア（database ドライバ）。user_id は UUID 文字列を保持する。
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('profiles');
    }
};

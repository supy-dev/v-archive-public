<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('youtube_channels', function (Blueprint $table) {
            $table->boolean('is_fetching_older')->default(false)->after('oldest_fetched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('youtube_channels', function (Blueprint $table) {
            $table->dropColumn('is_fetching_older');
        });
    }
};

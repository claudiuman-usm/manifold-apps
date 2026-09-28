<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('music_videos', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable();     // 0..3 stars
            $table->unsignedInteger('play_count')->default(0);      // denormalized for sort/display
            $table->timestamp('last_played_at')->nullable();
            $table->index('rating');
            $table->index('play_count');
        });

        // One row per counted listen — enables time-based stats (this month, trends).
        Schema::create('music_plays', function (Blueprint $table) {
            $table->id();
            $table->string('video_id');
            $table->timestamp('played_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index('video_id');
            $table->index('played_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('music_plays');
        Schema::table('music_videos', function (Blueprint $table) {
            $table->dropColumn(['rating', 'play_count', 'last_played_at']);
        });
    }
};

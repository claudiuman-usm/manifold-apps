<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('music_videos', function (Blueprint $table) {
            // Set when a full sync no longer finds the video in the Liked
            // playlist (= unliked on YouTube → treated as non-music).
            // Distinguishes "unliked" from parser/manual non-music, so only
            // unliked rows flip back to music when re-liked.
            $table->timestamp('unliked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('music_videos', function (Blueprint $table) {
            $table->dropColumn('unliked_at');
        });
    }
};

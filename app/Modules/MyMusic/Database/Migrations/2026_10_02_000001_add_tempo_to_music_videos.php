<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('music_videos', function (Blueprint $table) {
            // Manual slow/fast mark set from the player; null = unmarked.
            // On the video row (not the track) like rating — the stable
            // per-row entity that exists even before enrichment.
            $table->string('tempo', 10)->nullable()->after('rating');
            $table->index('tempo');
        });
    }

    public function down(): void
    {
        Schema::table('music_videos', function (Blueprint $table) {
            $table->dropIndex(['tempo']);
            $table->dropColumn('tempo');
        });
    }
};

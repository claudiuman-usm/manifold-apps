<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('music_playlists', function (Blueprint $table) {
            $table->id();
            $table->string('youtube_id')->nullable();   // set once playlists.insert succeeds
            $table->string('name');
            $table->json('filter_json')->nullable();    // the library filter that produced it
            $table->json('queue')->nullable();          // ordered video_ids still to insert
            // pending → creating → ready; quota_paused = resume tomorrow
            $table->string('status')->default('pending');
            $table->unsignedInteger('failed_count')->default(0); // deleted/blocked videos we skipped
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('music_playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained('music_playlists')->cascadeOnDelete();
            $table->string('video_id');
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['playlist_id', 'video_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('music_playlist_items');
        Schema::dropIfExists('music_playlists');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('music_videos', function (Blueprint $table) {
            $table->id();
            $table->string('video_id')->unique();
            $table->string('raw_title', 500);
            $table->string('channel_title')->nullable();     // video owner channel
            $table->timestamp('video_published_at')->nullable();
            $table->timestamp('liked_at')->nullable();       // when it was added to LL
            $table->string('thumbnail', 500)->nullable();
            $table->unsignedInteger('liked_position')->nullable();
            $table->boolean('is_music')->default(true);      // heuristic + manual toggle
            $table->boolean('embeddable')->default(true);    // flipped off on player error 101/150
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('liked_position');
            $table->index('is_music');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('music_videos');
    }
};

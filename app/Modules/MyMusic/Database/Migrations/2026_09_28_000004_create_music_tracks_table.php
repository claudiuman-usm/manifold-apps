<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('music_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('video_id')->unique();
            $table->string('artist')->nullable();
            $table->string('title', 500);
            $table->string('album')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->json('genres')->nullable();
            // pending → ok / not_found; manual = user-edited (never overwritten);
            // skipped = video flagged not-music.
            $table->string('enrich_status')->default('pending');
            $table->timestamp('enriched_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('enrich_status');
            $table->index('artist');
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('music_tracks');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('music_quota_log', function (Blueprint $table) {
            $table->id();
            $table->string('action');                 // e.g. playlistItems.list
            $table->unsignedInteger('units');         // YouTube quota units spent
            $table->date('used_on');                  // Pacific-time date (quota resets midnight PT)
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('used_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('music_quota_log');
    }
};

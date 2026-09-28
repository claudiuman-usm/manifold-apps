<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row table: the one Google account connected to the hub.
        Schema::create('music_google_tokens', function (Blueprint $table) {
            $table->id();
            $table->text('access_token');    // encrypted cast
            $table->text('refresh_token');   // encrypted cast
            $table->timestamp('expires_at')->nullable();
            $table->string('scope', 500)->nullable();
            $table->string('account_name')->nullable(); // YouTube channel title, for the settings panel
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('music_google_tokens');
    }
};

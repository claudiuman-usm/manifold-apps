<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Type sits under a client (e.g. AcmeCorp / Podcast, AcmeCorp / Reels).
        Schema::create('flower_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('flower_clients')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flower_types');
    }
};

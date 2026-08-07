<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flower_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_id')->constrained('flower_types')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flower_templates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flower_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('flower_templates')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['template_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flower_steps');
    }
};

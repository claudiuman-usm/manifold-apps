<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flower_step_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('flower_runs')->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('flower_steps')->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['run_id']);
            $table->index(['step_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flower_step_logs');
    }
};

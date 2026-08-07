<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flower_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('flower_templates')->cascadeOnDelete();
            $table->string('status')->default('in_progress'); // in_progress | completed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['template_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flower_runs');
    }
};

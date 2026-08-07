<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('original_path');          // uploaded original
            $table->string('image_path');             // 1:1 white-padded display image
            $table->string('merchant')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 3)->default(config('receipts.base_currency', 'RON'));
            $table->date('purchased_at')->nullable();
            $table->foreignId('category_id')->nullable()
                ->constrained('receipt_categories')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('status')->default('review'); // processing | review | done
            $table->timestamps();
            $table->softDeletes();

            $table->index('purchased_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};

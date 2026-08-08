<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('allocation_id')->nullable();
            $table->index('client_id');
            $table->index('allocation_id');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
            $table->dropIndex(['allocation_id']);
            $table->dropColumn(['client_id', 'allocation_id']);
        });
    }
};

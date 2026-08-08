<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_allocations', function (Blueprint $table) {
            $table->string('invoice_number')->nullable();
            $table->index('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('receipt_allocations', function (Blueprint $table) {
            $table->dropIndex(['invoice_number']);
            $table->dropColumn('invoice_number');
        });
    }
};

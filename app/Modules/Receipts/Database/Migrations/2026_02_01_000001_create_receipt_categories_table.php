<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        // Seed a starter set (editable later). Matched against AI output by name.
        $now = now();
        DB::table('receipt_categories')->insert(array_map(
            fn (string $name) => ['name' => $name, 'created_at' => $now, 'updated_at' => $now],
            ['Groceries', 'Meals', 'Transport', 'Office', 'Software', 'Travel', 'Utilities', 'Other'],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_categories');
    }
};

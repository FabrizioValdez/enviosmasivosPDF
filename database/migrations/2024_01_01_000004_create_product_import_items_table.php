<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('product_imports')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_code', 100)->index();
            $table->string('matched_code', 100)->nullable()->index();
            $table->decimal('old_price', 12, 2)->nullable();
            $table->decimal('new_price', 12, 2)->nullable();
            $table->decimal('old_cost', 12, 2)->nullable();
            $table->decimal('new_cost', 12, 2)->nullable();
            $table->decimal('old_stock', 15, 2)->nullable();
            $table->decimal('new_stock', 15, 2)->nullable();
            $table->string('status', 30)->default('PENDING')->index();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->string('match_level', 30)->nullable();
            $table->text('error_message')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_items');
    }
};

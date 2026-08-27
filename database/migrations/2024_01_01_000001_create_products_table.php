<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code', 100)->unique();
            $table->string('sku', 100)->nullable()->index();
            $table->string('internal_code', 100)->nullable()->index();
            $table->string('supplier_code', 100)->nullable()->index();
            $table->text('description');
            $table->string('family', 100)->nullable()->index();
            $table->string('line', 100)->nullable()->index();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('stock',15,2)->default(0);
            $table->string('unit', 20)->default('UND');
            $table->string('measurement', 50)->nullable();
            $table->string('brand', 100)->nullable()->index();
            $table->string('supplier', 100)->nullable()->index();
            $table->string('currency', 3)->default('USD');
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_code', 'active']);
            $table->index(['supplier_code', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

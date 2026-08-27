<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('filename', 255);
            $table->string('file_hash', 64)->index();
            $table->string('status', 30)->default('PENDING')->index();
            $table->integer('total_products')->default(0);
            $table->integer('processed_products')->default(0);
            $table->integer('updated_products')->default(0);
            $table->integer('failed_products')->default(0);
            $table->integer('not_found_products')->default(0);
            $table->integer('requires_review')->default(0);
            $table->decimal('ai_cost', 10, 6)->default(0);
            $table->integer('ai_tokens_input')->default(0);
            $table->integer('ai_tokens_output')->default(0);
            $table->integer('ai_calls')->default(0);
            $table->integer('processing_time_ms')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_imports');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pherce_intel.inventory_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name', 500);
            $table->string('unit', 30)->nullable();
            $table->decimal('current_stock', 14, 3)->default(0);
            $table->decimal('cost', 12, 4)->nullable();
            $table->decimal('sale_price', 12, 4)->nullable();
            $table->decimal('min_stock', 14, 3)->default(0);
            $table->timestamp('inventory_updated_at')->nullable();
            $table->string('import_source', 120)->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'name']);
            $table->index(['branch_id', 'current_stock']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pherce_intel.inventory_products');
    }
};

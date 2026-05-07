<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pherce_intel.inventory_products', function (Blueprint $table) {
            $table->decimal('last_purchase_cost', 12, 4)->nullable()->after('sale_price');
            $table->decimal('total_cost', 14, 4)->nullable()->after('last_purchase_cost');
            $table->string('supplier_code', 40)->nullable()->after('total_cost');
            $table->string('supplier_name', 255)->nullable()->after('supplier_code');
            $table->string('category_code', 40)->nullable()->after('supplier_name');
            $table->string('category_name', 160)->nullable()->after('category_code');
            $table->string('subcategory_code', 40)->nullable()->after('category_name');
            $table->string('subcategory_name', 160)->nullable()->after('subcategory_code');
            $table->timestamp('valued_inventory_updated_at')->nullable()->after('inventory_updated_at');

            $table->index(['branch_id', 'supplier_name']);
            $table->index(['branch_id', 'category_name']);
            $table->index(['branch_id', 'subcategory_name']);
        });
    }

    public function down(): void
    {
        Schema::table('pherce_intel.inventory_products', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'supplier_name']);
            $table->dropIndex(['branch_id', 'category_name']);
            $table->dropIndex(['branch_id', 'subcategory_name']);
            $table->dropColumn([
                'last_purchase_cost',
                'total_cost',
                'supplier_code',
                'supplier_name',
                'category_code',
                'category_name',
                'subcategory_code',
                'subcategory_name',
                'valued_inventory_updated_at',
            ]);
        });
    }
};

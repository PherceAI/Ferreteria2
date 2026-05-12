<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pherce_intel.inventory_products', function (Blueprint $table) {
            $table->index(['branch_id', 'supplier_name'], 'inventory_products_branch_supplier_idx');
            $table->index(['branch_id', 'category_name'], 'inventory_products_branch_category_idx');
            $table->index(['branch_id', 'subcategory_name'], 'inventory_products_branch_subcategory_idx');
            $table->index(['branch_id', 'min_stock', 'current_stock'], 'inventory_products_branch_stock_floor_idx');
        });

        Schema::table('pherce_intel.purchase_invoices', function (Blueprint $table) {
            $table->index(['branch_id', 'status', 'created_at'], 'purchase_invoices_branch_status_created_idx');
            $table->index(['branch_id', 'status', 'updated_at'], 'purchase_invoices_branch_status_updated_idx');
            $table->index(['supplier_id', 'status'], 'purchase_invoices_supplier_status_idx');
        });

        Schema::table('pherce_intel.reception_confirmations', function (Blueprint $table) {
            $table->index(['branch_id', 'status'], 'reception_confirmations_branch_status_idx');
            $table->index(['invoice_id', 'status'], 'reception_confirmations_invoice_status_idx');
        });

        Schema::table('pherce_intel.suppliers', function (Blueprint $table) {
            $table->index(['branch_id', 'is_active'], 'suppliers_branch_active_idx');
        });

        Schema::table('pherce_intel.branch_transfer_items', function (Blueprint $table) {
            $table->index('inventory_product_id', 'branch_transfer_items_inventory_product_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pherce_intel.branch_transfer_items', function (Blueprint $table) {
            $table->dropIndex('branch_transfer_items_inventory_product_idx');
        });

        Schema::table('pherce_intel.suppliers', function (Blueprint $table) {
            $table->dropIndex('suppliers_branch_active_idx');
        });

        Schema::table('pherce_intel.reception_confirmations', function (Blueprint $table) {
            $table->dropIndex('reception_confirmations_branch_status_idx');
            $table->dropIndex('reception_confirmations_invoice_status_idx');
        });

        Schema::table('pherce_intel.purchase_invoices', function (Blueprint $table) {
            $table->dropIndex('purchase_invoices_branch_status_created_idx');
            $table->dropIndex('purchase_invoices_branch_status_updated_idx');
            $table->dropIndex('purchase_invoices_supplier_status_idx');
        });

        Schema::table('pherce_intel.inventory_products', function (Blueprint $table) {
            $table->dropIndex('inventory_products_branch_supplier_idx');
            $table->dropIndex('inventory_products_branch_category_idx');
            $table->dropIndex('inventory_products_branch_subcategory_idx');
            $table->dropIndex('inventory_products_branch_stock_floor_idx');
        });
    }
};

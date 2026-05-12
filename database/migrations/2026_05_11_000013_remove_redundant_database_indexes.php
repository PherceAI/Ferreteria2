<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS pherce_intel.inventory_products_branch_supplier_idx');
        DB::statement('DROP INDEX IF EXISTS pherce_intel.inventory_products_branch_category_idx');
        DB::statement('DROP INDEX IF EXISTS pherce_intel.inventory_products_branch_subcategory_idx');

        DB::statement('ALTER TABLE pherce_intel.suppliers DROP CONSTRAINT IF EXISTS pherce_intel_suppliers_branch_id_ruc_unique');
    }

    public function down(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS inventory_products_branch_supplier_idx ON pherce_intel.inventory_products (branch_id, supplier_name)');
        DB::statement('CREATE INDEX IF NOT EXISTS inventory_products_branch_category_idx ON pherce_intel.inventory_products (branch_id, category_name)');
        DB::statement('CREATE INDEX IF NOT EXISTS inventory_products_branch_subcategory_idx ON pherce_intel.inventory_products (branch_id, subcategory_name)');

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'pherce_intel_suppliers_branch_id_ruc_unique'
    ) THEN
        ALTER TABLE pherce_intel.suppliers
            ADD CONSTRAINT pherce_intel_suppliers_branch_id_ruc_unique UNIQUE (branch_id, ruc);
    END IF;
END
$$;
SQL);
    }
};

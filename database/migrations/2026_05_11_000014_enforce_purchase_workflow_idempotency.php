<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS reception_confirmations_invoice_id_unique_idx
    ON pherce_intel.reception_confirmations (invoice_id)
    WHERE invoice_id IS NOT NULL
SQL);

        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS reception_confirmation_items_invoice_item_unique_idx
    ON pherce_intel.reception_confirmation_items (confirmation_id, purchase_invoice_item_id)
    WHERE purchase_invoice_item_id IS NOT NULL
SQL);

        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS purchase_invoice_events_once_unique_idx
    ON pherce_intel.purchase_invoice_events (invoice_id, type)
    WHERE type <> 'item_discrepancy'
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS pherce_intel.purchase_invoice_events_once_unique_idx');
        DB::statement('DROP INDEX IF EXISTS pherce_intel.reception_confirmation_items_invoice_item_unique_idx');
        DB::statement('DROP INDEX IF EXISTS pherce_intel.reception_confirmations_invoice_id_unique_idx');
    }
};

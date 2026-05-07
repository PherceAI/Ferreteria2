<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pherce_intel.purchase_invoices')
            ->where('status', 'pending')
            ->update(['status' => 'awaiting_physical']);
    }

    public function down(): void
    {
        //
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pherce_intel.branch_transfers', function (Blueprint $table) {
            $table->string('request_key', 64)->nullable()->after('requested_by');
        });

        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS branch_transfers_request_key_unique_idx
    ON pherce_intel.branch_transfers (request_key)
    WHERE request_key IS NOT NULL
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS pherce_intel.branch_transfers_request_key_unique_idx');

        Schema::table('pherce_intel.branch_transfers', function (Blueprint $table) {
            $table->dropColumn('request_key');
        });
    }
};

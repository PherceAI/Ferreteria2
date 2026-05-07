<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pherce_intel.suppliers', function (Blueprint $table) {
            $table->string('ruc_hash', 64)->nullable()->after('ruc');
        });

        $seen = [];

        DB::table('pherce_intel.suppliers')
            ->select(['id', 'branch_id', 'ruc'])
            ->orderBy('id')
            ->get()
            ->each(function (object $supplier) use (&$seen): void {
                $hash = $this->supplierRucHash((string) $supplier->ruc);
                $key = $supplier->branch_id.':'.$hash;

                if (isset($seen[$key])) {
                    return;
                }

                $seen[$key] = true;

                DB::table('pherce_intel.suppliers')
                    ->where('id', $supplier->id)
                    ->update(['ruc_hash' => $hash]);
            });

        Schema::table('pherce_intel.suppliers', function (Blueprint $table) {
            $table->unique(['branch_id', 'ruc_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('pherce_intel.suppliers', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'ruc_hash']);
            $table->dropColumn('ruc_hash');
        });
    }

    private function supplierRucHash(string $ruc): string
    {
        try {
            $ruc = Crypt::decryptString($ruc);
        } catch (Throwable) {
            //
        }

        return hash('sha256', Str::lower(preg_replace('/\D+/', '', $ruc) ?? $ruc));
    }
};

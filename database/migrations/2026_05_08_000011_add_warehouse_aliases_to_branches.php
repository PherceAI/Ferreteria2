<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('warehouse_name', 100)->nullable()->after('code');
            $table->string('warehouse_code', 10)->nullable()->unique()->after('warehouse_name');
        });

        $now = now();
        $branches = [
            [
                'legacy_codes' => ['RIO1'],
                'name' => 'MATRIZ',
                'code' => 'B10',
                'warehouse_name' => 'BODEGA 10',
                'warehouse_code' => '10',
                'city' => 'Riobamba',
                'is_headquarters' => true,
            ],
            [
                'legacy_codes' => ['RIO2'],
                'name' => 'SUCURSAL 1',
                'code' => 'B20',
                'warehouse_name' => 'BODEGA 20',
                'warehouse_code' => '20',
                'city' => 'Riobamba',
                'is_headquarters' => false,
            ],
            [
                'legacy_codes' => ['RIO3'],
                'name' => 'SUCURSAL 3',
                'code' => 'B30',
                'warehouse_name' => 'BODEGA 30',
                'warehouse_code' => '30',
                'city' => 'Riobamba',
                'is_headquarters' => false,
            ],
            [
                'legacy_codes' => ['MAC1'],
                'name' => 'SUCURSAL 4',
                'code' => 'B40',
                'warehouse_name' => 'BODEGA 40',
                'warehouse_code' => '40',
                'city' => 'Macas',
                'is_headquarters' => false,
            ],
        ];

        foreach ($branches as $branch) {
            $payload = [
                'name' => $branch['name'],
                'code' => $branch['code'],
                'warehouse_name' => $branch['warehouse_name'],
                'warehouse_code' => $branch['warehouse_code'],
                'city' => $branch['city'],
                'is_headquarters' => $branch['is_headquarters'],
                'is_active' => true,
                'updated_at' => $now,
            ];

            $existing = DB::table('branches')
                ->where('code', $branch['code'])
                ->orWhereIn('code', $branch['legacy_codes'])
                ->first();

            if ($existing) {
                DB::table('branches')
                    ->where('id', $existing->id)
                    ->update($payload);

                continue;
            }

            DB::table('branches')->insert([
                ...$payload,
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique(['warehouse_code']);
            $table->dropColumn(['warehouse_name', 'warehouse_code']);
        });
    }
};

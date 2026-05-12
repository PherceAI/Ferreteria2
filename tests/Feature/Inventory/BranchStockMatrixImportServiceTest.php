<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Inventory\Services\BranchStockMatrixImportService;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BranchStockMatrixImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_stock_by_real_warehouse_code(): void
    {
        $matrix = Branch::query()->where('warehouse_code', '10')->firstOrFail();
        $branch = Branch::query()->where('warehouse_code', '20')->firstOrFail();
        $path = storage_path('framework/testing/branch-stock-matrix.csv');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, implode(PHP_EOL, [
            ' Codigo         ;Bod;             Descripcion                           ;Ubica.;UM;   Stock',
            '044020101;10;10X30 LIST ATLANTA PZ;;UN;22',
            '014020053;20;2100I-PERM (BASE I) GALON;;GL;1,5',
            'PVC-A1;20;TUBO PVC CODIGO MIXTO;;UN;4',
            '014110022;25;SW - CHARISMA - C;;UN;786',
            '',
        ]));

        $result = app(BranchStockMatrixImportService::class)->importCsv($path, 'test-matrix');

        $this->assertSame(3, $result['imported']);
        $this->assertSame(1, $result['unknown_branch']);
        $this->assertSame(['10' => 1, '20' => 2], $result['by_branch']);

        $this->assertDatabaseHas('pherce_intel.inventory_products', [
            'branch_id' => $matrix->id,
            'code' => '044020101',
            'current_stock' => '22.000',
        ]);

        $this->assertDatabaseHas('pherce_intel.inventory_products', [
            'branch_id' => $branch->id,
            'code' => '014020053',
            'current_stock' => '1.500',
        ]);

        $this->assertDatabaseHas('pherce_intel.inventory_products', [
            'branch_id' => $branch->id,
            'code' => 'PVC-A1',
            'current_stock' => '4.000',
        ]);

        $this->assertSame(3, InventoryProduct::withoutBranchScope()->count());
    }

    public function test_it_normalizes_windows_encoded_excel_text_before_database_insert(): void
    {
        $matrix = Branch::query()->where('warehouse_code', '10')->firstOrFail();
        $path = storage_path('framework/testing/branch-stock-matrix-windows.csv');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, implode(PHP_EOL, [
            'Codigo;Bod;Descripcion;Ubica.;UM;Stock',
            '099000001;10;DISENO '.chr(209).'ORIENTE;;UN;7',
        ]));

        $result = app(BranchStockMatrixImportService::class)->importCsv($path, 'fuente '.chr(209));

        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseHas('pherce_intel.inventory_products', [
            'branch_id' => $matrix->id,
            'code' => '099000001',
            'name' => 'DISENO ÑORIENTE',
            'import_source' => 'fuente Ñ',
        ]);
    }
}

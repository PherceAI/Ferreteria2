<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Inventory\Services\ValuedInventoryImportService;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ValuedInventoryImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_valued_inventory_with_warehouse_column_and_last_purchase_cost(): void
    {
        $branch = Branch::query()->where('warehouse_code', '20')->firstOrFail();
        $path = storage_path('framework/testing/valued-inventory-warehouse.csv');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        InventoryProduct::query()->create([
            'branch_id' => $branch->id,
            'code' => '001010001',
            'name' => 'SPRAY ALUMINIO / ABRO',
            'unit' => 'UN',
            'current_stock' => 0,
            'min_stock' => 0,
        ]);

        file_put_contents($path, implode(PHP_EOL, [
            'Codigo,Descripcion,UM.,Bo.,Existencia,Costo Unit.,Ult.Compra,T.Costo,Prov.',
            'GRUPO           001     ABRO',
            'SUBGRUPO        00101   3-PINTURA',
            '001010001,SPRAY ALUMINIO / ABRO,UN,20,13,"1,6844","1,645","21,8972",000019 FERREMUNDO',
        ]));

        $result = app(ValuedInventoryImportService::class)->importCsv($path, $branch->id, 'test-valued-b20');

        $this->assertSame(1, $result['matched']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['missing']);

        $this->assertDatabaseHas('pherce_intel.inventory_products', [
            'branch_id' => $branch->id,
            'code' => '001010001',
            'current_stock' => '13.000',
            'cost' => '1.6844',
            'last_purchase_cost' => '1.6450',
            'total_cost' => '21.8972',
            'supplier_code' => '000019',
            'supplier_name' => 'FERREMUNDO',
            'category_code' => '001',
            'category_name' => 'ABRO',
            'subcategory_code' => '00101',
            'subcategory_name' => '3-PINTURA',
        ]);
    }

    public function test_it_creates_branch_products_that_only_exist_in_the_valued_inventory_file(): void
    {
        $branch = Branch::query()->where('warehouse_code', '20')->firstOrFail();
        $path = storage_path('framework/testing/valued-inventory-new-product.csv');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, implode(PHP_EOL, [
            'Codigo,Descripcion,UM.,Bo.,Existencia,Costo Unit.,Ult.Compra,T.Costo,Prov.',
            'GRUPO           006     LIMAS',
            'SUBGRUPO        00601   TRIANGULARES',
            '006010011,LIMA TRIANGULAR 09,UN,20,2,"2,335","2,335","4,67",000057 IMPORTADOR FERRETERO TRUJILLO CIA',
        ]));

        $result = app(ValuedInventoryImportService::class)->importCsv($path, $branch->id, 'test-valued-create-b20');

        $this->assertSame(0, $result['matched']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['missing']);

        $this->assertDatabaseHas('pherce_intel.inventory_products', [
            'branch_id' => $branch->id,
            'code' => '006010011',
            'name' => 'LIMA TRIANGULAR 09',
            'current_stock' => '2.000',
            'cost' => '2.3350',
            'total_cost' => '4.6700',
            'supplier_code' => '000057',
            'supplier_name' => 'IMPORTADOR FERRETERO TRUJILLO CIA',
            'category_code' => '006',
            'category_name' => 'LIMAS',
            'subcategory_code' => '00601',
            'subcategory_name' => 'TRIANGULARES',
        ]);
    }

    public function test_valued_inventory_import_can_be_retried_without_duplicate_products(): void
    {
        $branch = Branch::query()->where('warehouse_code', '20')->firstOrFail();
        $path = storage_path('framework/testing/valued-inventory-retry.csv');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, implode(PHP_EOL, [
            'Codigo,Descripcion,UM.,Bo.,Existencia,Costo Unit.,Ult.Compra,T.Costo,Prov.',
            'GRUPO           006     LIMAS',
            '006010099,LIMA PLANA 10,UN,20,3,"2,00","2,00","6,00",000057 IMPORTADOR FERRETERO TRUJILLO CIA',
        ]));

        $firstRun = app(ValuedInventoryImportService::class)->importCsv($path, $branch->id, 'test-valued-retry-b20');
        $secondRun = app(ValuedInventoryImportService::class)->importCsv($path, $branch->id, 'test-valued-retry-b20');

        $this->assertSame(1, $firstRun['created']);
        $this->assertSame(0, $secondRun['created']);
        $this->assertSame(1, $secondRun['matched']);
        $this->assertSame(1, InventoryProduct::query()
            ->where('branch_id', $branch->id)
            ->where('code', '006010099')
            ->count());
    }

    public function test_it_keeps_alphanumeric_product_codes_from_valued_inventory(): void
    {
        $branch = Branch::query()->where('warehouse_code', '20')->firstOrFail();
        $path = storage_path('framework/testing/valued-inventory-alpha-code.csv');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, implode(PHP_EOL, [
            'Codigo,Descripcion,UM.,Bo.,Existencia,Costo Unit.,Ult.Compra,T.Costo,Prov.',
            'GRUPO           014     PVC',
            'PVC-A1,TUBO PVC CODIGO MIXTO,UN,20,4,"1,25","1,25","5,00",000777 PROVEEDOR MIXTO',
        ]));

        $result = app(ValuedInventoryImportService::class)->importCsv($path, $branch->id, 'test-valued-alpha-b20');

        $this->assertSame(0, $result['matched']);
        $this->assertSame(1, $result['created']);

        $this->assertDatabaseHas('pherce_intel.inventory_products', [
            'branch_id' => $branch->id,
            'code' => 'PVC-A1',
            'name' => 'TUBO PVC CODIGO MIXTO',
            'current_stock' => '4.000',
        ]);
    }
}

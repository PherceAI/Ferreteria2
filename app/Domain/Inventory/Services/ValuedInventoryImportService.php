<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\InventoryProduct;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use SplFileObject;

final class ValuedInventoryImportService
{
    /**
     * @return array{matched:int, created:int, missing:int, skipped:int}
     */
    public function importCsv(string $path, int $branchId, string $source): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $matched = 0;
        $created = 0;
        $missing = 0;
        $skipped = 0;
        $chunk = [];
        $createChunk = [];
        $existingCodes = InventoryProduct::query()
            ->forBranch($branchId)
            ->pluck('code')
            ->mapWithKeys(fn (string $code): array => [$code => true])
            ->all();

        $categoryCode = null;
        $categoryName = null;
        $subcategoryCode = null;
        $subcategoryName = null;
        $now = now();

        foreach ($file as $rowIndex => $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            $row = array_pad($row, 9, '');
            $first = trim((string) $row[0]);
            $productName = $this->cleanText($row[1] ?? null);

            if ($rowIndex === 0 && str_contains(mb_strtolower($first), 'codigo')) {
                continue;
            }

            if ($this->parseGroup($first) !== null) {
                [$categoryCode, $categoryName] = $this->parseGroup($first);
                $subcategoryCode = null;
                $subcategoryName = null;

                continue;
            }

            if ($this->parseSubgroup($first) !== null) {
                [$subcategoryCode, $subcategoryName] = $this->parseSubgroup($first);

                continue;
            }

            if ($first === '' || $productName === '') {
                $skipped++;

                continue;
            }

            if (! isset($existingCodes[$first])) {
                $createChunk[] = $this->newProductRow(
                    row: $row,
                    branchId: $branchId,
                    source: $source,
                    sourceRow: $rowIndex + 1,
                    categoryCode: $categoryCode,
                    categoryName: $categoryName,
                    subcategoryCode: $subcategoryCode,
                    subcategoryName: $subcategoryName,
                    now: $now,
                );
                $existingCodes[$first] = true;

                if (count($createChunk) === 500) {
                    $created += $this->createMissing($createChunk);
                    $createChunk = [];
                }

                continue;
            }

            $columns = $this->valuedColumns($row);
            $supplier = $this->parseSupplier((string) $row[$columns['supplier']]);

            $chunk[] = [
                'branch_id' => $branchId,
                'code' => $first,
                'unit' => $this->cleanText($row[$columns['unit']] ?? null) ?: null,
                'current_stock' => $this->parseDecimal($row[$columns['stock']] ?? null),
                'cost' => $this->parseDecimal($row[$columns['cost']] ?? null),
                'last_purchase_cost' => $this->parseDecimal($row[$columns['last_purchase']] ?? null),
                'total_cost' => $this->parseDecimal($row[$columns['total_cost']] ?? null),
                'supplier_code' => $supplier['code'],
                'supplier_name' => $supplier['name'],
                'category_code' => $categoryCode,
                'category_name' => $categoryName,
                'subcategory_code' => $subcategoryCode,
                'subcategory_name' => $subcategoryName,
                'valued_inventory_updated_at' => Carbon::now('America/Guayaquil'),
                'import_source' => $source,
                'source_row' => $rowIndex + 1,
                'updated_at' => $now,
            ];

            if (count($chunk) === 500) {
                $matched += $this->updateExisting($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $matched += $this->updateExisting($chunk);
        }

        if ($createChunk !== []) {
            $created += $this->createMissing($createChunk);
        }

        Cache::tags(['inventory-products', "branch:{$branchId}"])->flush();

        return [
            'matched' => $matched,
            'created' => $created,
            'missing' => $missing,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function updateExisting(array $rows): int
    {
        $updated = 0;
        $table = (new InventoryProduct)->getTable();

        foreach ($rows as $row) {
            $branchId = $row['branch_id'];
            $code = $row['code'];

            unset($row['branch_id'], $row['code']);

            $updated += DB::table($table)
                ->where('branch_id', $branchId)
                ->where('code', $code)
                ->update($row);
        }

        return $updated;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>
     */
    private function newProductRow(
        array $row,
        int $branchId,
        string $source,
        int $sourceRow,
        ?string $categoryCode,
        ?string $categoryName,
        ?string $subcategoryCode,
        ?string $subcategoryName,
        CarbonInterface $now,
    ): array {
        $columns = $this->valuedColumns($row);
        $supplier = $this->parseSupplier((string) $row[$columns['supplier']]);

        return [
            'branch_id' => $branchId,
            'code' => $this->cleanText($row[0] ?? null),
            'name' => $this->cleanText($row[1] ?? null),
            'unit' => $this->cleanText($row[$columns['unit']] ?? null) ?: null,
            'current_stock' => $this->parseDecimal($row[$columns['stock']] ?? null),
            'cost' => $this->parseDecimal($row[$columns['cost']] ?? null),
            'last_purchase_cost' => $this->parseDecimal($row[$columns['last_purchase']] ?? null),
            'total_cost' => $this->parseDecimal($row[$columns['total_cost']] ?? null),
            'supplier_code' => $supplier['code'],
            'supplier_name' => $supplier['name'],
            'category_code' => $categoryCode,
            'category_name' => $categoryName,
            'subcategory_code' => $subcategoryCode,
            'subcategory_name' => $subcategoryName,
            'min_stock' => 0,
            'valued_inventory_updated_at' => Carbon::now('America/Guayaquil'),
            'import_source' => $source,
            'source_row' => $sourceRow,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function createMissing(array $rows): int
    {
        DB::table((new InventoryProduct)->getTable())->upsert(
            $rows,
            ['branch_id', 'code'],
            [
                'name',
                'unit',
                'current_stock',
                'cost',
                'last_purchase_cost',
                'total_cost',
                'supplier_code',
                'supplier_name',
                'category_code',
                'category_name',
                'subcategory_code',
                'subcategory_name',
                'valued_inventory_updated_at',
                'import_source',
                'source_row',
                'updated_at',
            ],
        );

        return count($rows);
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array{unit:int,stock:int,cost:int,last_purchase:int,total_cost:int,supplier:int}
     */
    private function valuedColumns(array $row): array
    {
        $warehouse = $this->cleanText($row[3] ?? null);

        if ($warehouse !== '' && ctype_digit($warehouse)) {
            return [
                'unit' => 2,
                'stock' => 4,
                'cost' => 5,
                'last_purchase' => 6,
                'total_cost' => 7,
                'supplier' => 8,
            ];
        }

        return [
            'unit' => 2,
            'stock' => 3,
            'cost' => 4,
            'last_purchase' => 4,
            'total_cost' => 5,
            'supplier' => 6,
        ];
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function parseGroup(string $value): ?array
    {
        if (! preg_match('/^GRUPO\s+(\S+)\s+(.+)$/u', $value, $matches)) {
            return null;
        }

        return [$matches[1], trim($matches[2])];
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function parseSubgroup(string $value): ?array
    {
        if (! preg_match('/^SUBGRUPO\s+(\S+)\s+(.+)$/u', $value, $matches)) {
            return null;
        }

        return [$matches[1], trim($matches[2])];
    }

    /**
     * @return array{code:?string,name:?string}
     */
    private function parseSupplier(string $value): array
    {
        $clean = $this->cleanText($value);

        if ($clean === '') {
            return ['code' => null, 'name' => null];
        }

        if (preg_match('/^(\S+)\s+(.+)$/u', $clean, $matches)) {
            return ['code' => $matches[1], 'name' => trim($matches[2])];
        }

        return ['code' => null, 'name' => $clean];
    }

    private function cleanText(mixed $value): string
    {
        return trim((string) $value, " \t\n\r\0\x0B\"'");
    }

    private function parseDecimal(mixed $value): string
    {
        $normalized = str_replace(',', '.', trim((string) $value));

        if ($normalized === '' || ! is_numeric($normalized)) {
            return '0';
        }

        return $normalized;
    }
}

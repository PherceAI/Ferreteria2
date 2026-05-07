<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\InventoryProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use SplFileObject;

final class ValuedInventoryImportService
{
    /**
     * @return array{matched:int, missing:int, skipped:int}
     */
    public function importCsv(string $path, int $branchId, string $source): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $matched = 0;
        $missing = 0;
        $skipped = 0;
        $chunk = [];
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

            $row = array_pad($row, 8, '');
            $first = trim((string) $row[0]);

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

            if (! preg_match('/^\d+$/', $first)) {
                $skipped++;

                continue;
            }

            if (! isset($existingCodes[$first])) {
                $missing++;

                continue;
            }

            $supplier = $this->parseSupplier((string) $row[6]);

            $chunk[] = [
                'branch_id' => $branchId,
                'code' => $first,
                'unit' => $this->cleanText($row[2] ?? null) ?: null,
                'current_stock' => $this->parseDecimal($row[3] ?? null),
                'cost' => $this->parseDecimal($row[4] ?? null),
                'last_purchase_cost' => $this->parseDecimal($row[4] ?? null),
                'total_cost' => $this->parseDecimal($row[5] ?? null),
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

        Cache::tags(['inventory-products', "branch:{$branchId}"])->flush();

        return [
            'matched' => $matched,
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

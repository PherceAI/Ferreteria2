<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Models\Branch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use SplFileObject;

final class BranchStockMatrixImportService
{
    /**
     * @return array{imported:int, skipped:int, unknown_branch:int, by_branch:array<string,int>}
     */
    public function importCsv(string $path, string $source): array
    {
        $source = $this->cleanText($source);
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(';');

        $branchesByWarehouseCode = Branch::query()
            ->whereNotNull('warehouse_code')
            ->pluck('id', 'warehouse_code')
            ->mapWithKeys(fn (int $id, string $warehouseCode): array => [trim($warehouseCode) => $id])
            ->all();

        $imported = 0;
        $skipped = 0;
        $unknownBranch = 0;
        $byBranch = [];
        $chunk = [];
        $now = now();
        $inventoryUpdatedAt = Carbon::now('America/Guayaquil');

        foreach ($file as $rowIndex => $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            $row = array_pad($row, 6, '');
            $code = $this->cleanText($row[0] ?? null);
            $warehouseCode = $this->cleanText($row[1] ?? null);
            $name = $this->cleanText($row[2] ?? null);

            if (! $this->isProductRow($code, $warehouseCode, $name)) {
                $skipped++;

                continue;
            }

            $branchId = $branchesByWarehouseCode[$warehouseCode] ?? null;

            if ($branchId === null) {
                $unknownBranch++;

                continue;
            }

            $chunk[] = [
                'branch_id' => $branchId,
                'code' => $code,
                'name' => $name,
                'unit' => $this->cleanText($row[4] ?? null) ?: null,
                'current_stock' => $this->parseDecimal($row[5] ?? null),
                'min_stock' => 0,
                'inventory_updated_at' => $inventoryUpdatedAt,
                'import_source' => $source,
                'source_row' => $rowIndex + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $byBranch[$warehouseCode] = ($byBranch[$warehouseCode] ?? 0) + 1;

            if (count($chunk) === 500) {
                $imported += $this->upsert($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $imported += $this->upsert($chunk);
        }

        foreach ($branchesByWarehouseCode as $branchId) {
            Cache::tags(['inventory-products', "branch:{$branchId}"])->flush();
        }

        ksort($byBranch);

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'unknown_branch' => $unknownBranch,
            'by_branch' => $byBranch,
        ];
    }

    private function isProductRow(string $code, string $warehouseCode, string $name): bool
    {
        return $code !== ''
            && $warehouseCode !== ''
            && $name !== ''
            && ctype_digit($warehouseCode);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function upsert(array $rows): int
    {
        DB::table((new InventoryProduct)->getTable())->upsert(
            $rows,
            ['branch_id', 'code'],
            [
                'name',
                'unit',
                'current_stock',
                'inventory_updated_at',
                'import_source',
                'source_row',
                'updated_at',
            ],
        );

        return count($rows);
    }

    private function cleanText(mixed $value): string
    {
        $text = $this->toUtf8((string) $value);
        $text = str_replace("\xEF\xBB\xBF", '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;

        return trim($text, " \t\n\r\0\x0B\"'");
    }

    private function toUtf8(string $text): string
    {
        if ($text === '' || mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        foreach (['Windows-1252', 'ISO-8859-1'] as $encoding) {
            $converted = @mb_convert_encoding($text, 'UTF-8', $encoding);

            if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $text);

        if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);

        if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        return preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
    }

    private function parseDecimal(mixed $value): string
    {
        $normalized = str_replace(',', '.', $this->cleanText($value));

        if ($normalized === '' || ! is_numeric($normalized)) {
            return '0';
        }

        return $normalized;
    }
}

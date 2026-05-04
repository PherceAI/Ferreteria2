<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\InventoryProduct;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use SplFileObject;

final class InventoryProductImportService
{
    /**
     * @return array{imported:int, skipped:int}
     */
    public function importCsv(string $path, int $branchId, string $source, Carbon $inventoryUpdatedAt): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $imported = 0;
        $skipped = 0;
        $chunk = [];
        $now = now();

        foreach ($file as $rowIndex => $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            if ($rowIndex === 0 && $this->looksLikeHeader($row)) {
                continue;
            }

            $normalized = $this->normalizeRow($row, $branchId, $source, $rowIndex + 1, $inventoryUpdatedAt, $now);

            if ($normalized === null) {
                $skipped++;

                continue;
            }

            $chunk[] = $normalized;

            if (count($chunk) === 500) {
                $imported += $this->upsert($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $imported += $this->upsert($chunk);
        }

        Cache::tags(['inventory-products', "branch:{$branchId}"])->flush();

        return [
            'imported' => $imported,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function normalizeRow(array $row, int $branchId, string $source, int $sourceRow, Carbon $inventoryUpdatedAt, CarbonInterface $now): ?array
    {
        $code = $this->cleanText($row[0] ?? null);
        $name = $this->cleanText($row[1] ?? null);
        $unit = $this->cleanText($row[2] ?? null);
        $stock = $this->parseDecimal($row[3] ?? null);

        if ($code === '' || $name === '' || $code === 'Bodega') {
            return null;
        }

        return [
            'branch_id' => $branchId,
            'code' => $code,
            'name' => $name,
            'unit' => $unit !== '' ? $unit : null,
            'current_stock' => $stock,
            'cost' => null,
            'sale_price' => null,
            'min_stock' => 0,
            'inventory_updated_at' => $inventoryUpdatedAt,
            'import_source' => $source,
            'source_row' => $sourceRow,
            'created_at' => $now,
            'updated_at' => $now,
        ];
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

    /**
     * @param  array<int, mixed>  $row
     */
    private function looksLikeHeader(array $row): bool
    {
        $header = mb_strtolower($this->cleanText($row[0] ?? null));

        return $header === 'code' || str_contains($header, 'codigo');
    }

    private function cleanText(mixed $value): string
    {
        return trim((string) $value, " \t\n\r\0\x0B\"'﻿");
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

<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Services;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Purchasing\DTOs\PurchaseDocumentMetadata;
use App\Domain\Purchasing\Models\Supplier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class SupplierDocumentFilterService
{
    public function isAllowed(PurchaseDocumentMetadata $metadata, int $branchId): bool
    {
        if (! $metadata->isRelevantPurchaseDocument()) {
            return false;
        }

        $suppliers = $this->knownSuppliers($branchId);
        $ruc = $this->digits($metadata->supplierRuc);
        $name = $this->normalize($metadata->supplierName);
        $compactName = $this->compact($metadata->supplierName);

        if ($ruc !== '' && in_array($ruc, $suppliers['codes'], true)) {
            return true;
        }

        if ($name === '') {
            return false;
        }

        foreach ($suppliers['names'] as $supplierName) {
            if ($supplierName === $name) {
                return true;
            }

            if (mb_strlen($supplierName) >= 8 && (str_contains($supplierName, $name) || str_contains($name, $supplierName))) {
                return true;
            }
        }

        if ($compactName !== '') {
            foreach ($suppliers['compact_names'] as $supplierName) {
                if ($supplierName === $compactName) {
                    return true;
                }

                if (mb_strlen($supplierName) >= 8 && (str_contains($supplierName, $compactName) || str_contains($compactName, $supplierName))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int,int>
     */
    public function allowedSupplierIds(int $branchId): array
    {
        $suppliers = $this->knownSuppliers($branchId);
        $codeHashes = collect($suppliers['codes'])
            ->map(fn (string $code): string => hash('sha256', $code))
            ->all();

        return Supplier::withoutBranchScope()
            ->where('branch_id', $branchId)
            ->get(['id', 'name', 'ruc_hash'])
            ->filter(function (Supplier $supplier) use ($codeHashes, $suppliers): bool {
                if ($supplier->ruc_hash !== null && in_array($supplier->ruc_hash, $codeHashes, true)) {
                    return true;
                }

                $name = $this->normalize($supplier->name);
                $compactName = $this->compact($supplier->name);

                return in_array($name, $suppliers['names'], true)
                    || in_array($compactName, $suppliers['compact_names'], true);
            })
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->values()
            ->all();
    }

    /**
     * @return array{codes:array<int,string>, names:array<int,string>, compact_names:array<int,string>}
     */
    private function knownSuppliers(int $branchId): array
    {
        return Cache::tags(['inventory-products', "branch:{$branchId}"])
            ->remember("purchase-documents:known-suppliers:v2:{$branchId}", 300, function () use ($branchId): array {
                $rows = InventoryProduct::query()
                    ->forBranch($branchId)
                    ->select(['supplier_code', 'supplier_name'])
                    ->where(function ($query): void {
                        $query
                            ->whereNotNull('supplier_code')
                            ->orWhereNotNull('supplier_name');
                    })
                    ->distinct()
                    ->limit(2000)
                    ->get();

                return [
                    'codes' => $rows
                        ->pluck('supplier_code')
                        ->map(fn (?string $value): string => $this->digits($value ?? ''))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                    'names' => $rows
                        ->pluck('supplier_name')
                        ->map(fn (?string $value): string => $this->normalize($value ?? ''))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                    'compact_names' => $rows
                        ->pluck('supplier_name')
                        ->map(fn (?string $value): string => $this->compact($value ?? ''))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                ];
            });
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function normalize(string $value): string
    {
        $value = Str::ascii($value);
        $value = Str::lower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function compact(string $value): string
    {
        $value = Str::ascii($value);
        $value = Str::lower($value);

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }
}

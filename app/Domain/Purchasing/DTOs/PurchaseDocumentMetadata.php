<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\DTOs;

use Spatie\LaravelData\Data;

final class PurchaseDocumentMetadata extends Data
{
    public function __construct(
        public readonly string $documentType,
        public readonly string $supplierRuc,
        public readonly string $supplierName,
        public readonly ?string $accessKey = null,
    ) {}

    public function isRelevantPurchaseDocument(): bool
    {
        return in_array($this->documentType, ['factura', 'notaCredito'], true);
    }
}

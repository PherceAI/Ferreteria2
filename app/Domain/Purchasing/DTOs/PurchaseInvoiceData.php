<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\DTOs;

use Spatie\LaravelData\Data;

final class PurchaseInvoiceData extends Data
{
    /**
     * @param  array<InvoiceLineItemData>  $items
     */
    public function __construct(
        public readonly string $supplierRuc,
        public readonly string $supplierName,
        public readonly string $invoiceNumber,
        public readonly string $accessKey,
        public readonly string $emissionDate,
        public readonly float $total,
        public readonly array $items,
        public readonly string $gmailMessageId,
        public readonly string $fromEmail,
    ) {}
}

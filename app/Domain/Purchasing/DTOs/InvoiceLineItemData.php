<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\DTOs;

use Spatie\LaravelData\Data;

final class InvoiceLineItemData extends Data
{
    public function __construct(
        public readonly ?string $code,
        public readonly string $description,
        public readonly float $quantity,
        public readonly float $unitPrice,
        public readonly float $subtotal,
    ) {}
}

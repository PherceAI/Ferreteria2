<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Events;

use App\Domain\Purchasing\Models\PurchaseInvoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InvoiceEmailReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PurchaseInvoice $invoice,
    ) {}
}

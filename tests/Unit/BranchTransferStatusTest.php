<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Warehouse\Models\BranchTransfer;
use Tests\TestCase;

final class BranchTransferStatusTest extends TestCase
{
    public function test_all_transfer_statuses_have_labels(): void
    {
        $statuses = [
            BranchTransfer::STATUS_REQUESTED,
            BranchTransfer::STATUS_PREPARING,
            BranchTransfer::STATUS_READY_TO_SHIP,
            BranchTransfer::STATUS_IN_TRANSIT,
            BranchTransfer::STATUS_RECEIVED,
            BranchTransfer::STATUS_RECEIVED_DISCREPANCY,
            BranchTransfer::STATUS_TINI_COMPLETED,
            BranchTransfer::STATUS_CANCELLED,
        ];

        foreach ($statuses as $status) {
            $this->assertArrayHasKey($status, BranchTransfer::statusLabels());
            $this->assertNotSame('', BranchTransfer::statusLabel($status));
        }
    }

    public function test_status_options_have_unique_values(): void
    {
        $options = BranchTransfer::statusOptions();
        $values = array_column($options, 'value');

        $this->assertSameSize(BranchTransfer::statusLabels(), $options);
        $this->assertSame(array_values(array_unique($values)), $values);

        foreach ($options as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
            $this->assertNotSame('', $option['label']);
        }
    }

    public function test_status_label_returns_safe_fallback_for_unknown_status(): void
    {
        $this->assertSame('unknown_state', BranchTransfer::statusLabel('unknown_state'));
    }
}

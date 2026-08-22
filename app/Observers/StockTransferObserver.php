<?php

namespace App\Observers;

use App\Accounting\PostingEngine;
use App\Models\StockTransfer;

class StockTransferObserver
{
    public function __construct(
        private readonly PostingEngine $ledger,
    ) {
    }

    /**
     * A completed transfer moves inventory value between two locations.
     *
     * Both "created already completed" and "updated to completed" are handled:
     * GrnService creates its inbound transfer in a completed state, so the
     * updated hook alone never saw it. That was harmless only by accident -
     * StockTransferPostingRule now declines a vendor-sourced transfer on
     * purpose, because the goods receipt accounts for it.
     */
    public function created(StockTransfer $transfer): void
    {
        $this->postIfComplete($transfer);
    }

    public function updated(StockTransfer $transfer): void
    {
        if (! $transfer->wasChanged('status')) {
            return;
        }

        $this->postIfComplete($transfer);
    }

    private function postIfComplete(StockTransfer $transfer): void
    {
        if ($transfer->status !== 'completed') {
            return;
        }

        $this->ledger->postFor($transfer);
    }
}

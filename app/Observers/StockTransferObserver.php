<?php

namespace App\Observers;

use App\Models\StockTransfer;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use App\Support\InventoryLocationAccount;
use Illuminate\Support\Facades\App;

class StockTransferObserver
{
    public function updated(StockTransfer $transfer): void
    {
        if (! $transfer->wasChanged('status') || $transfer->status !== 'completed') {
            return;
        }

        // Idempotency check
        $exists = JournalEntry::where('source_type', $transfer->getMorphClass())
            ->where('source_id', $transfer->getKey())
            ->exists();
        if ($exists) {
            return;
        }

        // Value the goods at what they cost on the day they moved, not at
        // whatever the product costs now. Reading the live figure meant a later
        // delivery silently restated a transfer already posted to the ledger,
        // so the two halves of an out-and-back no longer cancelled.
        $transfer->loadMissing('items.product');
        $costs = app(\App\Services\Pricing\ProductCostService::class);
        $movedAt = $transfer->transfer_date
            ? \Illuminate\Support\Carbon::parse($transfer->transfer_date)
            : now();

        $totalCost = $transfer->items->sum(function ($item) use ($costs, $movedAt) {
            $unitCost = $item->product
                ? $costs->costAtOrLegacy($item->product, $movedAt)
                : 0;

            return $unitCost * $item->quantity;
        });
        $totalCost = round($totalCost, 2);

        if ($totalCost <= 0) {
            return; // Nothing meaningful to post
        }

        // -----------------------------------------------------------------
        // Post General-Ledger entry – move inventory value between locations
        // -----------------------------------------------------------------
        // The 1200-<PREFIX><ID> sub-account convention lives in
        // InventoryLocationAccount, because the retailer-return journal has to
        // resolve the very same codes to move this value back again.
        //
        // If either side resolves to the SAME account code we skip posting as
        // there is no GL impact (rare but possible e.g. incorrect data).

        // from/to_location_type stores the model FQCN (e.g. App\Models\Warehouse).
        $fromCode = InventoryLocationAccount::codeFor($transfer->from_location_type, $transfer->from_location_id);
        $toCode   = InventoryLocationAccount::codeFor($transfer->to_location_type,   $transfer->to_location_id);

        // If both accounts are identical, no GL movement is required.
        if ($fromCode === $toCode) {
            return;
        }

        // Ensure both sub-accounts exist before posting.
        InventoryLocationAccount::ensure($transfer->from_location_type, $transfer->from_location_id);
        InventoryLocationAccount::ensure($transfer->to_location_type,   $transfer->to_location_id);

        /** @var AccountingService $acct */
        $acct = App::make(AccountingService::class);
        $acct->post([
            [
                'account_code' => $fromCode,
                'debit'        => 0,
                'credit'       => $totalCost,
                'description'  => 'Transfer out – XFR #'.$transfer->id,
            ],
            [
                'account_code' => $toCode,
                'debit'        => $totalCost,
                'credit'       => 0,
                'description'  => 'Transfer in – XFR #'.$transfer->id,
            ],
        ], $transfer->transfer_date ?? now(), 'Stock Transfer #'.$transfer->id, $transfer);
    }
} 
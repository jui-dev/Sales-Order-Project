<?php

namespace App\Observers;

use App\Models\StockTransfer;
use App\Models\JournalEntry;
use App\Services\AccountingService;
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

        // Determine total inventory value transferred based on product purchase prices
        $transfer->loadMissing('items.product');
        $totalCost = $transfer->items->sum(function ($item) {
            $unitCost = $item->product->purchase_price ?? 0;
            return $unitCost * $item->quantity;
        });
        $totalCost = round($totalCost, 2);

        if ($totalCost <= 0) {
            return; // Nothing meaningful to post
        }

        // -----------------------------------------------------------------
        // Post General-Ledger entry – move inventory value between locations
        // -----------------------------------------------------------------
        // We maintain sub-accounts under the main Inventory account (1200) so
        // that each warehouse / retailer carries its own balance. The account
        // code pattern is:  1200-<PREFIX><ID>  e.g. 1200-WH1, 1200-RT3
        //
        // • Warehouse  → prefix WH
        // • Retailer   → prefix RT
        // • Fallback   → prefix LO (generic “Location”)
        //
        // If either side resolves to the SAME account code we skip posting as
        // there is no GL impact (rare but possible e.g. incorrect data).

        /** @var \App\Models\AccountType $assetType */
        $assetType = \App\Models\AccountType::firstOrCreate(['name' => 'Asset']);

        /** @var \App\Models\Account|null $inventoryParent */
        $inventoryParent = \App\Models\Account::where('code', '1200')->first();

        // from/to_location_type stores the model FQCN (e.g. App\Models\Warehouse),
        // so match on the class names rather than lowercase labels.
        $buildCode = function (?string $type, ?int $id): string {
            $prefix = match ($type) {
                \App\Models\Warehouse::class => 'WH',
                \App\Models\Retailer::class  => 'RT',
                default                      => 'LO',
            };
            return '1200-' . $prefix . (string) $id;
        };

        $buildName = function (?string $type, ?int $id): string {
            return match ($type) {
                \App\Models\Warehouse::class => "Inventory – Warehouse #{$id}",
                \App\Models\Retailer::class  => "Inventory – Retailer #{$id}",
                default                      => "Inventory – Location #{$id}",
            };
        };

        $fromCode = $buildCode($transfer->from_location_type, $transfer->from_location_id);
        $toCode   = $buildCode($transfer->to_location_type,   $transfer->to_location_id);

        // If both accounts are identical, no GL movement is required.
        if ($fromCode === $toCode) {
            return;
        }

        // Ensure both sub-accounts exist before posting.
        foreach ([[$fromCode, $transfer->from_location_type, $transfer->from_location_id], [$toCode, $transfer->to_location_type, $transfer->to_location_id]] as [$code, $type, $id]) {
            \App\Models\Account::firstOrCreate(
                ['code' => $code],
                [
                    'name'             => $buildName($type, $id),
                    'account_type_id'  => $assetType->id,
                    'parent_id'        => $inventoryParent?->id,
                    'opening_balance'  => 0,
                    'is_contra'        => false,
                ]
            );
        }

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
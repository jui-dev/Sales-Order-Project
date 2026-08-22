<?php

namespace App\Accounting\Posting;

use App\Accounting\AccountRole;
use App\Accounting\JournalDraft;
use App\Accounting\Money;
use App\Models\Retailer;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\Pricing\ProductCostService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Stock has moved between two places the business owns.
 *
 *     Cr 1200 Inventory  (location it left)
 *     Dr 1200 Inventory  (location it arrived at)
 *
 * One account, two locations. This used to post between per-location
 * sub-accounts - 1200-WH1, 1200-RT2 - which were the only accounts transfers
 * ever touched: purchases debited the parent and sales credited the parent, so
 * a location sub-account went credit-negative on its first outbound move and
 * per-location inventory value meant nothing. Location is analysis, not
 * identity, so it belongs on the line.
 *
 * A transfer whose source is a vendor is the inbound leg that GrnService
 * creates for traceability; the goods receipt rule books that, and booking it
 * here as well would count the same delivery twice.
 */
class StockTransferPostingRule implements PostingRule
{
    /** Places the business actually holds stock. */
    private const OWNED = [Warehouse::class, Retailer::class];

    public function __construct(
        private readonly ProductCostService $costs,
    ) {
    }

    public function key(): string
    {
        return 'stock_transfer.movement';
    }

    public function documentType(): string
    {
        return StockTransfer::class;
    }

    public function appliesTo(Model $document): bool
    {
        /** @var StockTransfer $document */
        if ($document->status !== 'completed') {
            return false;
        }

        // Both ends must be places the business holds stock. A vendor at
        // either end means goods entering or leaving the business, which the
        // receipt and return rules account for.
        if (! in_array($document->from_location_type, self::OWNED, true)
            || ! in_array($document->to_location_type, self::OWNED, true)) {
            return false;
        }

        // A move to where it already is has no effect to record.
        return $document->from_location_type !== $document->to_location_type
            || (int) $document->from_location_id !== (int) $document->to_location_id;
    }

    public function build(Model $document): JournalDraft
    {
        /** @var StockTransfer $transfer */
        $transfer = $document;
        $transfer->loadMissing(['items.product', 'fromLocation', 'toLocation']);

        $draft = JournalDraft::for($transfer, $this->key())
            ->on($movedAt = Carbon::parse($transfer->transfer_date ?? now()))
            ->describedAs('Stock transfer #' . $transfer->id);

        $from = $transfer->fromLocation;
        $to = $transfer->toLocation;

        if (! $from || ! $to || $from->getKey() === null || $to->getKey() === null) {
            return $draft;
        }

        foreach ($transfer->items as $item) {
            if (! $item->product) {
                continue;
            }

            // Valued at what the goods cost on the day they moved, never at
            // what they cost now: reading the live figure let a later delivery
            // restate a transfer already on the ledger, so the two halves of
            // an out-and-back stopped cancelling.
            $unitCost = $this->costs->costAtOrLegacy($item->product, $movedAt);
            $value = Money::fromUnitCost($item->quantity, $unitCost);

            if ($value->isZero()) {
                continue;
            }

            $draft
                ->credit(
                    AccountRole::Inventory,
                    $value,
                    ['location' => $from, 'product' => $item->product_id],
                    'Transferred out - XFR #' . $transfer->id,
                )
                ->debit(
                    AccountRole::Inventory,
                    $value,
                    ['location' => $to, 'product' => $item->product_id],
                    'Transferred in - XFR #' . $transfer->id,
                );
        }

        return $draft;
    }
}

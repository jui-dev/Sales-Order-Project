<?php

namespace App\Accounting\Posting;

use App\Accounting\AccountRole;
use App\Accounting\JournalDraft;
use App\Accounting\Money;
use App\Models\Order;
use App\Models\PickingList;
use App\Services\Pricing\ProductCostService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Goods have left on a sale, so they stop being inventory and become cost.
 *
 *     Dr 5000 Cost of Goods Sold
 *         Cr 1200 Inventory     (per product, at the location they left from)
 *
 * Only sales do this. A stock transfer keeps the value inside the business and
 * is handled by StockTransferPostingRule, which moves it between locations on
 * the same account.
 *
 * Goods are relieved at the weighted average cost in force on the day they
 * shipped. That has to be the same basis inventory is carried at, or the
 * inventory account drifts from the stock behind it by design and can never be
 * reconciled - which is what happened while this read the unit cost the order
 * captured when it was placed. Pinning the cost to the shipment date still
 * means a later delivery cannot restate a sale already on the books, because
 * the costing ledger is historised.
 */
class CostOfGoodsSoldPostingRule implements PostingRule
{
    /** Statuses that mean the pick is finished and the goods have gone. */
    private const COMPLETED = ['completed', 'closed', 'verified'];

    public function __construct(
        private readonly ProductCostService $costs,
    ) {
    }

    public function key(): string
    {
        return 'picking_list.cogs';
    }

    public function documentType(): string
    {
        return PickingList::class;
    }

    public function appliesTo(Model $document): bool
    {
        /** @var PickingList $document */
        return in_array($document->status, self::COMPLETED, true)
            && $document->reference_type === Order::class;
    }

    public function build(Model $document): JournalDraft
    {
        /** @var PickingList $list */
        $list = $document;
        $list->loadMissing(['items.product', 'fromLocation']);

        $reference = $list->picking_number ?: ('PL-' . $list->id);
        $description = 'Cost of goods sold - Picking ' . $reference;

        $draft = JournalDraft::for($list, $this->key())
            ->on($shippedAt = Carbon::parse($list->completed_at ?? $list->picking_date ?? now()))
            ->describedAs($description);

        $location = $list->fromLocation;

        // Without a source location there is no inventory balance to relieve.
        // The stock movement is still the record of truth; this simply has
        // nothing it can say about it.
        if (! $location || $location->getKey() === null) {
            return $draft;
        }

        $total = Money::zero();

        foreach ($list->items as $item) {
            $quantity = (int) ($item->quantity_picked ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            if (! $item->product) {
                continue;
            }

            $value = Money::fromUnitCost(
                $quantity,
                $this->costs->costAtOrLegacy($item->product, $shippedAt),
            );

            if ($value->isZero()) {
                continue;
            }

            $draft->credit(
                AccountRole::Inventory,
                $value,
                ['location' => $location, 'product' => $item->product_id],
                sprintf('Shipped %s x %s', $quantity, $item->product?->name ?? ('#' . $item->product_id)),
            );

            $total = $total->plus($value);
        }

        return $draft->debit(AccountRole::CostOfGoodsSold, $total, [], $description);
    }
}

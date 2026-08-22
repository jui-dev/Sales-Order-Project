<?php

namespace App\Accounting\Posting;

use App\Accounting\AccountRole;
use App\Accounting\JournalDraft;
use App\Accounting\Money;
use App\Models\Grn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Goods have physically arrived, so the business owns them.
 *
 *     Dr 1200 Inventory                (per product, at the receiving location)
 *         Cr 2050 Goods Received Not Invoiced
 *
 * This entry did not exist before. Stock and the costing ledger moved when the
 * GRN was posted, but the ledger did not recognise the goods until somebody
 * posted the supplier bill - a later, manual, sometimes-never step. Between the
 * two, selling the goods credited inventory that had never been debited, so the
 * inventory account could go credit-negative and never reconciled to what was
 * on the shelves.
 *
 * GR-IR holds the other side until the vendor bills for it, at which point its
 * balance is exactly the value of goods received and not yet invoiced.
 */
class GoodsReceiptPostingRule implements PostingRule
{
    public function key(): string
    {
        return 'grn.goods_received';
    }

    public function documentType(): string
    {
        return Grn::class;
    }

    public function appliesTo(Model $document): bool
    {
        return $document->status === 'posted';
    }

    public function build(Model $document): JournalDraft
    {
        /** @var Grn $grn */
        $grn = $document;
        $grn->loadMissing(['supply.items.product', 'supply.warehouse']);

        $supply = $grn->supply;
        $warehouse = $supply?->warehouse;

        $draft = JournalDraft::for($grn, $this->key())
            ->on($this->receivedAt($grn))
            ->describedAs('Goods received - GRN ' . ($grn->formatted_id ?? $grn->id));

        // No warehouse means there is nowhere to carry the value, and the
        // location dimension on inventory is not optional.
        if (! $supply || ! $warehouse) {
            return $draft;
        }

        $total = Money::zero();

        foreach ($supply->items as $item) {
            $value = Money::fromUnitCost($item->quantity, $item->unit_cost);

            if ($value->isZero()) {
                continue;
            }

            $draft->debit(
                AccountRole::Inventory,
                $value,
                ['location' => $warehouse, 'product' => $item->product_id],
                sprintf('Received %s x %s', $item->quantity, $item->product?->name ?? ('#' . $item->product_id)),
            );

            $total = $total->plus($value);
        }

        return $draft->credit(
            AccountRole::GoodsReceivedNotInvoiced,
            $total,
            [],
            'Awaiting vendor bill - GRN ' . ($grn->formatted_id ?? $grn->id),
        );
    }

    private function receivedAt(Grn $grn): Carbon
    {
        return Carbon::parse($grn->received_date ?? $grn->supply?->supply_date ?? now());
    }
}

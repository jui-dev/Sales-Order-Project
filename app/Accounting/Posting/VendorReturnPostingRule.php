<?php

namespace App\Accounting\Posting;

use App\Accounting\AccountRole;
use App\Accounting\JournalDraft;
use App\Accounting\Money;
use App\Models\DebitNote;
use App\Services\Pricing\ProductCostService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Goods have gone back to the vendor.
 *
 *     Dr 2000 Accounts Payable    total     (against the vendor)
 *         Cr 1200 Inventory           at cost, from the location they left
 *          ± 5100 Purchase Returns    the difference, if any
 *
 * The mirror of the purchase, and nothing else. In particular it never touches
 * Cost of Goods Sold: stock returned to a vendor was never sold.
 *
 * Anything credited by the vendor beyond what the goods cost - or short of it -
 * is a price adjustment rather than a stock movement, so it goes to Purchase
 * Returns and keeps the entry balanced.
 */
class VendorReturnPostingRule implements PostingRule
{
    public function __construct(
        private readonly ProductCostService $costs,
    ) {
    }

    public function key(): string
    {
        return 'debit_note.vendor_return';
    }

    public function documentType(): string
    {
        return DebitNote::class;
    }

    public function appliesTo(Model $document): bool
    {
        /** @var DebitNote $document */
        return in_array($document->status, [DebitNote::STATUS_POSTED, 'issued'], true)
            && Money::of((string) $document->total_amount)->isPositive();
    }

    public function build(Model $document): JournalDraft
    {
        /** @var DebitNote $note */
        $note = $document;
        $note->loadMissing([
            'vendor',
            'items.product',
            'returnTransaction.location',
            'supplierBill.grn.supply.warehouse',
        ]);

        $reference = $note->debit_note_number ?? $note->formatted_id;
        $description = 'Vendor return - Debit Note ' . $reference;

        $total = Money::of((string) $note->total_amount);
        $raisedAt = Carbon::parse($note->issue_date ?? $note->created_at ?? now());

        $draft = JournalDraft::for($note, $this->key())
            ->on($raisedAt)
            ->describedAs($description)
            ->debit(
                AccountRole::AccountsPayable,
                $total,
                ['party' => $note->vendor],
                'Owed to vendor reduced - ' . $reference,
            );

        $location = $this->locationFor($note);
        $cost = Money::zero();

        if ($location) {
            foreach ($note->items as $item) {
                if (! $item->product) {
                    continue;
                }

                $value = Money::fromUnitCost(
                    $item->quantity,
                    $this->costs->costAtOrLegacy($item->product, $raisedAt),
                );

                if ($value->isZero()) {
                    continue;
                }

                $draft->credit(
                    AccountRole::Inventory,
                    $value,
                    ['location' => $location, 'product' => $item->product_id],
                    'Returned to vendor - ' . $reference,
                );

                $cost = $cost->plus($value);
            }
        }

        // Positive when the vendor credited more than the goods cost, negative
        // when less. Passing the signed amount lets one call cover both, and
        // an exact match posts nothing at all.
        return $draft->add(
            AccountRole::PurchaseReturns,
            $cost->minus($total),
            [],
            'Price difference on return - ' . $reference,
        );
    }

    /**
     * Where the goods left from.
     *
     * The return transaction knows directly; failing that, the warehouse the
     * original delivery was received into is the only place they can have been.
     */
    private function locationFor(DebitNote $note): ?Model
    {
        $location = $note->returnTransaction?->location
            ?? $note->supplierBill?->grn?->supply?->warehouse;

        return $location && $location->getKey() !== null ? $location : null;
    }
}

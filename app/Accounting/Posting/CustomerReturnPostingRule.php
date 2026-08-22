<?php

namespace App\Accounting\Posting;

use App\Accounting\AccountRole;
use App\Accounting\JournalDraft;
use App\Accounting\Money;
use App\Models\CreditNote;
use App\Services\Pricing\ProductCostService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A customer has sent goods back.
 *
 *     Dr 4200 Sales Returns & Allowances   subtotal   (per product, at the location it comes back to)
 *     Dr 2100 Sales Tax Payable            tax        (no longer owed)
 *         Cr 4100 Sales Discounts              discount   (no longer granted)
 *         Cr 1100 Accounts Receivable          total      (against the customer)
 *
 *     Dr 1200 Inventory   at cost, back at the location it came to
 *         Cr 5000 Cost of Goods Sold
 *
 * Revenue is reduced through a contra account rather than by debiting Sales
 * Revenue directly, so gross sales and returns both stay visible instead of
 * one silently eroding the other.
 *
 * The returns carry the same product and location dimensions the revenue they
 * cancel does, so profit per product is net of what came back without anything
 * having to match credit notes to invoices after the fact.
 *
 * The goods are valued at what they cost when they were sold, not at what they
 * cost today: reading the live figure meant a delivery arriving between the
 * sale and the return changed the inventory this puts back, so it no longer
 * matched the cost that had been relieved.
 */
class CustomerReturnPostingRule implements PostingRule
{
    public function __construct(
        private readonly ProductCostService $costs,
    ) {
    }

    public function key(): string
    {
        return 'credit_note.customer_return';
    }

    public function documentType(): string
    {
        return CreditNote::class;
    }

    public function appliesTo(Model $document): bool
    {
        /** @var CreditNote $document */
        return in_array($document->status, [CreditNote::STATUS_POSTED, 'issued'], true)
            && Money::of((string) $document->total_amount)->isPositive();
    }

    public function build(Model $document): JournalDraft
    {
        /** @var CreditNote $note */
        $note = $document;
        $note->loadMissing(['customer', 'items.product', 'returnTransaction.location']);

        $reference = $note->credit_note_number ?? $note->formatted_id;
        $description = 'Customer return - Credit Note ' . $reference;

        $subtotal = Money::of((string) $note->subtotal);
        $tax      = Money::of((string) $note->tax_amount);
        $discount = Money::of((string) $note->discount_amount);
        $total    = Money::of((string) $note->total_amount);

        // Where a note carries no line breakdown, the whole credit is a
        // reduction of revenue.
        if ($subtotal->isZero() && $tax->isZero() && $discount->isZero()) {
            $subtotal = $total;
        }

        // One lookup for both halves of the entry: the goods come back to the
        // same place the revenue is being taken off.
        $location = $this->locationFor($note);

        $draft = JournalDraft::for($note, $this->key())
            ->on($returnedAt = Carbon::parse($note->issue_date ?? $note->created_at ?? now()))
            ->describedAs($description);

        $this->addReturnedRevenue($draft, $note, $subtotal, $location, $reference);

        $draft
            ->debit(AccountRole::SalesTaxPayable, $tax, [], 'Tax no longer collected - ' . $reference)
            ->credit(AccountRole::SalesDiscount, $discount, [], 'Discount reversed - ' . $reference)
            ->credit(
                AccountRole::AccountsReceivable,
                $total,
                ['party' => $note->customer],
                'Customer owes less - ' . $reference,
            );

        return $this->addInventoryReturn($draft, $note, $returnedAt, $location, $reference);
    }

    /**
     * Debit the contra-revenue account one line per product returned.
     *
     * Split the same way the invoice splits revenue, and made to sum to the
     * note's subtotal exactly by giving the last line the remainder - a penny
     * adrift between the lines and the header would unbalance the entry.
     */
    private function addReturnedRevenue(
        JournalDraft $draft,
        CreditNote $note,
        Money $subtotal,
        ?Model $location,
        string $reference,
    ): void {
        $items = $note->items;

        if ($items->isEmpty() || $subtotal->isZero()) {
            $draft->debit(AccountRole::SalesReturns, $subtotal, [], 'Revenue reversed - ' . $reference);

            return;
        }

        $remaining = $subtotal;
        $last = $items->count() - 1;

        foreach ($items->values() as $index => $item) {
            $amount = $index === $last
                ? $remaining
                : Money::of((string) $item->subtotal);

            $remaining = $remaining->minus($amount);

            $draft->debit(
                AccountRole::SalesReturns,
                $amount,
                array_filter([
                    'product'  => $item->product_id,
                    'location' => $location,
                ]),
                sprintf('Returned %s x %s', $item->quantity, $item->product_name ?? ('#' . $item->product_id)),
            );
        }
    }

    /**
     * Where the goods came back to.
     *
     * The return transaction is the only record of it - a credit note on its
     * own says what is owed, not where the stock went.
     */
    private function locationFor(CreditNote $note): ?Model
    {
        $location = $note->returnTransaction?->location;

        return $location && $location->getKey() !== null ? $location : null;
    }

    /**
     * Put the goods back into stock at the cost they left at.
     *
     * Both halves are skipped together when the location or the cost is
     * unknown, which keeps the entry balanced: an unknown cost is not a cost
     * of zero, and inventory that cannot be placed anywhere cannot be booked.
     */
    private function addInventoryReturn(
        JournalDraft $draft,
        CreditNote $note,
        Carbon $returnedAt,
        ?Model $location,
        string $reference,
    ): JournalDraft {
        if (! $location) {
            return $draft;
        }

        foreach ($note->items as $item) {
            if (! $item->product) {
                continue;
            }

            $value = Money::fromUnitCost(
                $item->quantity,
                $this->costs->costAtOrLegacy($item->product, $returnedAt),
            );

            if ($value->isZero()) {
                continue;
            }

            $dimensions = ['location' => $location, 'product' => $item->product_id];

            $draft
                ->debit(
                    AccountRole::Inventory,
                    $value,
                    $dimensions,
                    'Returned to stock - ' . $reference,
                )
                // The cost comes off the same product the revenue did. Relieving
                // it in one lump would leave a product showing its revenue net of
                // the return but its cost gross of it, which reads as a loss.
                ->credit(
                    AccountRole::CostOfGoodsSold,
                    $value,
                    $dimensions,
                    'Cost reversed - ' . $reference,
                );
        }

        return $draft;
    }
}

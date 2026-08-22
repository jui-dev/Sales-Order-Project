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
 *     Dr 4200 Sales Returns & Allowances   subtotal
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

        $draft = JournalDraft::for($note, $this->key())
            ->on($returnedAt = Carbon::parse($note->issue_date ?? $note->created_at ?? now()))
            ->describedAs($description)
            ->debit(AccountRole::SalesReturns, $subtotal, [], 'Revenue reversed - ' . $reference)
            ->debit(AccountRole::SalesTaxPayable, $tax, [], 'Tax no longer collected - ' . $reference)
            ->credit(AccountRole::SalesDiscount, $discount, [], 'Discount reversed - ' . $reference)
            ->credit(
                AccountRole::AccountsReceivable,
                $total,
                ['party' => $note->customer],
                'Customer owes less - ' . $reference,
            );

        return $this->addInventoryReturn($draft, $note, $returnedAt, $reference);
    }

    /**
     * Put the goods back into stock at the cost they left at.
     *
     * Both halves are skipped together when the location or the cost is
     * unknown, which keeps the entry balanced: an unknown cost is not a cost
     * of zero, and inventory that cannot be placed anywhere cannot be booked.
     */
    private function addInventoryReturn(JournalDraft $draft, CreditNote $note, Carbon $returnedAt, string $reference): JournalDraft
    {
        $location = $note->returnTransaction?->location;

        if (! $location || $location->getKey() === null) {
            return $draft;
        }

        $total = Money::zero();

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

            $draft->debit(
                AccountRole::Inventory,
                $value,
                ['location' => $location, 'product' => $item->product_id],
                'Returned to stock - ' . $reference,
            );

            $total = $total->plus($value);
        }

        return $draft->credit(AccountRole::CostOfGoodsSold, $total, [], 'Cost reversed - ' . $reference);
    }
}

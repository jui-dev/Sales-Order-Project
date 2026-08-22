<?php

namespace App\Accounting\Posting;

use App\Accounting\AccountRole;
use App\Accounting\JournalDraft;
use App\Accounting\Money;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * A sale has been invoiced.
 *
 *     Dr 1100 Accounts Receivable   invoice total   (against the customer)
 *     Dr 4100 Sales Discounts       discount
 *         Cr 4000 Sales Revenue         subtotal   (per product, at the location it sells from)
 *         Cr 2100 Sales Tax Payable     tax
 *
 * The old entry credited the whole invoice total to revenue. Tax collected
 * from a customer is money held on behalf of the tax authority - a liability -
 * so revenue was overstated by exactly the tax charged, and there was no
 * balance to remit from. A discount is a reduction of revenue rather than a
 * cost of doing business, so it goes to a contra-revenue account where it
 * stays visible instead of quietly netting away.
 *
 * Revenue is split across the invoice lines and carries the product and the
 * location it was sold from, the same way the cost side already does. Revenue
 * stays one account - what a product earned is a GROUP BY over its lines, so
 * the parts necessarily add up to the whole. Without those dimensions profit
 * per product could only be rebuilt from the orders and hoped to agree with
 * the ledger, which is exactly how a returned order went on reporting a profit.
 */
class InvoicePostingRule implements PostingRule
{
    public function key(): string
    {
        return 'invoice.sale';
    }

    public function documentType(): string
    {
        return Invoice::class;
    }

    public function appliesTo(Model $document): bool
    {
        return Money::of((string) $document->total)->isPositive();
    }

    public function build(Model $document): JournalDraft
    {
        /** @var Invoice $invoice */
        $invoice = $document;
        $invoice->loadMissing(['customer', 'items', 'order.items']);

        $reference = $invoice->invoice_number ?? ('INV-' . $invoice->id);
        $description = 'Sale - Invoice ' . $reference;

        $subtotal = Money::of((string) $invoice->subtotal);
        $tax      = Money::of((string) $invoice->tax);
        $discount = Money::of((string) $invoice->discount);
        $total    = Money::of((string) $invoice->total);

        $this->assertTotalsAgree($subtotal, $tax, $discount, $total, $reference);

        $draft = JournalDraft::for($invoice, $this->key())
            ->on(Carbon::parse($invoice->invoice_date ?? now()))
            ->describedAs($description)
            ->debit(
                AccountRole::AccountsReceivable,
                $total,
                ['party' => $invoice->customer],
                'Owed by customer - Invoice ' . $reference,
            )
            ->debit(
                AccountRole::SalesDiscount,
                $discount,
                [],
                'Discount granted - Invoice ' . $reference,
            );

        $this->addRevenue($draft, $invoice, $subtotal, $reference);

        return $draft->credit(
            AccountRole::SalesTaxPayable,
            $tax,
            [],
            'Tax collected - Invoice ' . $reference,
        );
    }

    /**
     * Credit revenue one line per product sold.
     *
     * The lines are made to sum to the invoice subtotal exactly by giving the
     * last one whatever is left over, rather than by rounding each in turn: a
     * penny lost between the lines and the header would unbalance the entry,
     * and an invoice is not the place to discover that. An invoice with no
     * line breakdown still posts its subtotal as one undimensioned credit -
     * unattributed revenue is better than revenue that is not on the books.
     */
    private function addRevenue(JournalDraft $draft, Invoice $invoice, Money $subtotal, string $reference): void
    {
        $items = $invoice->items;

        if ($items->isEmpty() || $subtotal->isZero()) {
            $draft->credit(AccountRole::SalesRevenue, $subtotal, [], 'Goods sold - Invoice ' . $reference);

            return;
        }

        $remaining = $subtotal;
        $last = $items->count() - 1;

        foreach ($items->values() as $index => $item) {
            $amount = $index === $last
                ? $remaining
                : Money::of((string) $item->total);

            $remaining = $remaining->minus($amount);

            $draft->credit(
                AccountRole::SalesRevenue,
                $amount,
                array_filter([
                    'product'  => $item->product_id,
                    'location' => $this->locationFor($invoice, $item->product_id),
                ]),
                sprintf('Sold %s x %s', $item->quantity, $item->description ?? ('#' . $item->product_id)),
            );
        }
    }

    /**
     * Where this product was sold from.
     *
     * An invoice line carries no location of its own, so it is read off the
     * order line it came from - which is what the profit report has always
     * split warehouse from retailer by. Where one order sells the same product
     * from two locations the first line wins; the alternative is leaving the
     * revenue unattributed, which loses more than it protects.
     *
     * The order's own fulfilment location is the fallback, reached through the
     * relation rather than the property: the accessor at Order line 66 is a
     * withDefault() that hands back an unsaved model, and a dimension has to
     * point at a row that exists.
     */
    private function locationFor(Invoice $invoice, ?int $productId): ?Model
    {
        $order = $invoice->order;

        if (! $order || $productId === null) {
            return $order?->fulfillmentLocation()->first();
        }

        $line = $order->items->firstWhere('product_id', $productId);

        return $line?->location()->first()
            ?? $order->fulfillmentLocation()->first();
    }

    /**
     * Refuse to post an invoice whose own figures do not add up.
     *
     * An entry built from inconsistent totals still balances - the receivable
     * is taken from the total either way - so the error would be invisible on
     * the trial balance and would show up only as revenue that never agreed
     * with the sales ledger.
     */
    private function assertTotalsAgree(Money $subtotal, Money $tax, Money $discount, Money $total, string $reference): void
    {
        $expected = $subtotal->plus($tax)->minus($discount);

        if (! $expected->equals($total)) {
            throw new RuntimeException(sprintf(
                'Invoice %s does not add up: subtotal %s + tax %s - discount %s is %s, but the total says %s.',
                $reference,
                $subtotal->toDecimal(),
                $tax->toDecimal(),
                $discount->toDecimal(),
                $expected->toDecimal(),
                $total->toDecimal(),
            ));
        }
    }
}

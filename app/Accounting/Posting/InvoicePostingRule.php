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
 *         Cr 4000 Sales Revenue         subtotal
 *         Cr 2100 Sales Tax Payable     tax
 *
 * The old entry credited the whole invoice total to revenue. Tax collected
 * from a customer is money held on behalf of the tax authority - a liability -
 * so revenue was overstated by exactly the tax charged, and there was no
 * balance to remit from. A discount is a reduction of revenue rather than a
 * cost of doing business, so it goes to a contra-revenue account where it
 * stays visible instead of quietly netting away.
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
        $invoice->loadMissing('customer');

        $reference = $invoice->invoice_number ?? ('INV-' . $invoice->id);
        $description = 'Sale - Invoice ' . $reference;

        $subtotal = Money::of((string) $invoice->subtotal);
        $tax      = Money::of((string) $invoice->tax);
        $discount = Money::of((string) $invoice->discount);
        $total    = Money::of((string) $invoice->total);

        $this->assertTotalsAgree($subtotal, $tax, $discount, $total, $reference);

        return JournalDraft::for($invoice, $this->key())
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
            )
            ->credit(
                AccountRole::SalesRevenue,
                $subtotal,
                [],
                'Goods sold - Invoice ' . $reference,
            )
            ->credit(
                AccountRole::SalesTaxPayable,
                $tax,
                [],
                'Tax collected - Invoice ' . $reference,
            );
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

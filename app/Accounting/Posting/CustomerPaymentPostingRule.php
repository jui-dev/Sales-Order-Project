<?php

namespace App\Accounting\Posting;

use App\Accounting\AccountRole;
use App\Accounting\JournalDraft;
use App\Accounting\Money;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A customer has paid.
 *
 *     Dr 1000 Cash
 *         Cr 1100 Accounts Receivable   (against the customer)
 *
 * The receivable carries the customer, so what each one still owes is a
 * grouping of the control account rather than a figure that has to be
 * recomputed from the invoices and hoped to agree.
 */
class CustomerPaymentPostingRule implements PostingRule
{
    public function key(): string
    {
        return 'payment.receipt';
    }

    public function documentType(): string
    {
        return Payment::class;
    }

    public function appliesTo(Model $document): bool
    {
        /** @var Payment $document */
        return $document->invoice_id !== null
            && Money::of((string) $document->amount)->isPositive();
    }

    public function build(Model $document): JournalDraft
    {
        /** @var Payment $payment */
        $payment = $document;
        $payment->loadMissing('invoice.customer');

        $invoice = $payment->invoice;
        $reference = $invoice?->invoice_number ?? ('INV-' . $payment->invoice_id);
        $description = 'Payment received - Invoice ' . $reference;

        $amount = Money::of((string) $payment->amount);

        return JournalDraft::for($payment, $this->key())
            ->on(Carbon::parse($payment->payment_date ?? now()))
            ->describedAs($description)
            ->debit(
                AccountRole::Cash,
                $amount,
                [],
                'Cash received - Invoice ' . $reference,
            )
            ->credit(
                AccountRole::AccountsReceivable,
                $amount,
                ['party' => $invoice?->customer],
                'Customer owes less - Invoice ' . $reference,
            );
    }
}

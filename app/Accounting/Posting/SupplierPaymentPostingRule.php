<?php

namespace App\Accounting\Posting;

use App\Accounting\AccountRole;
use App\Accounting\JournalDraft;
use App\Accounting\Money;
use App\Models\SupplierBill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The vendor has been paid.
 *
 *     Dr 2000 Accounts Payable   (against the vendor)
 *         Cr 1000 Cash
 *
 * A second rule against the same document as SupplierBillPostingRule, which is
 * why entries are keyed by rule as well as by source: one supplier bill raises
 * one entry when it is posted and another when it is paid, and re-running
 * either must not duplicate the other.
 */
class SupplierPaymentPostingRule implements PostingRule
{
    public function key(): string
    {
        return 'supplier_bill.payment';
    }

    public function documentType(): string
    {
        return SupplierBill::class;
    }

    public function appliesTo(Model $document): bool
    {
        /** @var SupplierBill $document */
        return $document->paid_at !== null || $document->isPaid();
    }

    public function build(Model $document): JournalDraft
    {
        /** @var SupplierBill $bill */
        $bill = $document;
        $bill->loadMissing(['vendor', 'payment']);

        $reference = $bill->formatted_id ?? ('SB-' . $bill->id);

        $draft = JournalDraft::for($bill, $this->key())
            ->on(Carbon::parse($bill->paid_at ?? $bill->payment?->paid_at ?? now()))
            ->describedAs('Payment - Supplier Bill ' . $reference);

        // What was actually paid, which is not necessarily the whole bill once
        // partial settlement exists.
        $paid = Money::of((string) ($bill->payment?->payment_amount ?? $bill->total_amount));

        if ($paid->isZero()) {
            return $draft;
        }

        return $draft
            ->debit(
                AccountRole::AccountsPayable,
                $paid,
                ['party' => $bill->vendor],
                'Settled with vendor - Supplier Bill ' . $reference,
            )
            ->credit(
                AccountRole::Cash,
                $paid,
                [],
                'Cash paid - Supplier Bill ' . $reference,
            );
    }
}

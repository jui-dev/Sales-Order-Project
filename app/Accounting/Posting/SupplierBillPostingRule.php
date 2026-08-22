<?php

namespace App\Accounting\Posting;

use App\Accounting\AccountRole;
use App\Accounting\JournalDraft;
use App\Accounting\Money;
use App\Models\SupplierBill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The vendor has billed for goods already received.
 *
 *     Dr 2050 Goods Received Not Invoiced
 *         Cr 2000 Accounts Payable      (against the vendor)
 *
 * The debit clears what the goods receipt parked in GR-IR; it does not touch
 * inventory, because the goods were taken into inventory when they arrived.
 * Booking inventory here - as the old code did - meant the ledger recognised
 * stock at the moment an office task happened rather than at the moment goods
 * turned up, and left GR-IR with nothing to reconcile.
 *
 * The payable carries the vendor, so Accounts Payable can be reconciled
 * against the vendor subsidiary ledger and a vendor statement can be produced
 * from the ledger itself.
 */
class SupplierBillPostingRule implements PostingRule
{
    public function key(): string
    {
        return 'supplier_bill.purchase';
    }

    public function documentType(): string
    {
        return SupplierBill::class;
    }

    public function appliesTo(Model $document): bool
    {
        return in_array($document->status, ['posted', 'paid'], true);
    }

    public function build(Model $document): JournalDraft
    {
        /** @var SupplierBill $bill */
        $bill = $document;
        $bill->loadMissing('vendor');

        $reference = $bill->formatted_id ?? ('SB-' . $bill->id);

        $draft = JournalDraft::for($bill, $this->key())
            ->on(Carbon::parse($bill->bill_date ?? $bill->posted_at ?? now()))
            ->describedAs('Purchase - Supplier Bill ' . $reference);

        $net = Money::of((string) $bill->total_amount);

        // Recoverable input tax, once supplier bills carry a tax figure. The
        // column does not exist yet, so this is inert rather than speculative
        // structure: the moment a tax amount lands on the bill it books to the
        // asset it belongs in instead of inflating the cost of the goods.
        $tax = Money::of((string) ($bill->tax_amount ?? 0));

        // Nothing owed either way. Testing the net alone meant a bill that was
        // all tax and no goods posted nothing at all.
        if ($net->isZero() && $tax->isZero()) {
            return $draft;
        }

        $draft->debit(
            AccountRole::GoodsReceivedNotInvoiced,
            $net,
            [],
            'Clearing goods received - Supplier Bill ' . $reference,
        );

        if (! $tax->isZero()) {
            $draft->debit(
                AccountRole::InputTaxRecoverable,
                $tax,
                [],
                'Recoverable tax - Supplier Bill ' . $reference,
            );
        }

        return $draft->credit(
            AccountRole::AccountsPayable,
            $net->plus($tax),
            ['party' => $bill->vendor],
            'Owed to vendor - Supplier Bill ' . $reference,
        );
    }
}

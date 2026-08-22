<?php

namespace App\Services;

use App\Accounting\PostingEngine;
use App\Accounting\Posting\SupplierBillPostingRule;
use App\Accounting\Posting\SupplierPaymentPostingRule;
use App\Models\SupplierBill;
use App\Models\SupplierBillPayment;
use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SupplierBillService
{
    public function __construct(
        private readonly PostingEngine $ledger,
        private readonly SupplierBillPostingRule $purchaseRule,
        private readonly SupplierPaymentPostingRule $paymentRule,
    ) {
    }

    /**
     * Get filtered supplier bills with pagination
     */
    public function getFilteredSupplierBills(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = SupplierBill::with(['vendor', 'payment']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * Get supplier bill with all related data
     */
    public function getSupplierBillWithDetails(int $id): SupplierBill
    {
        return SupplierBill::with([
            'vendor',
            'items.product',
            // grn.supply backs the workflow rail's "Record Supply" stage
            'grn.supply',
            'purchaseJournal.lines.account',
            'paymentJournal.lines.account',
            'payment.paymentJournal'
        ])->findOrFail($id);
    }

    /**
     * Post (confirm) a draft supplier bill.
     *
     * The entry clears Goods Received Not Invoiced against Accounts Payable.
     * It does not touch inventory: the goods went into inventory when they
     * physically arrived, which is where the costing ledger records them too.
     */
    public function postSupplierBill(SupplierBill $supplierBill): SupplierBill
    {
        if ($supplierBill->status !== 'draft') {
            throw new \Exception('Only draft bills can be posted.');
        }

        if ($supplierBill->purchase_journal_id) {
            throw new \Exception('Supplier bill already posted.');
        }

        return DB::transaction(function () use ($supplierBill) {
            $supplierBill->update([
                'status'    => 'posted',
                'posted_at' => now(),
            ]);

            $entry = $this->ledger->runRule($this->purchaseRule, $supplierBill->refresh());

            if ($entry) {
                $supplierBill->updateQuietly(['purchase_journal_id' => $entry->id]);
            }

            // Create supplier bill payment record (only if one doesn't exist)
            if (! $supplierBill->payment) {
                SupplierBillPayment::create([
                    'formatted_id'      => 'SBP-' . str_pad((string) (SupplierBillPayment::count() + 1), 6, '0', STR_PAD_LEFT),
                    'supplier_bill_id'  => $supplierBill->id,
                    'vendor_id'         => $supplierBill->vendor_id,
                    'payment_amount'    => $supplierBill->total_amount,
                    'payment_status'    => 'unpaid',
                ]);
            }

            AuditLog::create([
                'user_id'      => auth()->id() ?? 1,
                'action'       => 'supplier_bill_posted',
                'description'  => 'Supplier Bill ' . $supplierBill->formatted_id . ' posted to the ledger.',
                'subject_type' => $supplierBill->getMorphClass(),
                'subject_id'   => $supplierBill->getKey(),
            ]);

            return $supplierBill->fresh();
        });
    }

    /**
     * Mark supplier bill as paid and post the payment entry.
     */
    public function paySupplierBill(SupplierBill $supplierBill): SupplierBill
    {
        if ($supplierBill->status !== 'posted') {
            throw new \Exception('Only posted bills can be marked as paid.');
        }

        if (! $supplierBill->payment) {
            throw new \Exception('No payment record found for this supplier bill.');
        }

        if ($supplierBill->payment->payment_status === 'paid') {
            throw new \Exception('Supplier bill already marked as paid.');
        }

        if ($supplierBill->payment_journal_id || $supplierBill->payment->payment_journal_id) {
            throw new \Exception('Payment journal entry already exists for this supplier bill.');
        }

        return DB::transaction(function () use ($supplierBill) {
            $paidAt = now();

            // Stamped before posting: the payment rule dates its entry by this,
            // and a payment recorded a few days late belongs in the period it
            // happened in, not the one it was keyed in.
            $supplierBill->update(['paid_at' => $paidAt]);
            $supplierBill->payment->update([
                'payment_status' => 'paid',
                'paid_at'        => $paidAt,
            ]);

            $entry = $this->ledger->runRule($this->paymentRule, $supplierBill->refresh());

            if ($entry) {
                $supplierBill->updateQuietly(['payment_journal_id' => $entry->id]);
                $supplierBill->payment->updateQuietly(['payment_journal_id' => $entry->id]);
            }

            AuditLog::create([
                'user_id'      => auth()->id() ?? 1,
                'action'       => 'supplier_bill_paid',
                'description'  => 'Supplier Bill ' . $supplierBill->formatted_id . ' paid and posted to the ledger.',
                'subject_type' => $supplierBill->getMorphClass(),
                'subject_id'   => $supplierBill->getKey(),
            ]);

            return $supplierBill->fresh();
        });
    }
}

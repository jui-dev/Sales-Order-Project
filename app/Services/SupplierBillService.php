<?php

namespace App\Services;

use App\Models\SupplierBill;
use App\Models\SupplierBillPayment;
use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierBillService
{
    public function __construct(private readonly AccountingService $accountingService)
    {
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
     * Post (confirm) a draft supplier bill
     */
    public function postSupplierBill(SupplierBill $supplierBill): SupplierBill
    {
        if ($supplierBill->status !== 'draft') {
            throw new \Exception('Only draft bills can be posted.');
        }

        if ($supplierBill->purchase_journal_id) {
            throw new \Exception('Supplier bill already posted.');
        }

        // Create journal entry: Inventory Dr / Accounts Payable Cr with Draft status
        $entry = $this->accountingService->post([
            [
                'account_code' => '1200', // Inventory
                'debit'        => $supplierBill->total_amount,
                'credit'       => 0,
                'description'  => 'Inventory – Supplier Bill ' . $supplierBill->formatted_id,
            ],
            [
                'account_code' => '2000', // Accounts Payable
                'debit'        => 0,
                'credit'       => $supplierBill->total_amount,
                'description'  => 'Liability to vendor – Supplier Bill ' . $supplierBill->formatted_id,
            ],
        ], now(), 'Purchase – Supplier Bill ' . $supplierBill->formatted_id, $supplierBill, 'draft');

        // Update bill status
        $supplierBill->update([
            'status'              => 'posted',
            'posted_at'           => now(),
            'purchase_journal_id' => $entry->id,
        ]);

        // Create supplier bill payment record (only if one doesn't exist)
        if (!$supplierBill->payment) {
            SupplierBillPayment::create([
                'formatted_id'      => 'SBP-' . str_pad((string) (SupplierBillPayment::count() + 1), 6, '0', STR_PAD_LEFT),
                'supplier_bill_id'  => $supplierBill->id,
                'vendor_id'         => $supplierBill->vendor_id,
                'payment_amount'    => $supplierBill->total_amount,
                'payment_status'    => 'unpaid',
            ]);
        }

        // Audit
        AuditLog::create([
            'user_id'      => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'action'       => 'supplier_bill_posted',
            'description'  => 'Supplier Bill ' . $supplierBill->formatted_id . ' posted with draft journal entry.',
            'subject_type' => $supplierBill->getMorphClass(),
            'subject_id'   => $supplierBill->getKey(),
        ]);

        return $supplierBill->fresh();
    }

    /**
     * Mark supplier bill as paid and create payment journal entry
     */
    public function paySupplierBill(SupplierBill $supplierBill): SupplierBill
    {
        if ($supplierBill->status !== 'posted') {
            throw new \Exception('Only posted bills can be marked as paid.');
        }

        if (!$supplierBill->payment) {
            throw new \Exception('No payment record found for this supplier bill.');
        }

        if ($supplierBill->payment->payment_status === 'paid') {
            throw new \Exception('Supplier bill already marked as paid.');
        }

        // Check if payment journal entry already exists (check both supplier bill and payment record)
        if ($supplierBill->payment_journal_id || $supplierBill->payment->payment_journal_id) {
            throw new \Exception('Payment journal entry already exists for this supplier bill.');
        }

        // Create payment journal entry: Accounts Payable Dr / Cash Cr with Draft status
        $entry = $this->accountingService->post([
            [
                'account_code' => '2000', // Accounts Payable
                'debit'        => $supplierBill->total_amount,
                'credit'       => 0,
                'description'  => 'Payment to vendor – Supplier Bill ' . $supplierBill->formatted_id,
            ],
            [
                'account_code' => '1000', // Cash
                'debit'        => 0,
                'credit'       => $supplierBill->total_amount,
                'description'  => 'Cash payment – Supplier Bill ' . $supplierBill->formatted_id,
            ],
        ], now(), 'Payment – Supplier Bill ' . $supplierBill->formatted_id, $supplierBill, 'draft');

        // Update bill paid_at timestamp and payment journal reference
        $supplierBill->update([
            'paid_at'              => now(),
            'payment_journal_id'   => $entry->id,
        ]);

        // Update payment record
        if ($supplierBill->payment) {
            $supplierBill->payment->update([
                'payment_status'    => 'paid',
                'paid_at'           => now(),
                'payment_journal_id' => $entry->id,
            ]);
        }

        // Audit
        AuditLog::create([
            'user_id'      => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'action'       => 'supplier_bill_paid',
            'description'  => 'Supplier Bill ' . $supplierBill->formatted_id . ' marked as paid with draft journal entry.',
            'subject_type' => $supplierBill->getMorphClass(),
            'subject_id'   => $supplierBill->getKey(),
        ]);

        return $supplierBill->fresh();
    }
} 
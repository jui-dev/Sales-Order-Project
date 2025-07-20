<?php

namespace App\Http\Controllers;

use App\Models\SupplierBill;
use App\Models\SupplierBillPayment;
use App\Models\AuditLog;
use App\Services\AccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierBillController extends Controller
{
    public function index(Request $request): View
    {
        $query = SupplierBill::with(['vendor', 'payment'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('id');

        $bills = $query->paginate(20)->withQueryString();

        return view('supplier-bills.index', compact('bills'));
    }

    public function show(SupplierBill $supplierBill): View
    {
        $supplierBill->load(['vendor', 'items.product', 'grn', 'purchaseJournal.lines.account', 'paymentJournal.lines.account', 'payment.paymentJournal']);
        return view('supplier-bills.show', compact('supplierBill'));
    }

    /**
     * Post (confirm) a draft supplier bill – creates purchase journal entry with Draft status.
     */
    public function post(SupplierBill $supplierBill, AccountingService $acct): RedirectResponse
    {
        if ($supplierBill->status !== 'draft') {
            return back()->with('error', 'Only draft bills can be posted.');
        }

        // Guard against duplicate journal
        if ($supplierBill->purchase_journal_id) {
            return back()->with('error', 'Supplier bill already posted.');
        }

        // Create journal entry: Inventory Dr / Accounts Payable Cr with Draft status
        $entry = $acct->post([
            [
                'account_code' => '1200', // Inventory
                'debit'        => $supplierBill->total_amount,
                'credit'       => 0,
                'description'  => 'Inventory – Supplier Bill '.$supplierBill->formatted_id,
            ],
            [
                'account_code' => '2000', // Accounts Payable
                'debit'        => 0,
                'credit'       => $supplierBill->total_amount,
                'description'  => 'Liability to vendor – Supplier Bill '.$supplierBill->formatted_id,
            ],
        ], now(), 'Purchase – Supplier Bill '.$supplierBill->formatted_id, $supplierBill, 'draft');

        // Update bill status
        $supplierBill->update([
            'status'              => 'posted',
            'posted_at'           => now(),
            'purchase_journal_id' => $entry->id,
        ]);

        // Create supplier bill payment record
        SupplierBillPayment::create([
            'formatted_id'      => 'SBP-' . str_pad((string) (SupplierBillPayment::count() + 1), 6, '0', STR_PAD_LEFT),
            'supplier_bill_id'  => $supplierBill->id,
            'vendor_id'         => $supplierBill->vendor_id,
            'payment_amount'    => $supplierBill->total_amount,
            'payment_status'    => 'unpaid',
        ]);

        // Audit
        AuditLog::create([
            'user_id'      => auth()->id(),
            'action'       => 'supplier_bill_posted',
            'description'  => 'Supplier Bill '.$supplierBill->formatted_id.' posted with draft journal entry.',
            'subject_type' => $supplierBill->getMorphClass(),
            'subject_id'   => $supplierBill->getKey(),
        ]);

        // Redirect to payment info page
        return redirect()->route('supplier-bills.payment-info', $supplierBill)
            ->with('success', 'Supplier Bill has been posted successfully. Purchase journal entry created with Draft status. You can now review payment information.');
    }

    /**
     * Mark supplier bill as paid and create payment journal entry with Draft status.
     */
    public function pay(SupplierBill $supplierBill, AccountingService $acct): RedirectResponse
    {
        if ($supplierBill->status !== 'posted') {
            return back()->with('error', 'Only posted bills can be marked as paid.');
        }

        if ($supplierBill->payment_journal_id) {
            return back()->with('error', 'Supplier bill already marked as paid.');
        }

        // Create payment journal: Accounts Payable Dr / Cash Cr with Draft status
        $entry = $acct->post([
            [
                'account_code' => '2000', // Accounts Payable
                'debit'        => $supplierBill->total_amount,
                'credit'       => 0,
                'description'  => 'Payment – Supplier Bill '.$supplierBill->formatted_id,
            ],
            [
                'account_code' => '1000', // Cash
                'debit'        => 0,
                'credit'       => $supplierBill->total_amount,
                'description'  => 'Cash payment to vendor – Supplier Bill '.$supplierBill->formatted_id,
            ],
        ], now(), 'Payment – Supplier Bill '.$supplierBill->formatted_id, $supplierBill, 'draft');

        $supplierBill->update([
            'payment_journal_id' => $entry->id,
        ]);

        // Update supplier bill payment record
        $supplierBillPayment = SupplierBillPayment::where('supplier_bill_id', $supplierBill->id)->first();
        if ($supplierBillPayment) {
            $supplierBillPayment->update([
                'payment_status'    => 'paid',
                'payment_journal_id' => $entry->id,
                'paid_at'           => now(),
            ]);
        }

        AuditLog::create([
            'user_id'      => auth()->id(),
            'action'       => 'supplier_bill_paid',
            'description'  => 'Supplier Bill '.$supplierBill->formatted_id.' marked as paid with draft payment journal entry.',
            'subject_type' => $supplierBill->getMorphClass(),
            'subject_id'   => $supplierBill->getKey(),
        ]);

        return back()->with('success', 'Supplier Bill marked as paid. Payment journal entry created with Draft status.');
    }

    /**
     * Show payment information page for a supplier bill.
     */
    public function paymentInfo(SupplierBill $supplierBill): View
    {
        $supplierBill->load(['vendor', 'items.product', 'grn', 'purchaseJournal.lines.account', 'paymentJournal.lines.account', 'payment.paymentJournal']);
        return view('supplier-bills.payment-info', compact('supplierBill'));
    }
} 
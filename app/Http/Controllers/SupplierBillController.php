<?php

namespace App\Http\Controllers;

use App\Models\SupplierBill;
use App\Services\SupplierBillService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierBillController extends Controller
{
    public function __construct(private readonly SupplierBillService $supplierBillService) {}

    public function index(Request $request): View
    {
        $filters = [
            'status' => $request->status,
        ];

        $bills = $this->supplierBillService->getFilteredSupplierBills($filters, 20);

        return view('supplier-bills.index', compact('bills'));
    }

    public function show(SupplierBill $supplierBill): View
    {
        $supplierBill = $this->supplierBillService->getSupplierBillWithDetails($supplierBill->id);

        return view('supplier-bills.show', compact('supplierBill'));
    }

    /**
     * Post (confirm) a draft supplier bill
     */
    public function post(SupplierBill $supplierBill): RedirectResponse
    {
        try {
            $this->supplierBillService->postSupplierBill($supplierBill);

            return redirect()->route('supplier-bills.payment-info', $supplierBill)
                ->with('success', 'Supplier Bill has been posted successfully. Purchase journal entry created with Draft status. You can now review payment information.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Mark supplier bill as paid and create payment journal entry
     */
    public function pay(SupplierBill $supplierBill): RedirectResponse
    {
        try {
            $this->supplierBillService->paySupplierBill($supplierBill);

            return redirect()->route('supplier-bills.payment-info', $supplierBill)
                ->with('success', 'Supplier Bill marked as paid successfully. Payment journal entry created with Draft status.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Show payment information page
     */
    public function paymentInfo(SupplierBill $supplierBill): View
    {
        $supplierBill = $this->supplierBillService->getSupplierBillWithDetails($supplierBill->id);

        return view('supplier-bills.payment-info', compact('supplierBill'));
    }
}

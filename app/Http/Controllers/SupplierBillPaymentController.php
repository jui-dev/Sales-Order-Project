<?php

namespace App\Http\Controllers;

use App\Models\SupplierBillPayment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierBillPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $query = SupplierBillPayment::with(['vendor', 'supplierBill'])
            ->when($request->filled('payment_status'), fn($q) => $q->where('payment_status', $request->payment_status))
            ->orderByDesc('id');

        $payments = $query->paginate(20)->withQueryString();

        return view('supplier-bill-payments.index', compact('payments'));
    }

    public function show(SupplierBillPayment $supplierBillPayment): View
    {
        $supplierBillPayment->load(['vendor', 'supplierBill', 'paymentJournal.lines.account']);
        return view('supplier-bill-payments.show', compact('supplierBillPayment'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\SupplierBillPaymentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierBillPaymentController extends Controller
{
    public function __construct(private readonly SupplierBillPaymentService $supplierBillPaymentService)
    {
    }

    public function index(Request $request): View
    {
        $filters = [
            'payment_status' => $request->payment_status,
        ];

        $payments = $this->supplierBillPaymentService->getFilteredPayments($filters, 20);
        $statistics = $this->supplierBillPaymentService->getPaymentStatistics();
        $filterOptions = $this->supplierBillPaymentService->getFilterOptions();

        return view('supplier-bill-payments.index', compact('payments', 'statistics', 'filterOptions'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Customer;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService)
    {
    }

    public function index(Request $request)
    {
        $query = Invoice::with(['customer', 'order']);

        // Filters
        if ($request->filled('status')) {
            $query->where('payment_status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('invoice_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('invoice_date', '<=', $request->to);
        }

        $invoices = $query->latest()->paginate(20)->withQueryString();
        $customers = Customer::orderBy('name')->get();

        return view('invoices.index', compact('invoices', 'customers'));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['items', 'customer', 'order', 'payments']);
        return view('invoices.show', compact('invoice'));
    }

    public function download(Invoice $invoice)
    {
        $pdf = $this->invoiceService->renderPdf($invoice);
        return $pdf->download($invoice->invoice_number.'.pdf');
    }
} 
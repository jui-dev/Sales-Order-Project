<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Traits\HasApiResponses;
use App\Exceptions\DataNotFoundException;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    use HasApiResponses;

    public function __construct(private readonly InvoiceService $invoiceService)
    {
    }

    public function index(Request $request): View
    {
        $filters = [
            'search' => $request->search,
            'status' => $request->status,
            'customer_id' => $request->customer_id,
            // The unified search component names its date range date_from/date_to;
            // from/to are kept so older bookmarked links still filter.
            'from' => $request->date_from ?? $request->from,
            'to' => $request->date_to ?? $request->to,
            'sort' => $request->sort,
            'direction' => $request->direction,
        ];

        $invoices = $this->invoiceService->getFilteredInvoices($filters, 20);
        $filterOptions = $this->invoiceService->getFilterOptions();
        $sortOptions = $this->invoiceService->getSortOptions();

        return view('invoices.index', compact('invoices', 'filterOptions', 'sortOptions'));
    }

    /**
     * API endpoint to get all invoices
     */
    public function apiIndex(Request $request): JsonResponse
    {
        return $this->handlePaginatedApiOperation(
            function() use ($request) {
                $filters = [
                    'status' => $request->get('status'),
                    'customer_id' => $request->get('customer_id'),
                    'from' => $request->get('from'),
                    'to' => $request->get('to'),
                ];

                $perPage = $request->get('per_page', 20);
                return $this->invoiceService->getFilteredInvoices($filters, $perPage);
            },
            'invoices',
            'Invoices retrieved successfully'
        );
    }

    public function show(Invoice $invoice): View
    {
        $invoice = $this->invoiceService->getInvoiceWithDetails($invoice->id);
        return view('invoices.show', compact('invoice'));
    }

    /**
     * API endpoint to get a specific invoice
     */
    public function apiShow(int $id): JsonResponse
    {
        return $this->handleSingleItemApiOperation(
            function() use ($id) {
                return $this->invoiceService->getInvoiceWithDetails($id);
            },
            'invoice',
            'Invoice retrieved successfully'
        );
    }

    public function download(Invoice $invoice)
    {
        $pdf = $this->invoiceService->renderPdf($invoice);
        return $pdf->download($invoice->invoice_number.'.pdf');
    }
}

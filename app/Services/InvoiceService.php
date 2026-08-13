<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Traits\HasErrorHandling;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\Order;

class InvoiceService
{
    use HasErrorHandling;

    /**
     * Get filtered invoices with pagination
     */
    public function getFilteredInvoices(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->getPaginatedOrEmpty(
            function() use ($filters, $perPage) {
                $query = Invoice::with(['customer', 'order']);

                // Apply search filter
                if (!empty($filters['search'])) {
                    $search = $filters['search'];
                    $query->where(function ($q) use ($search) {
                        $q->where('id', 'like', "%{$search}%")
                          ->orWhere('invoice_number', 'like', "%{$search}%")
                          ->orWhereHas('customer', function ($customerQuery) use ($search) {
                              $customerQuery->where('name', 'like', "%{$search}%");
                          });
                    });
                }

                // Apply status filter
                if (!empty($filters['status'])) {
                    $query->where('payment_status', $filters['status']);
                }

                // Apply customer filter
                if (!empty($filters['customer_id'])) {
                    $query->where('customer_id', $filters['customer_id']);
                }

                // Apply date filters
                if (!empty($filters['from'])) {
                    $query->whereDate('invoice_date', '>=', $filters['from']);
                }
                if (!empty($filters['to'])) {
                    $query->whereDate('invoice_date', '<=', $filters['to']);
                }

                // Apply sorting
                $sortField = $filters['sort'] ?? 'id';
                $sortDirection = $filters['direction'] ?? 'desc';
                $query->orderBy($sortField, $sortDirection);

                return $query->paginate($perPage);
            },
            'invoices',
            $perPage,
            $filters
        );
    }

    /**
     * Get invoice with all related data
     */
    public function getInvoiceWithDetails(int $id): Invoice
    {
        return $this->handleServiceOperation(
            function() use ($id) {
                $invoice = Invoice::with(['items', 'customer', 'order', 'payments'])->find($id);

                if (!$invoice) {
                    $this->logMissingData('invoice', $id);
                    throw new \App\Exceptions\DataNotFoundException('invoice', $id);
                }

                return $invoice;
            },
            'invoice',
            $id
        );
    }

    /**
     * Render PDF for invoice
     */
    public function renderPdf(Invoice $invoice): \Illuminate\Http\Response
    {
        return $this->handleServiceOperation(
            function() use ($invoice) {
                $pdf = \PDF::loadView('invoices.pdf', compact('invoice'));
                return $pdf->stream("invoice-{$invoice->invoice_number}.pdf");
            },
            'invoice PDF',
            $invoice->id
        );
    }

    /**
     * Generate invoice from order
     */
    public function generateFromOrder(Order $order): Invoice
    {
        return $this->handleServiceOperation(
            function() use ($order) {
                // Check if invoice already exists for this order
                if ($order->invoice()->exists()) {
                    return $order->invoice;
                }

                // Load order with items and customer
                $order->load(['items.product', 'customer']);

                // Calculate totals
                $subtotal = $order->items->sum('subtotal');
                $tax = 0; // Calculate tax if needed
                $discount = 0; // Calculate discount if needed
                $total = $subtotal + $tax - $discount;

                // Generate invoice number
                $lastInvoice = Invoice::orderBy('id', 'desc')->first();
                $nextNumber = $lastInvoice ? (intval(substr($lastInvoice->invoice_number ?? 'INV0000', 3)) + 1) : 1;
                $invoiceNumber = 'INV' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

                // Create invoice
                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'invoice_date' => now(),
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'discount' => $discount,
                    'total' => $total,
                    'payment_status' => 'unpaid',
                ]);

                // Create invoice items from order items
                foreach ($order->items as $orderItem) {
                    $invoice->items()->create([
                        'product_id' => $orderItem->product_id,
                        'description' => $orderItem->product->name,
                        'quantity' => $orderItem->quantity,
                        'unit_price' => $orderItem->unit_price,
                        'total' => $orderItem->subtotal,
                    ]);
                }

                return $invoice->load(['items', 'customer', 'order']);
            },
            'invoice from order',
            $order->id
        );
    }

    /**
     * Get filter options for the view
     */
    public function getFilterOptions(): array
    {
        return [
            'search' => [
                'type' => 'text',
                'label' => 'Search',
                'placeholder' => 'Search by invoice number or customer name'
            ],
            'status' => [
                'type' => 'select',
                'label' => 'Payment Status',
                'options' => [
                    'unpaid' => 'Unpaid',
                    'partially_paid' => 'Partially Paid',
                    'paid' => 'Paid',
                ]
            ],
            'customer_id' => [
                'type' => 'select',
                'label' => 'Customer',
                'options' => Customer::orderBy('name')->pluck('name', 'id')->toArray()
            ],
            'date_from' => [
                'type' => 'date',
                'label' => 'From Date',
                'placeholder' => 'Select start date'
            ],
            'date_to' => [
                'type' => 'date',
                'label' => 'To Date',
                'placeholder' => 'Select end date'
            ]
        ];
    }

    /**
     * Get sort options for the view
     */
    public function getSortOptions(): array
    {
        return [
            'id' => 'ID',
            'invoice_number' => 'Invoice Number',
            'invoice_date' => 'Invoice Date',
            'total' => 'Total Amount',
            'payment_status' => 'Payment Status',
            'created_at' => 'Created Date',
        ];
    }
}

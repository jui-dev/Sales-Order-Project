<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class InvoiceService
{
    /**
     * Generate an invoice for the given order if not already exists.
     */
    public function generateFromOrder(Order $order): Invoice
    {
        if ($order->invoice) {
            return $order->invoice; // already exists
        }

        return DB::transaction(function () use ($order) {
            // Calculate totals based on order items
            $subtotal = $order->orderItems->sum(fn ($item) => $item->subtotal ?? ($item->unit_price * $item->quantity));
            $taxRate  = config('app.invoice_tax_rate', 0); // define in .env if needed
            $tax      = round($subtotal * $taxRate, 2);
            $discount = 0; // extend logic as needed
            $total    = $subtotal + $tax - $discount;

            /** @var Invoice $invoice */
            $invoice = Invoice::create([
                'order_id'       => $order->id,
                'customer_id'    => $order->customer_id,
                'invoice_date'   => now(),
                'subtotal'       => $subtotal,
                'tax'            => $tax,
                'discount'       => $discount,
                'total'          => $total,
                'payment_status' => 'unpaid',
                'invoice_number' => '', // provisional
            ]);

            // Generate invoice number e.g. INV-000001
            $invoice->invoice_number = 'INV-' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT);
            $invoice->save();

            // Create invoice items mirror order items
            foreach ($order->orderItems as $item) {
                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'product_id'  => $item->product_id,
                    'description' => optional($item->product)->name ?? 'Product #'.$item->product_id,
                    'quantity'    => $item->quantity,
                    'unit_price'  => $item->unit_price,
                    'total'       => $item->subtotal ?? ($item->unit_price * $item->quantity),
                ]);
            }

            return $invoice->fresh(['items', 'customer', 'order']);
        });
    }

    /**
     * Create a DomPDF instance and return binary content.
     */
    public function renderPdf(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice->load(['items', 'customer', 'order']),
        ]);

        return $pdf;
    }
} 
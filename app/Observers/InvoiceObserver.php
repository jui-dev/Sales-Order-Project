<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\AuditLog;
use App\Services\AccountingService;
use Illuminate\Support\Facades\App;

class InvoiceObserver
{
    /**
     * Handle the Invoice "created" event.
     */
    public function created(Invoice $invoice): void
    {
        // Idempotency check – should not happen since we're on created
        $exists = JournalEntry::where('source_type', $invoice->getMorphClass())
            ->where('source_id', $invoice->getKey())
            ->exists();
        if ($exists) {
            return;
        }

        $totalSale = round($invoice->total, 2);
        if ($totalSale <= 0) {
            return;
        }

        // Determine COGS (sum of unit_cost * quantity from the related order items)
        $totalCost = 0;
        $order = $invoice->relationLoaded('order') ? $invoice->order : $invoice->order;
        if ($order) {
            $order->loadMissing('orderItems.product');
            $totalCost = $order->orderItems->sum(function ($item) {
                $unitCost = $item->unit_cost ?? ($item->product->purchase_price ?? 0);
                return $unitCost * $item->quantity;
            });
        }
        $totalCost = round($totalCost, 2);

        $lines = [
            [
                'account_code' => '1100', // Accounts Receivable
                'debit'        => $totalSale,
                'credit'       => 0,
                'description'  => 'Invoice #'.$invoice->invoice_number.' – A/R',
            ],
            [
                'account_code' => '4000', // Sales Revenue
                'debit'        => 0,
                'credit'       => $totalSale,
                'description'  => 'Invoice #'.$invoice->invoice_number.' – Revenue',
            ],
        ];

        if ($totalCost > 0) {
            $lines[] = [
                'account_code' => '5000', // COGS
                'debit'        => $totalCost,
                'credit'       => 0,
                'description'  => 'Invoice #'.$invoice->invoice_number.' – COGS',
            ];
            $lines[] = [
                'account_code' => '1200', // Inventory
                'debit'        => 0,
                'credit'       => $totalCost,
                'description'  => 'Invoice #'.$invoice->invoice_number.' – Inventory',
            ];
        }

        /** @var AccountingService $acct */
        $acct = App::make(AccountingService::class);
        $acct->post($lines, $invoice->invoice_date ?? now(), 'Sales Invoice #'.$invoice->invoice_number, $invoice);

        // Audit trail
        AuditLog::create([
            'user_id'      => auth()->id(),
            'action'       => 'invoice_created',
            'description'  => 'Invoice #' . $invoice->invoice_number . ' created.',
            'subject_type' => $invoice->getMorphClass(),
            'subject_id'   => $invoice->getKey(),
        ]);
    }
} 
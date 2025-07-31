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
        // Idempotency check
        $exists = JournalEntry::where('source_type', $invoice->getMorphClass())
            ->where('source_id', $invoice->getKey())
            ->exists();
        if ($exists) {
            return;
        }

        $invoiceAmount = round($invoice->total, 2);
        if ($invoiceAmount <= 0) {
            return;
        }

        // Create journal entry for invoice (sale)
        $lines = [
            [
                'account_code' => '1100', // Accounts Receivable
                'debit'        => $invoiceAmount,
                'credit'       => 0,
                'description'  => 'Sale recorded for Invoice #' . $invoice->invoice_number,
            ],
            [
                'account_code' => '4000', // Sales Revenue
                'debit'        => 0,
                'credit'       => $invoiceAmount,
                'description'  => 'Sale recorded for Invoice #' . $invoice->invoice_number,
            ],
        ];

        /** @var AccountingService $acct */
        $acct = App::make(AccountingService::class);
        $acct->post($lines, $invoice->invoice_date ?? now(), 'Sale recorded for Invoice #' . $invoice->invoice_number, $invoice);

        // Audit trail
        AuditLog::create([
            'user_id'      => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'action'       => 'invoice_created',
            'description'  => 'Invoice #' . $invoice->invoice_number . ' created for $' . number_format($invoiceAmount, 2),
            'subject_type' => $invoice->getMorphClass(),
            'subject_id'   => $invoice->getKey(),
        ]);
    }
} 
<?php

namespace App\Observers;

use App\Accounting\PostingEngine;
use App\Models\AuditLog;
use App\Models\Invoice;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Posting waits for the transaction to commit.
 *
 * An invoice and its lines are written together, and the lines are what revenue
 * is attributed to. Posting on the bare created event ran while the invoice was
 * still line-less, so every sale credited one unattributed lump to revenue and
 * profit per product could not be read off the ledger at all.
 */
class InvoiceObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly PostingEngine $ledger,
    ) {
    }

    /**
     * Handle the Invoice "created" event.
     *
     * What the sale posts - receivable, revenue, tax and discount - is
     * InvoicePostingRule's business, not this class's. The engine is
     * idempotent on the invoice and the rule, so re-running is a no-op and the
     * check this method used to do by hand is no longer needed here.
     */
    public function created(Invoice $invoice): void
    {
        $this->ledger->postFor($invoice);

        AuditLog::create([
            'user_id'      => auth()->id() ?? 1,
            'action'       => 'invoice_created',
            'description'  => 'Invoice #' . $invoice->invoice_number . ' created for ' . number_format((float) $invoice->total, 2),
            'subject_type' => $invoice->getMorphClass(),
            'subject_id'   => $invoice->getKey(),
        ]);
    }
}

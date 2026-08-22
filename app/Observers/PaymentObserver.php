<?php

namespace App\Observers;

use App\Accounting\PostingEngine;
use App\Models\AuditLog;
use App\Models\Payment;

class PaymentObserver
{
    public function __construct(
        private readonly PostingEngine $ledger,
    ) {
    }

    /**
     * Handle the Payment "created" event.
     *
     * This used to catch and log every exception, so a payment whose entry
     * could not be built was recorded with nothing on the ledger against it
     * and no sign beyond a log line. A payment that cannot be accounted for is
     * a payment that should not be recorded, so the failure now travels.
     */
    public function created(Payment $payment): void
    {
        $this->ledger->postFor($payment);

        AuditLog::create([
            'user_id'      => auth()->id() ?? 1,
            'action'       => 'payment_created',
            'description'  => 'Payment of ' . number_format((float) $payment->amount, 2) . ' recorded for Invoice #' . ($payment->invoice?->invoice_number ?? $payment->invoice_id),
            'subject_type' => $payment->getMorphClass(),
            'subject_id'   => $payment->getKey(),
        ]);
    }
}

<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\JournalEntry;
use App\Models\AuditLog;
use App\Services\AccountingService;
use Illuminate\Support\Facades\App;

class PaymentObserver
{
    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment): void
    {
        try {
            // Load the invoice relationship
            $payment->load('invoice');
            
            if (!$payment->invoice) {
                \Log::error('Payment created without invoice: Payment ID ' . $payment->id);
                return;
            }

            // Idempotency check
            $exists = JournalEntry::where('source_type', $payment->getMorphClass())
                ->where('source_id', $payment->getKey())
                ->exists();
            if ($exists) {
                return;
            }

            $paymentAmount = round($payment->amount, 2);
            if ($paymentAmount <= 0) {
                return;
            }

            // Create journal entry for payment
            $lines = [
                [
                    'account_code' => '1000', // Cash
                    'debit'        => $paymentAmount,
                    'credit'       => 0,
                    'description'  => 'Payment received for Invoice #' . $payment->invoice->invoice_number,
                ],
                [
                    'account_code' => '1100', // Accounts Receivable
                    'debit'        => 0,
                    'credit'       => $paymentAmount,
                    'description'  => 'Payment received for Invoice #' . $payment->invoice->invoice_number,
                ],
            ];

            /** @var AccountingService $acct */
            $acct = App::make(AccountingService::class);
            $acct->post($lines, $payment->payment_date ?? now(), 'Payment received for Invoice #' . $payment->invoice->invoice_number, $payment);

            // Audit trail
            AuditLog::create([
                'user_id'      => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
                'action'       => 'payment_created',
                'description'  => 'Payment of $' . number_format($paymentAmount, 2) . ' recorded for Invoice #' . $payment->invoice->invoice_number,
                'subject_type' => $payment->getMorphClass(),
                'subject_id'   => $payment->getKey(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in PaymentObserver::created: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
        }
    }
} 
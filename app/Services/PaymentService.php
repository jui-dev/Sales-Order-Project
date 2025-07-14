<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use App\Services\AccountingService;
use App\Models\AuditLog;

class PaymentService
{
    public function __construct(private readonly AccountingService $accountingService)
    {
    }

    public function recordPayment(Invoice $invoice, float $amount, string $method = 'cash'): Payment
    {
        return DB::transaction(function () use ($invoice, $amount, $method) {
            // Create payment record
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'amount'     => $amount,
                'method'     => $method,
                'paid_at'    => now(),
            ]);

            // Calculate total paid to determine status
            $totalPaid = $invoice->payments()->sum('amount');
            if ($totalPaid >= $invoice->total) {
                $invoice->payment_status = 'paid';
            } elseif ($totalPaid > 0) {
                $invoice->payment_status = 'partially_paid';
            }
            $invoice->paid_at = $payment->paid_at;
            $invoice->save();

            // Post journal entry (Debit Cash / Credit Accounts Receivable)
            $description = 'Payment for Invoice ' . $invoice->invoice_number;
            $this->accountingService->post([
                ['account_code' => '1000', 'debit' => $amount, 'credit' => 0, 'description' => $description], // Cash
                ['account_code' => '1100', 'debit' => 0, 'credit' => $amount, 'description' => $description], // Accounts Receivable
            ], now(), $description, $payment, 'draft');

            // Audit log
            AuditLog::create([
                'user_id'      => auth()->id(),
                'action'       => 'payment_recorded',
                'description'  => $description,
                'subject_type' => $payment->getMorphClass(),
                'subject_id'   => $payment->getKey(),
            ]);

            return $payment;
        });
    }
} 
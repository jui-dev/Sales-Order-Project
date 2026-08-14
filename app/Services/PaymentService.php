<?php

namespace App\Services;

use App\Models\Payment;
use App\Traits\HasErrorHandling;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentService
{
    use HasErrorHandling;

    public function list(): Collection
    {
        return $this->getCollectionOrEmpty(Payment::class, 'payments');
    }

    /**
     * Payments received from customers, newest first.
     *
     * The payments table carries no payment_number and no payment_method: a
     * payment is identified by its reference_number (or its id) and the method
     * lives in the 'method' column. Filters are named after those columns.
     */
    public function getFilteredPayments(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->getPaginatedOrEmpty(
            function() use ($filters, $perPage) {
                $query = Payment::with(['invoice.customer'])
                    ->orderByDesc('payment_date')
                    ->orderByDesc('id');

                // Apply filters
                if (!empty($filters['search'])) {
                    $search = $filters['search'];
                    $query->where(function($q) use ($search) {
                        $q->where('reference_number', 'like', "%{$search}%")
                          ->orWhere('id', 'like', "%{$search}%")
                          ->orWhereHas('invoice', function($invoiceQuery) use ($search) {
                              $invoiceQuery->where('invoice_number', 'like', "%{$search}%");
                          })
                          ->orWhereHas('invoice.customer', function($customerQuery) use ($search) {
                              $customerQuery->where('name', 'like', "%{$search}%");
                          });
                    });
                }

                if (!empty($filters['method'])) {
                    $query->where('method', $filters['method']);
                }

                if (!empty($filters['status'])) {
                    $query->where('status', $filters['status']);
                }

                if (!empty($filters['customer_id'])) {
                    $query->whereHas('invoice', function($invoiceQuery) use ($filters) {
                        $invoiceQuery->where('customer_id', $filters['customer_id']);
                    });
                }

                if (!empty($filters['date_from'])) {
                    $query->whereDate('payment_date', '>=', $filters['date_from']);
                }

                if (!empty($filters['date_to'])) {
                    $query->whereDate('payment_date', '<=', $filters['date_to']);
                }

                if (!empty($filters['sort'])) {
                    $query->reorder($filters['sort'], $filters['direction'] ?? 'desc');
                }

                return $query->paginate($perPage);
            },
            'payments',
            $perPage,
            $filters
        );
    }

    public function get(int $id): Payment
    {
        return $this->handleServiceOperation(
            function() use ($id) {
                $payment = Payment::with(['invoice.customer', 'invoice.order'])->find($id);

                if (!$payment) {
                    $this->logMissingData('payment', $id);
                    throw new \App\Exceptions\DataNotFoundException('payment', $id);
                }
                
                return $payment;
            },
            'payment',
            $id
        );
    }

    public function create(array $data): Payment
    {
        return $this->handleServiceOperation(
            fn() => Payment::create($data),
            'payment'
        );
    }

    public function update(int $id, array $data): Payment
    {
        return $this->handleServiceOperation(
            function() use ($id, $data) {
                $payment = $this->findOrFail(Payment::class, $id, 'payment');
                $payment->update($data);
                return $payment;
            },
            'payment',
            $id
        );
    }

    public function delete(int $id): void
    {
        $this->handleServiceOperation(
            function() use ($id) {
                $payment = $this->findOrFail(Payment::class, $id, 'payment');
                $payment->delete();
            },
            'payment',
            $id
        );
    }

    /**
     * Record a payment for an invoice
     */
    public function recordPayment(\App\Models\Invoice $invoice, float $amount, string $method, array $additionalData = []): Payment
    {
        return $this->handleServiceOperation(
            function() use ($invoice, $amount, $method, $additionalData) {
                // Create payment record
                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'method' => $method,
                    'payment_date' => now(),
                    'reference_number' => $additionalData['reference'] ?? null,
                    'notes' => $additionalData['notes'] ?? null,
                    'status' => 'completed',
                ]);

                // Update invoice payment status
                $totalPaid = $invoice->payments()->sum('amount');
                $paymentStatus = $totalPaid >= $invoice->total ? 'paid' : 'partially_paid';
                
                $invoice->update([
                    'payment_status' => $paymentStatus,
                    'paid_at' => $paymentStatus === 'paid' ? now() : null,
                ]);

                return $payment;
            },
            'payment'
        );
    }

    /**
     * Headline figures for the payments list.
     *
     * 'Outstanding' is read off the invoices rather than the payments, because
     * what is still owed only exists as the gap between an invoice total and
     * the payments against it.
     */
    public function getPaymentStatistics(): array
    {
        $received = (float) Payment::where('status', 'completed')->sum('amount');

        $invoiced = (float) \App\Models\Invoice::sum('total');
        $settled  = (float) \App\Models\Invoice::where('payment_status', 'paid')->sum('total');

        return [
            'count'       => Payment::count(),
            'received'    => $received,
            'outstanding' => max(0, $invoiced - $received),
            'open'        => \App\Models\Invoice::whereIn('payment_status', ['unpaid', 'partially_paid'])->count(),
            'settled'     => $settled,
        ];
    }

    /**
     * Get filter options for payments.
     *
     * Shaped for <x-unified-search>, and the method list mirrors the choices the
     * record-payment form offers so a filter can never name a method that
     * cannot have been recorded.
     */
    public function getFilterOptions(): array
    {
        return [
            'method' => [
                'type' => 'select',
                'label' => 'Payment Method',
                'options' => self::methods(),
            ],
            'status' => [
                'type' => 'select',
                'label' => 'Status',
                'options' => [
                    'completed' => 'Completed',
                    'pending' => 'Pending',
                    'failed' => 'Failed',
                    'cancelled' => 'Cancelled',
                ],
            ],
            'customer_id' => [
                'type' => 'select',
                'label' => 'Customer',
                'options' => \App\Models\Customer::orderBy('name')->pluck('name', 'id')->toArray(),
            ],
            'date_from' => [
                'type' => 'date',
                'label' => 'Paid From',
            ],
            'date_to' => [
                'type' => 'date',
                'label' => 'Paid To',
            ],
        ];
    }

    /**
     * The payment methods a payment can be recorded against.
     */
    public static function methods(): array
    {
        return [
            'cash' => 'Cash',
            'credit_card' => 'Credit Card',
            'bank_transfer' => 'Bank Transfer',
            'check' => 'Check',
            'other' => 'Other',
        ];
    }

    /**
     * Get sort options for payments
     */
    public function getSortOptions(): array
    {
        return [
            'payment_date' => 'Payment Date',
            'id' => 'Payment ID',
            'amount' => 'Amount',
            'method' => 'Payment Method',
            'status' => 'Status',
        ];
    }
} 
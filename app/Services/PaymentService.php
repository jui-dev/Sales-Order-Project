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

    public function getFilteredPayments(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->getPaginatedOrEmpty(
            function() use ($filters, $perPage) {
                $query = Payment::with(['invoice.customer', 'paymentMethod'])
                    ->latest();

                // Apply filters
                if (!empty($filters['search'])) {
                    $search = $filters['search'];
                    $query->where(function($q) use ($search) {
                        $q->where('payment_number', 'like', "%{$search}%")
                          ->orWhere('reference_number', 'like', "%{$search}%")
                          ->orWhereHas('invoice.customer', function($customerQuery) use ($search) {
                              $customerQuery->where('name', 'like', "%{$search}%");
                          });
                    });
                }

                if (!empty($filters['payment_method'])) {
                    $query->where('payment_method', $filters['payment_method']);
                }

                if (!empty($filters['status'])) {
                    $query->where('status', $filters['status']);
                }

                if (!empty($filters['date_from'])) {
                    $query->where('payment_date', '>=', $filters['date_from']);
                }

                if (!empty($filters['date_to'])) {
                    $query->where('payment_date', '<=', $filters['date_to']);
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
                $payment = Payment::with(['invoice.customer', 'paymentMethod'])->find($id);
                
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
     * Get filter options for payments
     */
    public function getFilterOptions(): array
    {
        return [
            'payment_methods' => [
                'cash' => 'Cash',
                'check' => 'Check',
                'bank_transfer' => 'Bank Transfer',
                'credit_card' => 'Credit Card',
                'debit_card' => 'Debit Card',
                'online_payment' => 'Online Payment',
            ],
            'statuses' => [
                'pending' => 'Pending',
                'completed' => 'Completed',
                'failed' => 'Failed',
                'cancelled' => 'Cancelled',
            ],
        ];
    }

    /**
     * Get sort options for payments
     */
    public function getSortOptions(): array
    {
        return [
            'payment_date' => 'Payment Date',
            'payment_number' => 'Payment Number',
            'amount' => 'Amount',
            'payment_method' => 'Payment Method',
            'status' => 'Status',
            'customer_name' => 'Customer Name',
        ];
    }
} 
<?php

namespace App\Services;

use App\Models\SupplierBillPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierBillPaymentService
{
    /**
     * Get filtered supplier bill payments with pagination
     */
    public function getFilteredPayments(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = SupplierBillPayment::with(['vendor', 'supplierBill']);

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStatistics(): array
    {
        $totalPayments = SupplierBillPayment::count();
        $paidPayments = SupplierBillPayment::where('payment_status', 'paid')->count();
        $unpaidPayments = SupplierBillPayment::where('payment_status', 'unpaid')->count();
        $totalAmount = SupplierBillPayment::sum('payment_amount');
        $paidAmount = SupplierBillPayment::where('payment_status', 'paid')->sum('payment_amount');

        return [
            'total_payments' => $totalPayments,
            'paid_payments' => $paidPayments,
            'unpaid_payments' => $unpaidPayments,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'unpaid_amount' => $totalAmount - $paidAmount,
        ];
    }

    /**
     * Get filter options for the view
     */
    public function getFilterOptions(): array
    {
        return [
            'payment_status' => [
                'type' => 'select',
                'label' => 'Payment Status',
                'options' => [
                    'unpaid' => 'Unpaid',
                    'paid' => 'Paid',
                ]
            ],
            'date_from' => [
                'type' => 'date',
                'label' => 'Date From',
                'placeholder' => 'Select start date...'
            ],
            'date_to' => [
                'type' => 'date',
                'label' => 'Date To',
                'placeholder' => 'Select end date...'
            ]
        ];
    }
} 
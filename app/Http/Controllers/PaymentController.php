<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Traits\HasApiResponses;
use App\Exceptions\DataNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    use HasApiResponses;

    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    /**
     * API endpoint to get all payments
     */
    public function apiIndex(Request $request): JsonResponse
    {
        return $this->handlePaginatedApiOperation(
            function() use ($request) {
                $filters = [
                    'invoice_id' => $request->get('invoice_id'),
                    'method' => $request->get('method'),
                    'date_from' => $request->get('date_from'),
                    'date_to' => $request->get('date_to'),
                    'search' => $request->get('search'),
                ];

                $perPage = $request->get('per_page', 20);
                return $this->paymentService->getFilteredPayments($filters, $perPage);
            },
            'payments',
            'Payments retrieved successfully'
        );
    }

    /**
     * API endpoint to get a specific payment
     */
    public function apiShow(int $id): JsonResponse
    {
        return $this->handleSingleItemApiOperation(
            function() use ($id) {
                return $this->paymentService->get($id);
            },
            'payment',
            'Payment retrieved successfully'
        );
    }

    public function store(Request $request, Invoice $invoice): \Illuminate\Http\RedirectResponse
    {
        try {
            $validated = $request->validate([
                'amount' => ['required', 'numeric', 'min:0.01'],
                'method' => ['required', 'string', 'max:50'],
                'reference' => ['nullable', 'string', 'max:100'],
                'notes' => ['nullable', 'string', 'max:500'],
            ]);

            $additionalData = [
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ];

            $this->paymentService->recordPayment($invoice, (float) $validated['amount'], $validated['method'], $additionalData);

            return back()->with('success', 'Payment recorded successfully.');
        } catch (\Exception $e) {
            \Log::error('Payment processing error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return back()->with('error', 'Unable to process payment. Please try again later.');
        }
    }
} 
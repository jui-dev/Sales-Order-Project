<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Traits\HasApiResponses;
use App\Exceptions\DataNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    use HasApiResponses;

    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    /**
     * Every payment received from a customer, across all invoices.
     */
    public function index(Request $request): View
    {
        $filters = [
            'search' => $request->search,
            'method' => $request->method,
            'status' => $request->status,
            'customer_id' => $request->customer_id,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'sort' => $request->sort,
            'direction' => $request->direction,
        ];

        $payments = $this->paymentService->getFilteredPayments($filters, 20);
        $statistics = $this->paymentService->getPaymentStatistics();
        $filterOptions = $this->paymentService->getFilterOptions();
        $sortOptions = $this->paymentService->getSortOptions();

        return view('payments.index', compact('payments', 'statistics', 'filterOptions', 'sortOptions'));
    }

    public function show(int $id): View|RedirectResponse
    {
        try {
            $payment = $this->paymentService->get($id);
            $payment->loadMissing('invoice.payments');

            return view('payments.show', compact('payment'));
        } catch (DataNotFoundException $e) {
            return redirect()->route('payments.index')->with('error', $e->getMessage());
        }
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

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        try {
            // What is still owed. recordPayment() reads the payment status back
            // off the running total, so an amount above the balance would settle
            // the invoice while leaving the books over-credited.
            $balance = round($invoice->total - $invoice->payments()->sum('amount'), 2);

            $validated = $request->validate([
                'amount' => ['required', 'numeric', 'min:0.01', 'max:' . max($balance, 0.01)],
                'method' => ['required', Rule::in(array_keys(PaymentService::methods()))],
                'reference' => ['nullable', 'string', 'max:100'],
                'notes' => ['nullable', 'string', 'max:500'],
            ], [
                'amount.max' => 'Only $' . number_format($balance, 2) . ' is still owed on this invoice.',
            ]);

            $additionalData = [
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ];

            $this->paymentService->recordPayment($invoice, (float) $validated['amount'], $validated['method'], $additionalData);

            return back()->with('success', 'Payment recorded successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // A rejected amount or method is the recorder's to correct, so it has
            // to reach them as a field error rather than the generic failure below.
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Payment processing error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return back()->with('error', 'Unable to process payment. Please try again later.');
        }
    }
} 
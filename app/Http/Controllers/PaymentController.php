<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use App\Services\PaymentService;

class PaymentController extends Controller
{
    public function store(Request $request, Invoice $invoice, PaymentService $paymentService)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'max:50'],
        ]);

        $paymentService->recordPayment($invoice, (float) $validated['amount'], $validated['method']);

        return back()->with('success', 'Payment recorded successfully.');
    }
} 
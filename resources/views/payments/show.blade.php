@extends('layouts.app')

@php
    $invoice  = $payment->invoice;
    $customer = optional($invoice)->customer;
    $order    = optional($invoice)->order;

    $reference = 'PAY-' . str_pad($payment->id, 4, '0', STR_PAD_LEFT);
    $methodLabel = \App\Services\PaymentService::methods()[$payment->method]
        ?? ucfirst(str_replace('_', ' ', $payment->method));

    $statusColour = match ($payment->status) {
        'completed' => 'success',
        'pending' => 'warning',
        'failed', 'cancelled' => 'danger',
        default => 'secondary',
    };

    // What this payment left behind on the invoice. Read off the invoice's own
    // payments rather than this one alone, since several can settle one invoice.
    $invoicePayments = $invoice ? $invoice->payments : collect();
    $amountPaid = $invoicePayments->sum('amount');
    $balanceDue = $invoice ? max(0, $invoice->total - $amountPaid) : 0;

    $paidOn = optional($payment->payment_date)->format('M d, Y') ?: 'No date recorded';

    // The payment is the last stage of the sales workflow, and shares the
    // invoice's page there, so the rail is built from the invoice.
    $stages = ($invoice && $order) ? \App\Support\OrderWorkflow::forInvoice($invoice) : null;
@endphp

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Payment {{ $reference }}</h1>
            <p class="text-muted mb-0">
                <span class="badge bg-{{ $statusColour }}">{{ ucfirst($payment->status) }}</span>
                <span class="ms-2">${{ number_format($payment->amount, 2) }} by {{ $methodLabel }}</span>
                <span class="ms-2">·</span>
                <span class="ms-2">{{ $paidOn }}</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            @if($invoice)
                <a href="{{ route('invoices.show', $invoice->id) }}" class="btn btn-primary">
                    <i class="bi bi-receipt me-1"></i> View Invoice
                </a>
            @endif
            @if($order)
                <a href="{{ route('orders.show', $order->id) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-cart me-1"></i> View Order
                </a>
            @endif
        </div>
    </div>
@endsection

@section('content')
    @if($stages)
        <x-workflow-rail :stages="$stages" />
    @endif

    {{-- Headline figures --}}
    <div class="detail-card mb-4">
        <div class="detail-card__body">
            <div class="detail-figures">
                <div class="detail-figure">
                    <span class="detail-figure__label">Amount</span>
                    <span class="detail-figure__value detail-figure__value--lead">
                        ${{ number_format($payment->amount, 2) }}
                    </span>
                    <span class="detail-figure__note">Received by {{ $methodLabel }}</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Invoice Total</span>
                    <span class="detail-figure__value">
                        ${{ $invoice ? number_format($invoice->total, 2) : '—' }}
                    </span>
                    <span class="detail-figure__note">
                        {{ $invoice ? ($invoice->invoice_number ?: $invoice->formatted_id) : 'No invoice on record' }}
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Balance Due</span>
                    <span class="detail-figure__value">${{ number_format($balanceDue, 2) }}</span>
                    <span class="detail-figure__note">
                        @if(! $invoice)
                            Nothing to settle
                        @elseif($balanceDue > 0)
                            Still owed on the invoice
                        @else
                            Invoice settled in full
                        @endif
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Paid On</span>
                    <span class="detail-figure__value">
                        {{ optional($payment->payment_date)->format('M d') ?: '—' }}
                    </span>
                    <span class="detail-figure__note">
                        {{ optional($payment->payment_date)->format('Y, h:i A') ?: 'No date recorded' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- What this payment did to the invoice --}}
    <div class="detail-card mb-4">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-flag"></i></span>
            <div>
                <h2 class="detail-card__title">Effect on the Invoice</h2>
                <p class="detail-card__subtitle">Where this payment left the customer's balance</p>
            </div>
        </div>
        <div class="detail-card__body">
            <div class="detail-kv">
                <span class="detail-kv__label">Payment Status</span>
                <span class="detail-kv__value">
                    <span class="badge bg-{{ $statusColour }}">{{ ucfirst($payment->status) }}</span>
                </span>
            </div>
            @if($invoice)
                <div class="detail-kv">
                    <span class="detail-kv__label">Invoice Status</span>
                    <span class="detail-kv__value">
                        <span class="badge bg-{{ $invoice->payment_status === 'paid' ? 'success' : ($invoice->payment_status === 'partially_paid' ? 'info' : 'warning') }}">
                            {{ ucfirst(str_replace('_', ' ', $invoice->payment_status)) }}
                        </span>
                    </span>
                </div>
                <div class="detail-kv">
                    <span class="detail-kv__label">Received in Total</span>
                    <span class="detail-kv__value">
                        ${{ number_format($amountPaid, 2) }} of ${{ number_format($invoice->total, 2) }}
                        <span class="text-muted small d-block">
                            Across {{ $invoicePayments->count() }}
                            {{ Str::plural('payment', $invoicePayments->count()) }}
                        </span>
                    </span>
                </div>
            @endif

            <div class="detail-panel mt-3 mb-0">
                @if(! $invoice)
                    The invoice this payment settled is no longer on record, so there is nothing left
                    to reconcile it against.
                @elseif($invoice->payment_status === 'paid')
                    {{ $invoice->invoice_number ?: $invoice->formatted_id }} is settled in full. Nothing
                    further is owed by {{ optional($customer)->name ?: 'this customer' }}.
                @else
                    ${{ number_format($balanceDue, 2) }} is still owed on
                    {{ $invoice->invoice_number ?: $invoice->formatted_id }}. The remainder is recorded
                    from the invoice.
                @endif
            </div>
        </div>
    </div>

    {{-- Parties and paperwork --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-person"></i></span>
                    <div>
                        <h2 class="detail-card__title">Paid By</h2>
                        <p class="detail-card__subtitle">Who the money came from</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    @if($customer)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Customer</span>
                            <span class="detail-kv__value">{{ $customer->name ?: '—' }}</span>
                        </div>
                        @if($customer->email)
                            <div class="detail-kv">
                                <span class="detail-kv__label">Email</span>
                                <span class="detail-kv__value">{{ $customer->email }}</span>
                            </div>
                        @endif
                        @if($customer->phone)
                            <div class="detail-kv">
                                <span class="detail-kv__label">Phone</span>
                                <span class="detail-kv__value">{{ $customer->phone }}</span>
                            </div>
                        @endif
                        <div class="detail-kv">
                            <span class="detail-kv__label">All Payments</span>
                            <span class="detail-kv__value">
                                <a href="{{ route('payments.index', ['customer_id' => $customer->id]) }}">
                                    From this customer
                                </a>
                            </span>
                        </div>
                    @else
                        <div class="detail-panel mb-0">
                            No customer is on record for this payment.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-credit-card"></i></span>
                    <div>
                        <h2 class="detail-card__title">Payment Details</h2>
                        <p class="detail-card__subtitle">How it was recorded</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Payment</span>
                        <span class="detail-kv__value">{{ $reference }}</span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Method</span>
                        <span class="detail-kv__value">{{ $methodLabel }}</span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Reference</span>
                        <span class="detail-kv__value {{ $payment->reference_number ? '' : 'detail-kv__value--muted' }}">
                            {{ $payment->reference_number ?: 'None given' }}
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Paid At</span>
                        <span class="detail-kv__value">
                            {{ optional($payment->payment_date)->format('M d, Y \a\t h:i A') ?: '—' }}
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Recorded</span>
                        <span class="detail-kv__value detail-kv__value--muted">
                            {{ optional($payment->created_at)->format('M d, Y') ?: '—' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-link-45deg"></i></span>
                    <div>
                        <h2 class="detail-card__title">Related Records</h2>
                        <p class="detail-card__subtitle">What this payment settles</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Invoice</span>
                        <span class="detail-kv__value">
                            @if($invoice)
                                <a href="{{ route('invoices.show', $invoice->id) }}">
                                    {{ $invoice->invoice_number ?: $invoice->formatted_id }}
                                </a>
                                <span class="text-muted small d-block">
                                    ${{ number_format($invoice->total, 2) }} ·
                                    {{ optional($invoice->invoice_date)->format('M d, Y') ?: 'No date' }}
                                </span>
                            @else
                                <span class="text-muted">No invoice on record</span>
                            @endif
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Order</span>
                        <span class="detail-kv__value">
                            @if($order)
                                <a href="{{ route('orders.show', $order->id) }}">
                                    {{ $order->formatted_id ?? '#' . $order->id }}
                                </a>
                                <span class="text-muted small d-block">{{ ucfirst($order->status) }}</span>
                            @else
                                <span class="text-muted">No order on record</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($payment->notes)
        <div class="detail-card">
            <div class="detail-card__header">
                <span class="detail-card__step"><i class="bi bi-sticky"></i></span>
                <div>
                    <h2 class="detail-card__title">Notes</h2>
                    <p class="detail-card__subtitle">Recorded with the payment</p>
                </div>
            </div>
            <div class="detail-card__body">
                <div class="detail-panel mb-0">{{ $payment->notes }}</div>
            </div>
        </div>
    @endif
@endsection

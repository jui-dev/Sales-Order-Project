@extends('layouts.app')

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Invoice Payments</h1>
            <p class="text-muted mb-0">Money received from customers, across every invoice</p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <a href="{{ route('invoices.index', ['status' => 'unpaid']) }}" class="btn btn-outline-primary">
                <i class="bi bi-receipt me-1"></i> Unpaid Invoices
            </a>
        </div>
    </div>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Headline figures --}}
    <div class="detail-card mb-4">
        <div class="detail-card__body">
            <div class="detail-figures">
                <div class="detail-figure">
                    <span class="detail-figure__label">Received</span>
                    <span class="detail-figure__value detail-figure__value--lead">
                        ${{ number_format($statistics['received'], 2) }}
                    </span>
                    <span class="detail-figure__note">Completed payments, all time</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Outstanding</span>
                    <span class="detail-figure__value">${{ number_format($statistics['outstanding'], 2) }}</span>
                    <span class="detail-figure__note">Invoiced but not yet received</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Payments</span>
                    <span class="detail-figure__value">{{ number_format($statistics['count']) }}</span>
                    <span class="detail-figure__note">Recorded against invoices</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Open Invoices</span>
                    <span class="detail-figure__value">{{ number_format($statistics['open']) }}</span>
                    <span class="detail-figure__note">Unpaid or part paid</span>
                </div>
            </div>
        </div>
    </div>

    <x-unified-search
        :searchPlaceholder="'Search by reference, invoice number, or customer...'"
        :filterOptions="$filterOptions"
        :sortOptions="$sortOptions"
        :defaultSort="'payment_date'"
        :defaultDirection="'desc'"
    />

    <div class="detail-card payments-list">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-cash-coin"></i></span>
            <div>
                <h2 class="detail-card__title">Payments</h2>
                <p class="detail-card__subtitle">
                    Payments are recorded on the invoice they settle — open an invoice to take one.
                </p>
            </div>
        </div>
        <div class="detail-card__body detail-card__body--flush">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Payment</th>
                            <th>Invoice</th>
                            <th>Customer</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                            @php
                                $invoice = $payment->invoice;
                                $statusColour = match ($payment->status) {
                                    'completed' => 'success',
                                    'pending' => 'warning',
                                    'failed', 'cancelled' => 'danger',
                                    default => 'secondary',
                                };
                            @endphp
                            <tr>
                                <td data-label="Payment">
                                    <a href="{{ route('payments.show', $payment->id) }}" class="fw-semibold">
                                        PAY-{{ str_pad($payment->id, 4, '0', STR_PAD_LEFT) }}
                                    </a>
                                    <span class="d-block text-muted small">
                                        {{ optional($payment->payment_date)->format('M d, Y') ?: 'No date' }}
                                        @if($payment->reference_number)
                                            · {{ $payment->reference_number }}
                                        @endif
                                    </span>
                                </td>
                                <td data-label="Invoice">
                                    @if($invoice)
                                        <a href="{{ route('invoices.show', $invoice->id) }}">
                                            {{ $invoice->invoice_number ?: $invoice->formatted_id }}
                                        </a>
                                        <span class="d-block text-muted small">
                                            ${{ number_format($invoice->total, 2) }} ·
                                            {{ ucfirst(str_replace('_', ' ', $invoice->payment_status)) }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="Customer">
                                    {{ optional(optional($invoice)->customer)->name ?: '—' }}
                                </td>
                                <td data-label="Method">
                                    {{ \App\Services\PaymentService::methods()[$payment->method] ?? ucfirst(str_replace('_', ' ', $payment->method)) }}
                                </td>
                                <td data-label="Status">
                                    <span class="badge bg-{{ $statusColour }}">{{ ucfirst($payment->status) }}</span>
                                </td>
                                <td data-label="Amount" class="text-end fw-semibold">
                                    ${{ number_format($payment->amount, 2) }}
                                </td>
                                <td data-label="Action" class="text-end">
                                    <div class="d-flex flex-wrap gap-1 justify-content-end">
                                        <a href="{{ route('payments.show', $payment->id) }}"
                                           class="btn btn-sm btn-outline-primary" title="View payment">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if($invoice)
                                            <a href="{{ route('invoices.show', $invoice->id) }}"
                                               class="btn btn-sm btn-outline-secondary" title="View invoice">
                                                <i class="bi bi-receipt"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox display-6 d-block mb-2"></i>
                                    <h5 class="mb-1">No payments found</h5>
                                    <p class="mb-2">
                                        @if(request()->hasAny(['search', 'method', 'status', 'customer_id', 'date_from', 'date_to']))
                                            No payments match the current filters.
                                        @else
                                            Payments are recorded against an invoice. Open an unpaid invoice
                                            to record the first one.
                                        @endif
                                    </p>
                                    @if(request()->hasAny(['search', 'method', 'status', 'customer_id', 'date_from', 'date_to']))
                                        <a href="{{ route('payments.index') }}" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-arrow-clockwise me-1"></i> Clear Filters
                                        </a>
                                    @else
                                        <a href="{{ route('invoices.index', ['status' => 'unpaid']) }}" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-receipt me-1"></i> Unpaid Invoices
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="detail-card__body">
            <x-pagination :paginator="$payments" />
        </div>
    </div>
@endsection

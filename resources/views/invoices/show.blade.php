@extends('layouts.app')

@php
    $items    = $invoice->items ?? collect();
    $payments = $invoice->payments ?? collect();
    $customer = $invoice->customer;
    $order    = $invoice->order;

    $status      = $invoice->payment_status;
    $isPaid      = $status === 'paid';
    $isPartPaid  = $status === 'partially_paid';
    $statusLabel = ucfirst(str_replace('_', ' ', $status));

    $statusColour = match ($status) {
        'paid' => 'success',
        'partially_paid' => 'info',
        default => 'warning',
    };

    $amountPaid = $payments->sum('amount');
    $balanceDue = max(0, $invoice->total - $amountPaid);
    // A settled invoice is settled even if rounding leaves a cent behind, so the
    // recorded status - not the arithmetic - decides whether more can be taken.
    $canRecord  = ! $isPaid && $balanceDue > 0;

    $totalUnits = $items->sum('quantity');
    $invoicedOn = optional($invoice->invoice_date)->format('M d, Y') ?: '—';

    // Payments are the last of Record Order -> Confirm -> Pick -> Invoice -> Payment.
    // order_id is a constrained foreign key, so an invoice without an order only
    // happens if the row was written by hand.
    $stages = $order ? \App\Support\OrderWorkflow::forInvoice($invoice) : null;
@endphp

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Invoice {{ $invoice->invoice_number ?: $invoice->formatted_id }}</h1>
            <p class="text-muted mb-0">
                <span class="badge bg-{{ $statusColour }}">{{ $statusLabel }}</span>
                <span class="ms-2">Issued {{ $invoicedOn }}</span>
                <span class="ms-2">·</span>
                <span class="ms-2">{{ optional($customer)->name ?: 'Unknown customer' }}</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            @if($canRecord)
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                    <i class="bi bi-credit-card me-1"></i> Record Payment
                </button>
            @endif
            <a href="{{ route('invoices.download', $invoice) }}"
               class="btn btn-{{ $canRecord ? 'outline-secondary' : 'primary' }}">
                <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
            </a>
            @if($order)
                <a href="{{ route('orders.show', $order->id) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-cart me-1"></i> View Order
                </a>
            @endif
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

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Where this invoice sits in the sales workflow --}}
    @if($stages)
        <x-workflow-rail :stages="$stages" />
    @endif

    {{-- Headline figures --}}
    <div class="detail-card mb-4">
        <div class="detail-card__body">
            <div class="detail-figures">
                <div class="detail-figure">
                    <span class="detail-figure__label">Invoice Total</span>
                    <span class="detail-figure__value detail-figure__value--lead">
                        ${{ number_format($invoice->total, 2) }}
                    </span>
                    <span class="detail-figure__note">
                        Charged to {{ optional($customer)->name ?: 'the customer' }}
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Amount Paid</span>
                    <span class="detail-figure__value">${{ number_format($amountPaid, 2) }}</span>
                    <span class="detail-figure__note">
                        {{ $payments->count() }} {{ Str::plural('payment', $payments->count()) }} recorded
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Balance Due</span>
                    <span class="detail-figure__value">${{ number_format($balanceDue, 2) }}</span>
                    <span class="detail-figure__note">
                        @if($isPaid)
                            Settled in full
                        @elseif($isPartPaid)
                            Still owed by the customer
                        @else
                            Nothing received yet
                        @endif
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Units Invoiced</span>
                    <span class="detail-figure__value">{{ number_format($totalUnits) }}</span>
                    <span class="detail-figure__note">
                        Across {{ $items->count() }} {{ Str::plural('product', $items->count()) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Status and what the page can do about it --}}
    <div class="detail-card mb-4">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-flag"></i></span>
            <div>
                <h2 class="detail-card__title">Payment Status</h2>
                <p class="detail-card__subtitle">What has been received against this invoice, and what is left</p>
            </div>
        </div>
        <div class="detail-card__body">
            <div class="detail-kv">
                <span class="detail-kv__label">Current Status</span>
                <span class="detail-kv__value">
                    <span class="badge bg-{{ $statusColour }}">{{ $statusLabel }}</span>
                </span>
            </div>
            @if($invoice->paid_at)
                <div class="detail-kv">
                    <span class="detail-kv__label">Settled On</span>
                    <span class="detail-kv__value">{{ $invoice->paid_at->format('M d, Y \a\t h:i A') }}</span>
                </div>
            @endif

            @if(! $isPaid)
                {{-- Progress towards settlement, so a part-paid invoice reads at a glance --}}
                <div class="progress mt-3" style="height: 0.5rem;" role="progressbar"
                     aria-valuenow="{{ (int) ($invoice->total > 0 ? ($amountPaid / $invoice->total) * 100 : 0) }}"
                     aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-{{ $statusColour }}"
                         style="width: {{ $invoice->total > 0 ? min(100, ($amountPaid / $invoice->total) * 100) : 0 }}%"></div>
                </div>
            @endif

            <div class="detail-panel mt-3 mb-0">
                @if($isPaid)
                    This invoice has been paid in full. Payments recorded against it are listed in
                    <a href="{{ route('payments.index') }}">Invoice Payments</a>.
                @elseif($isPartPaid)
                    ${{ number_format($amountPaid, 2) }} of ${{ number_format($invoice->total, 2) }} has been
                    received. Recording the remaining ${{ number_format($balanceDue, 2) }} marks this invoice
                    as paid.
                @else
                    Nothing has been received yet. Use <strong>Record Payment</strong> to enter what the
                    customer has paid — a smaller amount marks the invoice partially paid and leaves the
                    rest owing.
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
                        <h2 class="detail-card__title">Billed To</h2>
                        <p class="detail-card__subtitle">Who owes this invoice</p>
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
                        @if($customer->address)
                            <div class="detail-kv">
                                <span class="detail-kv__label">Address</span>
                                <span class="detail-kv__value detail-kv__value--muted">{{ $customer->address }}</span>
                            </div>
                        @endif
                    @else
                        <div class="detail-panel mb-0">
                            The customer this invoice was raised for is no longer on record.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-receipt"></i></span>
                    <div>
                        <h2 class="detail-card__title">Invoice Details</h2>
                        <p class="detail-card__subtitle">How the total was arrived at</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Invoice No.</span>
                        <span class="detail-kv__value">{{ $invoice->invoice_number ?: $invoice->formatted_id }}</span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Invoice Date</span>
                        <span class="detail-kv__value">{{ $invoicedOn }}</span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Subtotal</span>
                        <span class="detail-kv__value">${{ number_format($invoice->subtotal, 2) }}</span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Tax</span>
                        <span class="detail-kv__value">${{ number_format($invoice->tax, 2) }}</span>
                    </div>
                    @if($invoice->discount > 0)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Discount</span>
                            <span class="detail-kv__value">-${{ number_format($invoice->discount, 2) }}</span>
                        </div>
                    @endif
                    <div class="detail-kv">
                        <span class="detail-kv__label">Grand Total</span>
                        <span class="detail-kv__value fw-bold">${{ number_format($invoice->total, 2) }}</span>
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
                        <p class="detail-card__subtitle">Where this invoice came from</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Order</span>
                        <span class="detail-kv__value">
                            @if($order)
                                <a href="{{ route('orders.show', $order->id) }}">
                                    {{ $order->formatted_id ?? '#' . $order->id }}
                                </a>
                                <span class="text-muted small d-block">
                                    {{ ucfirst($order->status) }} ·
                                    {{ optional($order->order_date)->format('M d, Y') ?: 'No date set' }}
                                </span>
                            @else
                                <span class="text-muted">No order on record</span>
                            @endif
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Payments</span>
                        <span class="detail-kv__value">
                            @if($payments->isNotEmpty())
                                <a href="{{ route('payments.index', ['customer_id' => $invoice->customer_id]) }}">
                                    {{ $payments->count() }} recorded
                                </a>
                                <span class="text-muted small d-block">
                                    ${{ number_format($amountPaid, 2) }} received
                                </span>
                            @else
                                <span class="text-muted">None recorded yet</span>
                            @endif
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Raised</span>
                        <span class="detail-kv__value detail-kv__value--muted">
                            {{ optional($invoice->created_at)->format('M d, Y') ?: '—' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Line items --}}
    <div class="detail-card invoice-items mb-4">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-list-ul"></i></span>
            <div>
                <h2 class="detail-card__title">Invoiced Products</h2>
                <p class="detail-card__subtitle">
                    What {{ optional($customer)->name ?: 'the customer' }} is being charged for.
                </p>
            </div>
        </div>
        <div class="detail-card__body detail-card__body--flush">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 3rem;">#</th>
                            <th>Description</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $index => $item)
                            <tr>
                                <td data-label="#">{{ $index + 1 }}</td>
                                <td data-label="Description">
                                    <strong>{{ $item->description ?: optional($item->product)->name ?: 'Unknown product' }}</strong>
                                    @if(optional($item->product)->sku)
                                        <span class="d-block text-muted small">SKU {{ $item->product->sku }}</span>
                                    @endif
                                </td>
                                <td data-label="Quantity" class="text-end">{{ number_format($item->quantity) }}</td>
                                <td data-label="Unit Price" class="text-end">
                                    ${{ number_format($item->unit_price, 2) }}
                                </td>
                                <td data-label="Line Total" class="text-end fw-semibold">
                                    ${{ number_format($item->total, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No items on this invoice.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($items->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="2" class="text-muted">
                                    Total across {{ $items->count() }}
                                    {{ Str::plural('product', $items->count()) }}
                                </td>
                                <td data-label="Total quantity" class="text-end fw-semibold">
                                    {{ number_format($totalUnits) }}
                                </td>
                                <td></td>
                                <td data-label="Invoice total" class="text-end fw-bold">
                                    ${{ number_format($invoice->total, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- A short account of what has been received. The full ledger, across every
         invoice, lives under Invoice Payments. --}}
    <div class="detail-card invoice-payments">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-cash-coin"></i></span>
            <div>
                <h2 class="detail-card__title">Payments Received</h2>
                <p class="detail-card__subtitle">
                    Money taken against this invoice.
                    <a href="{{ route('payments.index') }}">See all payments</a>.
                </p>
            </div>
        </div>
        <div class="detail-card__body detail-card__body--flush">
            @if($payments->isEmpty())
                <div class="detail-card__body">
                    <div class="detail-panel mb-0">
                        Nothing has been received against this invoice yet.
                        @if($canRecord)
                            Use <strong>Record Payment</strong> above to enter one.
                        @endif
                    </div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 3rem;">#</th>
                                <th>Paid On</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($payments as $index => $payment)
                                <tr>
                                    <td data-label="#">{{ $index + 1 }}</td>
                                    <td data-label="Paid On">
                                        <a href="{{ route('payments.show', $payment->id) }}">
                                            {{ optional($payment->payment_date)->format('M d, Y') ?: 'No date' }}
                                        </a>
                                        <span class="d-block text-muted small">
                                            {{ optional($payment->payment_date)->format('h:i A') }}
                                        </span>
                                    </td>
                                    <td data-label="Method">
                                        {{ \App\Services\PaymentService::methods()[$payment->method] ?? ucfirst(str_replace('_', ' ', $payment->method)) }}
                                    </td>
                                    <td data-label="Reference" class="text-muted">
                                        {{ $payment->reference_number ?: '—' }}
                                    </td>
                                    <td data-label="Amount" class="text-end fw-semibold">
                                        ${{ number_format($payment->amount, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-muted">
                                    {{ $payments->count() }} {{ Str::plural('payment', $payments->count()) }} received
                                </td>
                                <td data-label="Balance due" class="text-end text-muted">
                                    ${{ number_format($balanceDue, 2) }} due
                                </td>
                                <td data-label="Total received" class="text-end fw-bold">
                                    ${{ number_format($amountPaid, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if($canRecord)
        {{-- Taking money is done from the invoice because the balance is only
             known here; the resulting payments are browsed under Invoice Payments. --}}
        <div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-labelledby="recordPaymentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <form action="{{ route('invoices.pay', $invoice) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="recordPaymentModalLabel">Record a Payment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-{{ $statusColour }}">{{ $statusLabel }}</span>
                                <i class="bi bi-arrow-right text-muted"></i>
                                <span class="badge bg-success">Paid</span>
                                <span class="text-muted small">if the full balance is entered</span>
                            </div>

                            <p class="mb-3">
                                <strong>${{ number_format($balanceDue, 2) }}</strong> is still owed on
                                {{ $invoice->invoice_number ?: $invoice->formatted_id }} by
                                {{ optional($customer)->name ?: 'this customer' }}. Recording a payment
                                marks the invoice paid when the balance is settled in full, or partially
                                paid when it is not, and adds the payment to Invoice Payments.
                            </p>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="amount" class="form-label">
                                        Amount <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0.01" max="{{ $balanceDue }}"
                                               class="form-control @error('amount') is-invalid @enderror"
                                               id="amount" name="amount"
                                               value="{{ old('amount', number_format($balanceDue, 2, '.', '')) }}" required>
                                    </div>
                                    <div class="form-text">Balance due: ${{ number_format($balanceDue, 2) }}</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="method" class="form-label">
                                        Method <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="method" name="method" required>
                                        @foreach(\App\Services\PaymentService::methods() as $value => $label)
                                            <option value="{{ $value }}" {{ old('method') === $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="reference" class="form-label">Reference</label>
                                    <input type="text" class="form-control" id="reference" name="reference"
                                           value="{{ old('reference') }}"
                                           placeholder="Transaction ID, cheque no.">
                                </div>
                                <div class="col-12">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2"
                                              placeholder="Anything worth recording about this payment">{{ old('notes') }}</textarea>
                                </div>
                            </div>

                            <div class="detail-panel mt-3 mb-0">
                                <span class="d-block mb-1">
                                    <strong>What does not happen:</strong> nothing moves in the warehouse and
                                    the order is untouched — the goods were dispatched when the picking list
                                    was completed.
                                </span>
                                <span class="text-muted small">
                                    A recorded payment cannot be edited or removed from this page.
                                </span>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i> Record Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@if($canRecord && $errors->any())
    @push('scripts')
    <script>
        // A rejected amount comes back as a page load with the modal closed, so
        // reopen it - the corrected value is repopulated from old() inside it.
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('recordPaymentModal');
            if (modal) {
                new bootstrap.Modal(modal).show();
            }
        });
    </script>
    @endpush
@endif

@push('styles')
<style>
    /* Totals rows: keep them readable once the tables collapse to cards on mobile */
    @media (max-width: 768px) {
        .invoice-items tfoot tr,
        .invoice-payments tfoot tr {
            display: block;
            border-top: 1px solid #dee2e6;
        }

        .invoice-items tfoot td,
        .invoice-payments tfoot td {
            display: block;
            text-align: right;
            padding: 0.35rem 0.75rem;
        }

        .invoice-items tfoot td:empty,
        .invoice-payments tfoot td:empty {
            display: none;
        }

        /* Mirror the tbody label treatment custom.css applies below 768px */
        .invoice-items tfoot td[data-label]::before,
        .invoice-payments tfoot td[data-label]::before {
            content: attr(data-label);
            float: left;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.85em;
        }
    }
</style>
@endpush

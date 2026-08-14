@extends('layouts.app')

@php
    $bill    = $supplierBill;
    $items   = $bill->items ?? collect();
    $grn     = $bill->grn;
    $supply  = $grn?->supply;
    $payment = $bill->payment;

    $isPaid    = $bill->isPaid();
    $awaitsPay = $bill->status === 'posted' && ! $isPaid;

    $totalUnits    = $items->sum('quantity');
    $totalProducts = $items->count();

    // The payment journal hangs off the payment record as well as the bill;
    // prefer whichever is loaded.
    $paymentJournal = $payment?->paymentJournal ?? $bill->paymentJournal;

    $stages = \App\Support\SuppliesWorkflow::forBill(
        $bill,
        \App\Support\SuppliesWorkflow::STAGE_PAYMENT,
    );
@endphp

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Payment · {{ $bill->formatted_id ?? '#' . $bill->id }}</h1>
            <p class="text-muted mb-0">
                <span class="badge bg-{{ $isPaid ? 'success' : 'warning' }}">
                    {{ $isPaid ? 'Paid' : 'Unpaid' }}
                </span>
                <span class="ms-2">${{ number_format($bill->total_amount, 2) }}</span>
                <span class="ms-2">·</span>
                <span class="ms-2">{{ $bill->vendor->name ?: 'Unknown vendor' }}</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print
            </button>
            @if($awaitsPay && $payment)
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#markAsPaidModal">
                    <i class="bi bi-credit-card me-1"></i> Mark as Paid
                </button>
            @endif
        </div>
    </div>
@endsection

@section('content')
    {{-- Where this payment sits in the purchase workflow --}}
    <x-workflow-rail :stages="$stages" />

    {{-- Headline figures --}}
    <div class="detail-card mb-4">
        <div class="detail-card__body">
            <div class="detail-figures">
                <div class="detail-figure">
                    <span class="detail-figure__label">{{ $isPaid ? 'Amount Paid' : 'Amount Due' }}</span>
                    <span class="detail-figure__value detail-figure__value--lead">
                        ${{ number_format($bill->total_amount, 2) }}
                    </span>
                    <span class="detail-figure__note">
                        To {{ $bill->vendor->name ?: 'vendor' }}
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Status</span>
                    <span class="detail-figure__value">{{ $isPaid ? 'Paid' : 'Unpaid' }}</span>
                    <span class="detail-figure__note">
                        {{ $isPaid ? 'Settled in full' : ($awaitsPay ? 'Awaiting payment' : 'Bill not posted') }}
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Bill Posted</span>
                    <span class="detail-figure__value">
                        {{ optional($bill->posted_at)->format('M d') ?: '—' }}
                    </span>
                    <span class="detail-figure__note">
                        {{ optional($bill->posted_at)->format('Y') ?: 'Not posted' }}
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Paid</span>
                    <span class="detail-figure__value">
                        {{ optional($payment?->paid_at)->format('M d') ?: '—' }}
                    </span>
                    <span class="detail-figure__note">
                        {{ optional($payment?->paid_at)->format('Y') ?: 'Not yet paid' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-truck"></i></span>
                    <div>
                        <h2 class="detail-card__title">Vendor</h2>
                        <p class="detail-card__subtitle">Who is being paid</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Name</span>
                        <span class="detail-kv__value">{{ $bill->vendor->name ?: '—' }}</span>
                    </div>
                    @if($bill->vendor->contact_person)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Contact</span>
                            <span class="detail-kv__value">{{ $bill->vendor->contact_person }}</span>
                        </div>
                    @endif
                    @if($bill->vendor->phone)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Phone</span>
                            <span class="detail-kv__value">{{ $bill->vendor->phone }}</span>
                        </div>
                    @endif
                    @if($bill->vendor->address)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Address</span>
                            <span class="detail-kv__value detail-kv__value--muted">{{ $bill->vendor->address }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-credit-card"></i></span>
                    <div>
                        <h2 class="detail-card__title">Payment Details</h2>
                        <p class="detail-card__subtitle">Timeline and source documents</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Bill Date</span>
                        <span class="detail-kv__value">{{ optional($bill->bill_date)->format('M d, Y') ?: '—' }}</span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Posted At</span>
                        <span class="detail-kv__value detail-kv__value--muted">
                            {{ optional($bill->posted_at)->format('M d, Y H:i') ?: 'Not posted' }}
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Paid At</span>
                        <span class="detail-kv__value detail-kv__value--muted">
                            {{ optional($payment?->paid_at)->format('M d, Y H:i') ?: 'Not paid' }}
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Source Documents</span>
                        <span class="detail-kv__value">
                            @if($grn)
                                <a href="{{ route('grns.show', $grn->id) }}">{{ $grn->formatted_id ?? '#' . $grn->id }}</a>
                            @endif
                            @if($grn && $supply)
                                <span class="text-muted">·</span>
                            @endif
                            @if($supply)
                                <a href="{{ route('supplies.show', $supply->id) }}">{{ $supply->formatted_id ?? '#' . $supply->id }}</a>
                            @endif
                            @if(! $grn && ! $supply)
                                <span class="text-muted">—</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Accounting: the payment entry leads, the purchase entry gives context --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <x-journal-ledger
                :entry="$paymentJournal"
                title="Payment Journal"
                subtitle="Debits Accounts Payable and credits Cash"
                icon="bi-journal-arrow-up"
                empty="Created when this bill is marked as paid." />
        </div>
        <div class="col-lg-6">
            <x-journal-ledger
                :entry="$bill->purchaseJournal"
                title="Purchase Journal"
                subtitle="The liability being settled"
                icon="bi-journal-arrow-down"
                empty="Created when this bill is posted." />
        </div>
    </div>

    {{-- What is being paid for --}}
    <div class="detail-card payment-items">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-list-ul"></i></span>
            <div>
                <h2 class="detail-card__title">What This Pays For</h2>
                <p class="detail-card__subtitle">
                    {{ $totalUnits }} {{ Str::plural('unit', $totalUnits) }} received from
                    {{ $bill->vendor->name ?: 'the vendor' }}
                </p>
            </div>
        </div>
        <div class="detail-card__body detail-card__body--flush">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td data-label="Product">
                                    <strong>{{ $item->product->name ?: 'Unknown product' }}</strong>
                                    <span class="d-block text-muted small">SKU {{ $item->product->sku ?: 'N/A' }}</span>
                                </td>
                                <td data-label="Quantity" class="text-end">{{ number_format($item->quantity) }}</td>
                                <td data-label="Unit Cost" class="text-end">${{ number_format($item->unit_cost, 2) }}</td>
                                <td data-label="Subtotal" class="text-end fw-semibold">
                                    ${{ number_format($item->subtotal, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No items on this bill.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($items->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td class="text-muted">
                                    Total across {{ $totalProducts }} {{ Str::plural('product', $totalProducts) }}
                                </td>
                                <td data-label="Total quantity" class="text-end fw-semibold">
                                    {{ number_format($totalUnits) }}
                                </td>
                                <td></td>
                                <td data-label="Total amount" class="text-end fw-bold">
                                    ${{ number_format($bill->total_amount, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

@if($awaitsPay && $payment)
    {{-- Settling the bill writes to the ledger, so spell out the consequences --}}
    <div class="modal fade" id="markAsPaidModal" tabindex="-1" aria-labelledby="markAsPaidModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="markAsPaidModalLabel">Mark Bill as Paid</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge bg-warning">Unpaid</span>
                        <i class="bi bi-arrow-right text-muted"></i>
                        <span class="badge bg-success">Paid</span>
                    </div>

                    <p class="mb-3">
                        This records that ${{ number_format($bill->total_amount, 2) }} has been paid to
                        {{ $bill->vendor->name ?: 'the vendor' }} against
                        {{ $bill->formatted_id ?? '#' . $bill->id }}. Three things happen at once:
                    </p>

                    <ul class="list-unstyled mb-3">
                        <li class="d-flex gap-3 mb-3">
                            <span class="detail-card__step flex-shrink-0"><i class="bi bi-journal-arrow-up"></i></span>
                            <span>
                                <strong>A payment journal entry is drafted.</strong>
                                Accounts Payable (2000) is debited ${{ number_format($bill->total_amount, 2) }}
                                and Cash (1000) is credited the same, clearing the liability and recording
                                the cash leaving the business. The entry is created with <em>draft</em>
                                status for accounting to review.
                            </span>
                        </li>
                        <li class="d-flex gap-3 mb-3">
                            <span class="detail-card__step flex-shrink-0"><i class="bi bi-credit-card"></i></span>
                            <span>
                                <strong>The payment record is settled.</strong>
                                {{ $payment->formatted_id ?? 'The payment' }} moves from unpaid to paid and
                                is stamped with today's date. Nothing further is owed on this bill.
                            </span>
                        </li>
                        <li class="d-flex gap-3 mb-0">
                            <span class="detail-card__step flex-shrink-0"><i class="bi bi-flag"></i></span>
                            <span>
                                <strong>The purchase is complete.</strong>
                                Payment is the last stage of Record Supply → Receive Goods → Supplier Bill
                                → Payment, so the workflow closes out.
                            </span>
                        </li>
                    </ul>

                    <div class="detail-panel mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        This records a payment that has already been made — it does not move any money
                        itself. It cannot be undone from this page, so confirm the vendor has actually
                        been paid ${{ number_format($bill->total_amount, 2) }} before continuing.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="{{ route('supplier-bills.pay', $bill) }}" method="POST" id="markAsPaidForm">
                        @csrf
                        <button type="submit" class="btn btn-primary" id="markAsPaidBtn">
                            <i class="bi bi-credit-card me-1"></i> Mark as Paid
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let isProcessing = false;

    // Handle Mark as Paid button - prevent double submission only
    const markAsPaidForm = document.getElementById('markAsPaidForm');
    const markAsPaidBtn = document.getElementById('markAsPaidBtn');

    if (markAsPaidForm && markAsPaidBtn) {
        markAsPaidForm.addEventListener('submit', function(e) {
            // Only prevent double submission, no visual feedback
            if (isProcessing) {
                e.preventDefault();
                return false;
            }

            // Set processing flag to prevent double clicks
            isProcessing = true;

            // Disable the button to prevent multiple clicks
            markAsPaidBtn.disabled = true;

            // Allow form to submit normally without any processing overlay
        });
    }
});
</script>
@endpush
@endsection

@push('styles')
<style>
    /* Totals row: keep it readable once the table collapses to cards on mobile */
    @media (max-width: 768px) {
        .payment-items tfoot tr {
            display: block;
            border-top: 1px solid #dee2e6;
        }

        .payment-items tfoot td {
            display: block;
            text-align: right;
            padding: 0.35rem 0.75rem;
        }

        .payment-items tfoot td:empty {
            display: none;
        }

        /* Mirror the tbody label treatment custom.css applies below 768px */
        .payment-items tfoot td[data-label]::before {
            content: attr(data-label);
            float: left;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.85em;
        }
    }
</style>
@endpush

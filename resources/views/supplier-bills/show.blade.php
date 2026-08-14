@extends('layouts.app')

@php
    $bill    = $supplierBill;
    $items   = $bill->items ?? collect();
    $grn     = $bill->grn;
    $supply  = $grn?->supply;
    $payment = $bill->payment;

    $isDraft    = $bill->status === 'draft';
    $isPaid     = $bill->isPaid();
    $awaitsPay  = $bill->status === 'posted' && ! $isPaid;

    $totalUnits    = $items->sum('quantity');
    $totalProducts = $items->count();

    $stages = \App\Support\SuppliesWorkflow::forBill($bill);
@endphp

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Supplier Bill {{ $bill->formatted_id ?? '#' . $bill->id }}</h1>
            <p class="text-muted mb-0">
                <span class="badge bg-{{ $isDraft ? 'secondary' : 'success' }}">{{ ucfirst($bill->status) }}</span>
                @if($payment)
                    <span class="badge bg-{{ $isPaid ? 'success' : 'warning' }} ms-1">
                        {{ ucfirst($payment->payment_status) }}
                    </span>
                @endif
                <span class="ms-2">Billed {{ optional($bill->bill_date)->format('M d, Y') ?: '—' }}</span>
                <span class="ms-2">·</span>
                <span class="ms-2">{{ $bill->vendor->name ?: 'Unknown vendor' }}</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <a href="{{ route('supplier-bills.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print
            </button>
            @if($isDraft)
                <form action="{{ route('supplier-bills.post', $bill) }}" method="POST" class="d-inline" id="postSupplierBillForm">
                    @csrf
                    <button type="submit" class="btn btn-primary" id="postSupplierBillBtn">
                        <i class="bi bi-check-circle me-1"></i> Post Supplier Bill
                    </button>
                </form>
            @elseif($bill->status === 'posted')
                <a href="{{ route('supplier-bills.payment-info', $bill) }}" class="btn btn-outline-primary">
                    <i class="bi bi-credit-card me-1"></i> Payment Information
                </a>
                @if($payment && $payment->payment_status === 'unpaid')
                    <form action="{{ route('supplier-bills.pay', $bill) }}" method="POST" class="d-inline" id="markAsPaidForm">
                        @csrf
                        <button type="submit" class="btn btn-primary" id="markAsPaidBtn">
                            <i class="bi bi-cash-coin me-1"></i> Mark as Paid
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>
@endsection

@section('content')
    {{-- Where this bill sits in the purchase workflow --}}
    <x-workflow-rail :stages="$stages" />

    {{-- Headline figures --}}
    <div class="detail-card mb-4">
        <div class="detail-card__body">
            <div class="detail-figures">
                <div class="detail-figure">
                    <span class="detail-figure__label">Total Amount</span>
                    <span class="detail-figure__value detail-figure__value--lead">
                        ${{ number_format($bill->total_amount, 2) }}
                    </span>
                    <span class="detail-figure__note">
                        {{ $isPaid ? 'Settled' : ($isDraft ? 'Not yet owed' : 'Owed to vendor') }}
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Units Billed</span>
                    <span class="detail-figure__value">{{ number_format($totalUnits) }}</span>
                    <span class="detail-figure__note">Across {{ $totalProducts }} {{ Str::plural('product', $totalProducts) }}</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Posted</span>
                    <span class="detail-figure__value">
                        {{ optional($bill->posted_at)->format('M d') ?: '—' }}
                    </span>
                    <span class="detail-figure__note">
                        {{ optional($bill->posted_at)->format('Y') ?: 'Awaiting posting' }}
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Paid</span>
                    <span class="detail-figure__value">
                        {{ optional($payment?->paid_at)->format('M d') ?: '—' }}
                    </span>
                    <span class="detail-figure__note">
                        {{ optional($payment?->paid_at)->format('Y') ?: ($awaitsPay ? 'Payment pending' : 'Not yet due') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Parties and paperwork --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
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

        <div class="col-lg-4">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-receipt"></i></span>
                    <div>
                        <h2 class="detail-card__title">Bill Details</h2>
                        <p class="detail-card__subtitle">Dates and description</p>
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
                    @if($bill->description)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Description</span>
                            <span class="detail-kv__value detail-kv__value--muted">{{ $bill->description }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-link-45deg"></i></span>
                    <div>
                        <h2 class="detail-card__title">Related Records</h2>
                        <p class="detail-card__subtitle">Where this bill came from</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Goods Received Note</span>
                        <span class="detail-kv__value">
                            @if($grn)
                                <a href="{{ route('grns.show', $grn->id) }}">
                                    {{ $grn->formatted_id ?? '#' . $grn->id }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Supply</span>
                        <span class="detail-kv__value">
                            @if($supply)
                                <a href="{{ route('supplies.show', $supply->id) }}">
                                    {{ $supply->formatted_id ?? '#' . $supply->id }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Purchase Journal</span>
                        <span class="detail-kv__value">
                            @if($bill->purchaseJournal)
                                <a href="{{ route('journal-entries.show', $bill->purchaseJournal) }}">
                                    {{ $bill->purchaseJournal->formatted_id }}
                                </a>
                            @else
                                <span class="text-muted">Created when posted</span>
                            @endif
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Payment Journal</span>
                        <span class="detail-kv__value">
                            @if($bill->paymentJournal)
                                <a href="{{ route('journal-entries.show', $bill->paymentJournal) }}">
                                    {{ $bill->paymentJournal->formatted_id }}
                                </a>
                            @else
                                <span class="text-muted">Created when paid</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Line items --}}
    <div class="detail-card bill-items mb-4">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-box-seam"></i></span>
            <div>
                <h2 class="detail-card__title">Bill Items</h2>
                <p class="detail-card__subtitle">What the vendor is charging for</p>
            </div>
        </div>
        <div class="detail-card__body detail-card__body--flush">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 3rem;">#</th>
                            <th>Product</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $index => $item)
                            <tr>
                                <td data-label="#">{{ $index + 1 }}</td>
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
                                <td colspan="5" class="text-center text-muted py-4">No items on this bill.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($items->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="2" class="text-muted">
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

    {{-- Accounting --}}
    <div class="row g-4">
        <div class="col-lg-6">
            <x-journal-ledger
                :entry="$bill->purchaseJournal"
                title="Purchase Journal"
                subtitle="Records the stock received and what is owed"
                icon="bi-journal-arrow-down"
                empty="Created when this bill is posted." />
        </div>
        <div class="col-lg-6">
            <x-journal-ledger
                :entry="$bill->paymentJournal"
                title="Payment Journal"
                subtitle="Records the cash leaving the business"
                icon="bi-journal-arrow-up"
                empty="Created when this bill is marked as paid." />
        </div>
    </div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let isProcessing = false;

    // Handle Post Supplier Bill button protection
    const postForm = document.getElementById('postSupplierBillForm');
    const postBtn = document.getElementById('postSupplierBillBtn');

    if (postForm && postBtn) {
        postForm.addEventListener('submit', function(e) {
            if (isProcessing) {
                e.preventDefault();
                return false;
            }

            isProcessing = true;

            // Disable the button immediately
            postBtn.disabled = true;
            postBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Posting...';
            postBtn.classList.remove('btn-primary');
            postBtn.classList.add('btn-secondary');

            // Prevent multiple submissions
            postForm.style.pointerEvents = 'none';

            // Show processing indicator
            showProcessingIndicator('Posting supplier bill...');
        });
    }

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

    // Function to show processing indicator
    function showProcessingIndicator(message) {
        // Create overlay if it doesn't exist
        let overlay = document.getElementById('processingOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'processingOverlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 9999;
                display: flex;
                justify-content: center;
                align-items: center;
            `;

            const content = document.createElement('div');
            content.style.cssText = `
                background: white;
                padding: 2rem;
                border-radius: 8px;
                text-align: center;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            `;

            content.innerHTML = `
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mb-0">${message}</p>
                <small class="text-muted">Please wait, do not refresh the page...</small>
            `;

            overlay.appendChild(content);
            document.body.appendChild(overlay);
        }

        overlay.style.display = 'flex';
    }
});
</script>
@endpush
@endsection

@push('styles')
<style>
    /* Totals row: keep it readable once the table collapses to cards on mobile */
    @media (max-width: 768px) {
        .bill-items tfoot tr {
            display: block;
            border-top: 1px solid #dee2e6;
        }

        .bill-items tfoot td {
            display: block;
            text-align: right;
            padding: 0.35rem 0.75rem;
        }

        .bill-items tfoot td:empty {
            display: none;
        }

        /* Mirror the tbody label treatment custom.css applies below 768px */
        .bill-items tfoot td[data-label]::before {
            content: attr(data-label);
            float: left;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.85em;
        }
    }
</style>
@endpush

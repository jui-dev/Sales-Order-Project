@extends('layouts.app')

@php
    $items = $supply->items ?? collect();
    $grn   = $supply->grn;
    $bill  = $grn?->supplierBill;

    $isPending  = $supply->status !== 'completed';
    $totalUnits = $items->sum('quantity');
    $totalValue = $items->sum(fn ($item) => $item->subtotal ?? ($item->quantity * $item->unit_cost));
    $recordedOn = optional($supply->supply_date)->format('M d, Y') ?: '—';

    // Where this supply sits in Record Supply -> Receive Goods -> Supplier Bill -> Payment.
    $stages = \App\Support\SuppliesWorkflow::forSupply($supply);
@endphp

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Supply {{ $supply->formatted_id ?? '#' . $supply->id }}</h1>
            <p class="text-muted mb-0">
                <span class="badge bg-{{ $isPending ? 'warning' : 'success' }}">{{ ucfirst($supply->status) }}</span>
                <span class="ms-2">Recorded {{ $recordedOn }}</span>
                <span class="ms-2">·</span>
                <span class="ms-2">{{ $supply->vendor->name ?: 'Unknown vendor' }}</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            @if($isPending)
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#markCompletedModal">
                    <i class="bi bi-check-circle me-1"></i> Mark as Completed
                </button>
            @elseif($grn)
                <a href="{{ route('grns.show', $grn->id) }}" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-down me-1"></i> Go to Receiving
                </a>
            @endif
        </div>
    </div>
@endsection

@section('content')
    {{-- Where this supply sits in the purchase workflow --}}
    <x-workflow-rail :stages="$stages" />

    {{-- Headline figures --}}
    <div class="detail-card mb-4">
        <div class="detail-card__body">
            <div class="detail-figures">
                <div class="detail-figure">
                    <span class="detail-figure__label">Total Cost</span>
                    <span class="detail-figure__value detail-figure__value--lead">${{ number_format($supply->total_cost, 2) }}</span>
                    <span class="detail-figure__note">At recorded cost</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Units Ordered</span>
                    <span class="detail-figure__value">{{ number_format($totalUnits) }}</span>
                    <span class="detail-figure__note">
                        {{ $isPending ? 'Not yet in stock' : 'Awaiting GRN posting' }}
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Products</span>
                    <span class="detail-figure__value">{{ number_format($items->count()) }}</span>
                    <span class="detail-figure__note">Distinct lines</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Recorded</span>
                    <span class="detail-figure__value">{{ optional($supply->supply_date)->format('M d') ?: '—' }}</span>
                    <span class="detail-figure__note">{{ optional($supply->supply_date)->format('Y') ?: 'No date set' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Status and the one action this page owns --}}
    <div class="detail-card mb-4">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-flag"></i></span>
            <div>
                <h2 class="detail-card__title">Status</h2>
                <p class="detail-card__subtitle">Where this supply has reached, and what happens next</p>
            </div>
        </div>
        <div class="detail-card__body">
            <div class="detail-kv">
                <span class="detail-kv__label">Current Status</span>
                <span class="detail-kv__value">
                    <span class="badge bg-{{ $isPending ? 'warning' : 'success' }}">{{ ucfirst($supply->status) }}</span>
                </span>
            </div>

            <div class="detail-panel mt-3 mb-0">
                @if($isPending)
                    Nothing has entered stock yet. Mark this supply completed to raise a draft goods
                    received note for the receiving team.
                @else
                    A goods received note has been raised. The stock reaches
                    {{ $supply->warehouse->name ?: 'the warehouse' }} once that GRN is posted.
                @endif
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
                        <p class="detail-card__subtitle">Who the goods are bought from</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Name</span>
                        <span class="detail-kv__value">{{ $supply->vendor->name ?: '—' }}</span>
                    </div>
                    @if($supply->vendor->contact_person)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Contact</span>
                            <span class="detail-kv__value">{{ $supply->vendor->contact_person }}</span>
                        </div>
                    @endif
                    @if($supply->vendor->phone)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Phone</span>
                            <span class="detail-kv__value">{{ $supply->vendor->phone }}</span>
                        </div>
                    @endif
                    @if($supply->vendor->address)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Address</span>
                            <span class="detail-kv__value detail-kv__value--muted">{{ $supply->vendor->address }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-building"></i></span>
                    <div>
                        <h2 class="detail-card__title">Destination</h2>
                        <p class="detail-card__subtitle">Where the stock is headed</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Warehouse</span>
                        <span class="detail-kv__value">{{ $supply->warehouse->name ?: '—' }}</span>
                    </div>
                    @if($supply->warehouse->contact_person)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Contact</span>
                            <span class="detail-kv__value">{{ $supply->warehouse->contact_person }}</span>
                        </div>
                    @endif
                    @if($supply->warehouse->address)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Address</span>
                            <span class="detail-kv__value detail-kv__value--muted">{{ $supply->warehouse->address }}</span>
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
                        <p class="detail-card__subtitle">Documents raised from this supply</p>
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
                                <span class="text-muted small d-block">
                                    {{ ucfirst($grn->status) }} ·
                                    {{ optional($grn->received_date)->format('M d, Y') ?: 'No date set' }}
                                </span>
                            @else
                                <span class="text-muted">Created when the supply is completed</span>
                            @endif
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Supplier Bill</span>
                        <span class="detail-kv__value">
                            @if($bill)
                                <a href="{{ route('supplier-bills.show', $bill) }}">
                                    {{ $bill->formatted_id ?? '#' . $bill->id }}
                                </a>
                                <span class="text-muted small d-block">
                                    ${{ number_format($bill->total_amount, 2) }} · {{ ucfirst($bill->status) }}
                                </span>
                            @else
                                <span class="text-muted">Not yet generated</span>
                            @endif
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Supply Recorded</span>
                        <span class="detail-kv__value detail-kv__value--muted">
                            {{ optional($supply->created_at)->format('M d, Y') ?: '—' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Line items --}}
        <div class="col-12">
            <div class="detail-card supply-items">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-box-seam"></i></span>
                    <div>
                        <h2 class="detail-card__title">Supplied Products</h2>
                        <p class="detail-card__subtitle">
                            What was ordered from {{ $supply->vendor->name ?: 'the vendor' }}, at the cost recorded here.
                        </p>
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
                                    <th class="text-end">Line Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($items as $index => $item)
                                    <tr>
                                        <td data-label="#">{{ $index + 1 }}</td>
                                        <td data-label="Product">
                                            <strong>{{ $item->product->name ?: 'Unknown product' }}</strong>
                                            <span class="d-block text-muted small">
                                                SKU {{ $item->product->sku ?: 'N/A' }}
                                            </span>
                                        </td>
                                        <td data-label="Quantity" class="text-end">
                                            {{ number_format($item->quantity) }}
                                        </td>
                                        <td data-label="Unit Cost" class="text-end">
                                            ${{ number_format($item->unit_cost, 2) }}
                                        </td>
                                        <td data-label="Line Total" class="text-end fw-semibold">
                                            ${{ number_format($item->subtotal ?? ($item->quantity * $item->unit_cost), 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No items on this supply.
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
                                        <td data-label="Total cost" class="text-end fw-bold">
                                            ${{ number_format($totalValue, 2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

            @if($supply->notes)
                <div class="detail-card mt-4">
                    <div class="detail-card__header">
                        <span class="detail-card__step"><i class="bi bi-sticky"></i></span>
                        <div>
                            <h2 class="detail-card__title">Notes</h2>
                            <p class="detail-card__subtitle">Recorded with the supply</p>
                        </div>
                    </div>
                    <div class="detail-card__body">
                        <div class="detail-panel mb-0">{{ $supply->notes }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if($isPending)
        {{-- Status change: Pending -> Completed is the only transition the service implements --}}
        <div class="modal fade" id="markCompletedModal" tabindex="-1" aria-labelledby="markCompletedModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="markCompletedModalLabel">Change Supply Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-warning">{{ ucfirst($supply->status) }}</span>
                            <i class="bi bi-arrow-right text-muted"></i>
                            <span class="badge bg-success">Completed</span>
                        </div>
                        <p class="mb-2">
                            Marking supply {{ $supply->formatted_id ?? '#' . $supply->id }} as completed raises a
                            draft goods received note so the receiving team can check the delivery in.
                        </p>
                        <p class="text-muted small mb-0">
                            Stock is not added to {{ $supply->warehouse->name ?: 'the warehouse' }} until that
                            GRN is posted.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form action="{{ route('supplies.completed', $supply) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i> Mark as Completed
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('styles')
<style>
    /* Totals row: keep it readable once the table collapses to cards on mobile */
    @media (max-width: 768px) {
        .supply-items tfoot tr {
            display: block;
            border-top: 1px solid #dee2e6;
        }

        .supply-items tfoot td {
            display: block;
            text-align: right;
            padding: 0.35rem 0.75rem;
        }

        .supply-items tfoot td:empty {
            display: none;
        }

        /* Mirror the tbody label treatment custom.css applies below 768px */
        .supply-items tfoot td[data-label]::before {
            content: attr(data-label);
            float: left;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.85em;
        }
    }
</style>
@endpush

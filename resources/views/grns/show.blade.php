@extends('layouts.app')

@php
    $supply = $grn->supply;
    $items  = $supply?->items ?? collect();
    $bill   = $grn->supplierBill;

    $isPosted    = $grn->status === 'posted';
    $totalUnits  = $items->sum('quantity');
    $totalValue  = $items->sum(fn ($item) => $item->subtotal ?? ($item->quantity * $item->unit_cost));
    $receivedOn  = optional($grn->received_date)->format('M d, Y') ?: '—';

    // Where this receipt sits in Record Supply -> Receive Goods -> Supplier Bill -> Payment.
    $stages = \App\Support\SuppliesWorkflow::forGrn($grn);
@endphp

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Goods Received Note {{ $grn->formatted_id ?? '#' . $grn->id }}</h1>
            <p class="text-muted mb-0">
                <span class="badge bg-{{ $isPosted ? 'success' : 'secondary' }}">{{ ucfirst($grn->status) }}</span>
                <span class="ms-2">Received {{ $receivedOn }}</span>
                @if($supply)
                    <span class="ms-2">·</span>
                    <span class="ms-2">{{ $supply->vendor->name ?: 'Unknown vendor' }}</span>
                @endif
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <a href="{{ route('grns.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print
            </button>
            @unless($isPosted)
                <form action="{{ route('grns.destroy', $grn) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Delete this draft GRN? The supply itself is not affected.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash me-1"></i> Delete
                    </button>
                </form>
                <form action="{{ route('grns.update-status', $grn) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Post GRN
                    </button>
                </form>
            @endunless
        </div>
    </div>
@endsection

@section('content')
@unless($supply)
    <div class="detail-card">
        <div class="detail-card__body text-center py-5">
            <i class="bi bi-exclamation-triangle fs-2 text-muted d-block mb-2"></i>
            <p class="mb-1"><strong>This GRN has no supply attached.</strong></p>
            <p class="text-muted mb-0">Nothing can be shown until the receipt is linked to a supply record.</p>
        </div>
    </div>
@else
    {{-- Where this receipt sits in the purchase workflow --}}
    <x-workflow-rail :stages="$stages" />

    {{-- Headline figures --}}
    <div class="detail-card mb-4">
        <div class="detail-card__body">
            <div class="detail-figures">
                <div class="detail-figure">
                    <span class="detail-figure__label">Total Value</span>
                    <span class="detail-figure__value detail-figure__value--lead">${{ number_format($totalValue, 2) }}</span>
                    <span class="detail-figure__note">At recorded cost</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Units Received</span>
                    <span class="detail-figure__value">{{ number_format($totalUnits) }}</span>
                    <span class="detail-figure__note">
                        {{ $isPosted ? 'Added to stock' : 'Not yet in stock' }}
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Products</span>
                    <span class="detail-figure__value">{{ number_format($items->count()) }}</span>
                    <span class="detail-figure__note">Distinct lines</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Received</span>
                    <span class="detail-figure__value">{{ optional($grn->received_date)->format('M d') ?: '—' }}</span>
                    <span class="detail-figure__note">{{ optional($grn->received_date)->format('Y') ?: 'No date set' }}</span>
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
                        <p class="detail-card__subtitle">Who supplied the goods</p>
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
                        <h2 class="detail-card__title">Receiving Location</h2>
                        <p class="detail-card__subtitle">Where the stock lands</p>
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
                        <p class="detail-card__subtitle">Documents either side of this receipt</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Supply</span>
                        <span class="detail-kv__value">
                            <a href="{{ route('supplies.show', $supply->id) }}">
                                {{ $supply->formatted_id ?? '#' . $supply->id }}
                            </a>
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
                        <span class="detail-kv__label">GRN Created</span>
                        <span class="detail-kv__value detail-kv__value--muted">
                            {{ optional($grn->created_at)->format('M d, Y') ?: '—' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Line items --}}
        <div class="col-12">
            <div class="detail-card grn-items">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-box-seam"></i></span>
                    <div>
                        <h2 class="detail-card__title">Items Received</h2>
                        <p class="detail-card__subtitle">
                            {{ $isPosted
                                ? 'These quantities have been added to ' . ($supply->warehouse->name ?: 'the warehouse') . '.'
                                : 'These quantities enter stock once the GRN is posted.' }}
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
                                            No items on this receipt.
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
                                        <td data-label="Total value" class="text-end fw-bold">
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
@endunless
@endsection

@push('styles')
<style>
    /* Totals row: keep it readable once the table collapses to cards on mobile */
    @media (max-width: 768px) {
        .grn-items tfoot tr {
            display: block;
            border-top: 1px solid #dee2e6;
        }

        .grn-items tfoot td {
            display: block;
            text-align: right;
            padding: 0.35rem 0.75rem;
        }

        .grn-items tfoot td:empty {
            display: none;
        }

        /* Mirror the tbody label treatment custom.css applies below 768px */
        .grn-items tfoot td[data-label]::before {
            content: attr(data-label);
            float: left;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.85em;
        }
    }
</style>
@endpush

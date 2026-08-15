@extends('layouts.app')

@php use Illuminate\Support\Str; @endphp

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Transaction Flow</h1>
            <p class="text-muted mb-0">
                Where stock is, how it gets there, and what has moved lately
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <a href="{{ route('stock-locations.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-geo-alt me-1"></i> Stock Locations
            </a>
        </div>
    </div>
@endsection

@section('content')
    {{-- Headline figures --}}
    <div class="detail-card mb-4">
        <div class="detail-card__body">
            <div class="detail-figures">
                <div class="detail-figure">
                    <span class="detail-figure__label">Stock Value</span>
                    <span class="detail-figure__value detail-figure__value--lead">
                        ${{ number_format($stockSummary['total_stock_value'], 2) }}
                    </span>
                    <span class="detail-figure__note">On hand, at purchase price</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Products</span>
                    <span class="detail-figure__value">{{ number_format($stockSummary['total_products']) }}</span>
                    <span class="detail-figure__note">In the catalogue</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Pending Pickings</span>
                    <span class="detail-figure__value">{{ number_format($stockSummary['pending_movements']) }}</span>
                    <span class="detail-figure__note">Raised but not started</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Active Pickings</span>
                    <span class="detail-figure__value">{{ number_format($stockSummary['active_pickings']) }}</span>
                    <span class="detail-figure__note">Pending or in progress</span>
                </div>
            </div>
        </div>
    </div>

    {{-- The routes stock can take --}}
    <div class="detail-card mb-4">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-diagram-3"></i></span>
            <div>
                <h2 class="detail-card__title">Stock Flow</h2>
                <p class="detail-card__subtitle">
                    Every route stock can take between a vendor and a customer
                </p>
            </div>
        </div>
        <div class="detail-card__body">
            @include('picking.partials.flow-diagram')

            <div class="row g-3 mt-1">
                <div class="col-lg-6">
                    <div class="detail-panel h-100">
                        <span class="flow-route__label flow-route__label--direct">Direct route</span>
                        <p class="mb-0 mt-2">
                            An online order is picked at the warehouse and shipped straight to the
                            customer. Stock leaves the warehouse once and never sits anywhere else.
                        </p>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="detail-panel h-100">
                        <span class="flow-route__label flow-route__label--retail">Retail route</span>
                        <p class="mb-0 mt-2">
                            Stock is transferred from the warehouse to a retailer first, and the
                            retailer delivers to the customer. It counts as retailer stock from the
                            moment the transfer is received.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- What has actually moved --}}
    <div class="detail-card">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-arrow-left-right"></i></span>
            <div>
                <h2 class="detail-card__title">Recent Stock Movements</h2>
                <p class="detail-card__subtitle">
                    The {{ $recentMovements->count() }} most recent transactions recorded against stock
                </p>
            </div>
        </div>
        <div class="detail-card__body detail-card__body--flush">
            @if($recentMovements->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 movement-table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Product</th>
                                <th>Route</th>
                                <th>Type</th>
                                <th class="text-end">Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentMovements as $movement)
                                @php
                                    $when     = $movement->transaction_date ?? $movement->created_at;
                                    $inbound  = $movement->direction === 'inbound';
                                    $outbound = $movement->direction === 'outbound';

                                    // Only one end of a movement is a stock location; the other
                                    // end is whoever it came from or went to, which the
                                    // transaction type is what actually names.
                                    $counterparty = match ($movement->transaction_type) {
                                        'stock_in', 'vendor_return'         => 'Vendor',
                                        'order_fulfillment', 'customer_return' => 'Customer',
                                        'retailer_return'                   => 'Retailer',
                                        'stock_transfer'                    => 'Other location',
                                        default                             => 'Adjustment',
                                    };
                                    $held = $movement->location?->name;

                                    $fromLabel = $inbound ? $counterparty : ($held ?? $counterparty);
                                    $toLabel   = $inbound ? ($held ?? $counterparty) : $counterparty;

                                    $typeColour = match ($movement->transaction_type) {
                                        'stock_in'                                          => 'success',
                                        'stock_transfer'                                    => 'primary',
                                        'order_fulfillment'                                 => 'info',
                                        'customer_return', 'vendor_return', 'retailer_return' => 'warning',
                                        default                                             => 'secondary',
                                    };
                                @endphp
                                <tr>
                                    <td data-label="When">
                                        <span class="fw-semibold">{{ optional($when)->format('M d') ?: '—' }}</span>
                                        <span class="d-block text-muted small">
                                            {{ optional($when)->format('Y · H:i') ?: 'No date' }}
                                        </span>
                                    </td>
                                    <td data-label="Product">
                                        @if($movement->product)
                                            <a href="{{ route('picking.product-transaction-history', $movement->product) }}"
                                               class="fw-semibold">
                                                {{ Str::limit($movement->product->name, 40) }}
                                            </a>
                                            @if($movement->product->sku)
                                                <span class="d-block text-muted small">{{ $movement->product->sku }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">Product removed</span>
                                        @endif
                                    </td>
                                    <td data-label="Route">
                                        <span class="movement-route">
                                            <span class="movement-route__end">{{ Str::limit($fromLabel, 28) }}</span>
                                            <i class="bi bi-arrow-right movement-route__arrow"></i>
                                            <span class="movement-route__end">{{ Str::limit($toLabel, 28) }}</span>
                                        </span>
                                    </td>
                                    <td data-label="Type">
                                        <span class="badge bg-{{ $typeColour }}">
                                            {{ Str::headline($movement->transaction_type ?? 'unknown') }}
                                        </span>
                                    </td>
                                    <td data-label="Quantity" class="text-end">
                                        <span class="fw-semibold {{ $inbound ? 'text-success' : ($outbound ? 'text-danger' : '') }}">
                                            @if($inbound){{ '+' }}@elseif($outbound){{ '−' }}@endif{{ number_format($movement->quantity) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="detail-card__body text-center text-muted">
                    <i class="bi bi-arrow-left-right d-block fs-3 mb-2 opacity-50"></i>
                    Nothing has moved yet. Receiving a supply is what records the first movement.
                </div>
            @endif
        </div>
    </div>
@endsection

@section('styles')
<style>
/* Route captions under the diagram: a coloured tag matching its branch */
.flow-route__label {
    display: inline-block;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
}

.flow-route__label--direct {
    background-color: #e9f5f4;
    color: #227a6e;
}

.flow-route__label--retail {
    background-color: #fdf4e6;
    color: #8f6a17;
}

/* Route cell: two endpoints reading as one movement, not two loose badges */
.movement-route {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    white-space: nowrap;
}

.movement-route__end {
    font-size: 0.875rem;
    color: var(--dark-text);
}

.movement-route__arrow {
    color: var(--medium-text);
    font-size: 0.8rem;
}

.movement-table td {
    white-space: nowrap;
}
</style>
@endsection

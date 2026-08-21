@extends('layouts.app')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-1">Product Pricing</h1>
        <p class="text-muted mb-0">What each product costs you, and what you charge for it.</p>
    </div>
    <form method="GET" class="d-flex" style="max-width: 340px;">
        <input type="search" name="search" class="form-control me-2"
               placeholder="Search product or SKU" value="{{ request('search') }}">
        <button class="btn btn-outline-secondary" type="submit">Search</button>
    </form>
</div>
@endsection

@section('content')
<x-breadcrumb :items="[['label' => 'Product Pricing']]" />

@if($unpricedCount > 0)
<div class="alert alert-warning d-flex align-items-start" role="alert">
    <i class="bi bi-exclamation-triangle me-2 mt-1"></i>
    <div class="small">
        <strong>{{ $unpricedCount }} product(s) have no selling price.</strong>
        Until one is set, an order for them is priced from cost plus markup - a stopgap, not an agreed price.
    </div>
</div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 pricing-table">
            <thead>
                <tr>
                    <th style="width: 26%;">Product</th>
                    <th style="width: 30%;">
                        Purchase Price List
                        <span class="fw-normal text-muted">(what a vendor charges you)</span>
                    </th>
                    <th style="width: 30%;">
                        Sale Price List
                        <span class="fw-normal text-muted">(what you charge to customer)</span>
                    </th>
                    <th class="text-end" style="width: 14%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $product->name }}</div>
                        <div class="small text-muted">{{ $product->sku }}</div>
                        @if($product->category)
                            <span class="badge bg-info-subtle text-info-emphasis mt-1">{{ $product->category->name }}</span>
                        @endif
                    </td>

                    {{-- What each vendor charges. A vendor who carries the product
                         but has no agreed price is absent here rather than shown
                         at zero, which a purchase order would accept as free. --}}
                    <td>
                        @forelse($product->purchase_rows as $vendorId => $row)
                            <div class="d-flex justify-content-between border-bottom py-1 price-line">
                                <span class="small text-truncate me-2">{{ $row->list_name }}</span>
                                <span class="fw-semibold">${{ number_format((float) $row->unit_price, 2) }}</span>
                            </div>
                        @empty
                            <span class="badge bg-warning text-dark">No vendor price set</span>
                        @endforelse
                    </td>

                    {{-- What we charge: split by where the order is fulfilled from,
                         and within that, one price per vendor cost it was derived
                         from. Only the row marked Charged is what an order pays. --}}
                    <td>
                        @php($hasSale = false)
                        @foreach($fulfilmentKinds as $key => $kind)
                            @php($rows = $product->sale_rows[$key] ?? collect())
                            @if($rows->isNotEmpty())
                                @php($hasSale = true)
                                <div class="small fw-semibold text-muted mt-1">{{ $kind['label'] }}</div>
                                @foreach($rows as $row)
                                    @php($gp = $row->grossProfit())
                                    <div class="d-flex justify-content-between border-bottom py-1 price-line">
                                        <span class="small text-truncate me-2">
                                            @if($row->is_charged)
                                                <i class="bi bi-check-circle-fill text-success" title="Charged on orders"></i>
                                            @else
                                                <i class="bi bi-circle text-muted" title="Not charged"></i>
                                            @endif
                                            @if($row->basis)
                                                from ${{ number_format((float) $row->basis->unit_price, 2) }}
                                            @else
                                                no cost basis
                                            @endif
                                        </span>
                                        <span>
                                            <span class="fw-semibold {{ $row->is_charged ? '' : 'text-muted' }}">
                                                ${{ number_format((float) $row->unit_price, 2) }}
                                            </span>
                                            @if($gp !== null)
                                                <small class="{{ $gp < 0 ? 'text-danger fw-semibold' : 'text-success' }}">
                                                    ({{ $gp < 0 ? '-' : '+' }}${{ number_format(abs($gp), 2) }})
                                                </small>
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            @endif
                        @endforeach
                        @unless($hasSale)
                            <span class="badge bg-warning text-dark">Not priced</span>
                        @endunless
                    </td>

                    <td class="text-end">
                        @can('product-pricing.manage')
                        <a href="{{ route('product-pricing.edit', $product->id) }}" class="btn btn-sm btn-primary">
                            Set prices
                        </a>
                        @endcan
                        <a href="{{ route('product-pricing.history', $product->id) }}" class="btn btn-sm btn-outline-secondary">
                            History
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center text-muted py-5">
                        No products found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <x-pagination :paginator="$products" />
    </div>
</div>
@endsection

@section('styles')
<style>
    /* The three columns answer three different questions - what the product is,
       what it costs, what it sells for - so they are ruled apart rather than
       left to run together. */
    .pricing-table th:not(:last-child),
    .pricing-table td:not(:last-child) {
        border-right: 1px solid var(--bs-border-color);
    }

    .pricing-table th {
        vertical-align: top;
    }

    /* Rows inside a cell are their own small list; the last needs no rule. */
    .pricing-table .price-line:last-child {
        border-bottom: 0 !important;
    }
</style>
@endsection

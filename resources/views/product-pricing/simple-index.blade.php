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

@if($unpricedCount > 0)
<div class="alert alert-warning d-flex align-items-start small" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <div>
        <strong>{{ $unpricedCount }} product(s) have no selling price.</strong>
        Until one is set, an order for them is priced from cost plus markup - a stopgap, not an agreed price.
    </div>
</div>
@endif

<div class="card">
    <div class="card-header bg-white">
        <span class="small text-muted">
            One purchase price per product, whoever supplies it, marked up by a fixed
            <strong>{{ rtrim(rtrim(number_format($markup, 2, '.', ''), '0'), '.') }}%</strong>
            to give the selling price.
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 34%;">Product</th>
                    <th class="text-end" style="width: 14%;">Purchase price</th>
                    <th class="text-end" style="width: 10%;">Markup</th>
                    <th class="text-end" style="width: 14%;">Selling price</th>
                    <th class="text-end" style="width: 14%;">Gross profit</th>
                    <th class="text-end" style="width: 14%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                @php($row = $snapshots[$product->id] ?? null)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $product->name }}</div>
                        <div class="small text-muted">{{ $product->sku }}</div>
                        @if($product->category)
                            <span class="badge bg-info-subtle text-info-emphasis mt-1">{{ $product->category->name }}</span>
                        @endif
                    </td>

                    <td class="text-end">
                        @if($row && $row['cost'] !== null)
                            <span class="fw-semibold">${{ number_format($row['cost'], 2) }}</span>
                        @else
                            <span class="badge bg-warning text-dark">No price set</span>
                        @endif
                    </td>

                    <td class="text-end text-muted">
                        {{ rtrim(rtrim(number_format($markup, 2, '.', ''), '0'), '.') }}%
                    </td>

                    <td class="text-end">
                        @if($row && $row['selling'] !== null)
                            <span class="fw-semibold">${{ number_format($row['selling'], 2) }}</span>
                            @unless($row['in_step'])
                                {{-- Priced under the full editor before the mode was
                                     turned on, so it does not follow the markup. Saying
                                     so beats quietly showing a figure orders do not pay. --}}
                                <i class="bi bi-exclamation-triangle-fill text-warning ms-1"
                                   data-bs-toggle="tooltip"
                                   title="This is not what the markup gives. Open it and save to bring it into step."></i>
                            @endunless
                        @else
                            <span class="badge bg-warning text-dark">Not priced</span>
                        @endif
                    </td>

                    <td class="text-end">
                        @if($row && $row['gross_profit'] !== null)
                            <span class="fw-semibold {{ $row['gross_profit'] < 0 ? 'text-danger' : 'text-success' }}">
                                {{ $row['gross_profit'] < 0 ? '-' : '+' }}${{ number_format(abs($row['gross_profit']), 2) }}
                            </span>
                        @else
                            <span class="text-muted">&mdash;</span>
                        @endif
                    </td>

                    <td class="text-end">
                        @can('product-pricing.manage')
                        <a href="{{ route('product-pricing.edit', $product->id) }}" class="btn btn-sm btn-primary">
                            Set price
                        </a>
                        @endcan
                        <a href="{{ route('product-pricing.history', $product->id) }}" class="btn btn-sm btn-outline-secondary">
                            History
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">
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

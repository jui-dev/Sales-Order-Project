@extends('layouts.app')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-1">{{ $product->name }}</h1>
        <p class="text-muted mb-0">Price and cost history &middot; {{ $product->sku }}</p>
    </div>
    <div>
        <a href="{{ route('products.show', $product->id) }}" class="btn btn-outline-secondary">View product</a>
        <a href="{{ route('product-pricing.index') }}" class="btn btn-secondary">Back to Price Lists</a>
    </div>
</div>
@endsection

@section('content')
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <h2 class="h6 mb-0">What it has been priced at</h2>
            </div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>List</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">Min qty</th>
                            <th>From</th>
                            <th>Until</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($priceHistory as $row)
                        <tr class="{{ $row->ends_at ? 'text-muted' : '' }}">
                            <td>
                                {{ $row->priceList->name ?? '—' }}
                                <span class="badge {{ ($row->priceList->type ?? '') === 'sale' ? 'bg-success-subtle text-success-emphasis' : 'bg-info-subtle text-info-emphasis' }}">
                                    {{ $row->priceList->type ?? '' }}
                                </span>
                            </td>
                            <td class="text-end">${{ number_format((float) $row->unit_price, 2) }}</td>
                            <td class="text-end">{{ $row->min_quantity }}</td>
                            <td>{{ $row->starts_at?->format('d M Y') }}</td>
                            <td>
                                @if($row->ends_at)
                                    {{ $row->ends_at->format('d M Y') }}
                                @else
                                    <span class="badge bg-success">Current</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No price has ever been set for this product.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <h2 class="h6 mb-0">What it has cost us</h2>
            </div>
            <div class="card-body pb-0">
                <p class="small text-muted">
                    A weighted average, restruck each time goods are received - so a small cheap delivery cannot
                    restate stock that cost more.
                </p>
            </div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Cost</th>
                            <th class="text-end">On hand</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($costHistory as $cost)
                        <tr>
                            <td>{{ $cost->effective_at?->format('d M Y') }}</td>
                            <td class="text-end">${{ number_format((float) $cost->unit_cost, 2) }}</td>
                            <td class="text-end">{{ $cost->quantity_on_hand }}</td>
                            <td>
                                @if($cost->source_id)
                                    {{ class_basename($cost->source_type) }} #{{ $cost->source_id }}
                                @else
                                    <span class="text-muted">Opening balance</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                This product has never been received.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

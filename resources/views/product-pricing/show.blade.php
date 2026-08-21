@extends('layouts.app')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-1">
            {{ $list->name }}
            @if($list->is_default)<span class="badge bg-primary align-middle">Default</span>@endif
        </h1>
        <p class="text-muted mb-0">
            {{ $list->type === 'sale' ? 'What we charge' : 'What we are charged' }} &middot;
            {{ $list->assignments->isEmpty() ? 'Applies to everyone' : 'Applies to ' . $list->assignments->map(fn($a) => class_basename($a->assignable_type).' '.($a->assignable->name ?? '#'.$a->assignable_id))->join(', ') }}
        </p>
    </div>
    <a href="{{ route('product-pricing.index') }}" class="btn btn-secondary">Back to Price Lists</a>
</div>
@endsection

@section('content')
<x-breadcrumb :items="[
    ['label' => 'Product Pricing', 'url' => route('product-pricing.index')],
    ['label' => $list->name],
]" />

@can('product-pricing.manage')
<div class="card mb-4">
    <div class="card-header">
        <h2 class="h6 mb-0">Add a price</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('product-pricing.prices.add', $list->id) }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-5">
                <label for="product_id" class="form-label">Product <span class="text-danger">*</span></label>
                <select name="product_id" id="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
                    <option value="">Select a product</option>
                    @foreach($assignableProducts as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                    @endforeach
                </select>
                @error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="unit_price" class="form-label">Unit price <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" step="0.0001" min="0" name="unit_price" id="unit_price"
                           class="form-control @error('unit_price') is-invalid @enderror" value="{{ old('unit_price') }}" required>
                    @error('unit_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-2">
                <label for="min_quantity" class="form-label">Min quantity</label>
                <input type="number" min="1" name="min_quantity" id="min_quantity" class="form-control" value="{{ old('min_quantity', 1) }}">
                <div class="form-text">Quantity break</div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Add price</button>
            </div>
        </form>
    </div>
</div>
@endcan

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0">Prices in force</h2>
        <form method="GET" class="d-flex" style="max-width: 320px;">
            <input type="search" name="search" class="form-control form-control-sm me-2"
                   placeholder="Search product or SKU" value="{{ request('search') }}">
            <button class="btn btn-sm btn-outline-secondary" type="submit">Search</button>
        </form>
    </div>

    {{-- The bulk edit form. Delete forms cannot nest inside it, so they are
         declared after the table and referenced by the form attribute. --}}
    <form method="POST" action="{{ route('product-pricing.prices.update', $list->id) }}" id="bulk-price-form">
        @csrf
        @method('PUT')
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-end" style="width: 12rem;">Unit price</th>
                        <th class="text-end">Min qty</th>
                        <th>In force since</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $row->product->name ?? 'Unknown product' }}</div>
                            <div class="small text-muted">{{ $row->product->sku ?? '' }}</div>
                        </td>
                        <td class="text-end">
                            @can('product-pricing.manage')
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.0001" min="0"
                                       name="rows[{{ $row->id }}][unit_price]"
                                       class="form-control text-end"
                                       value="{{ number_format((float) $row->unit_price, 4, '.', '') }}">
                            </div>
                            @else
                                ${{ number_format((float) $row->unit_price, 2) }}
                            @endcan
                        </td>
                        <td class="text-end">{{ $row->min_quantity }}</td>
                        <td>
                            <span title="{{ $row->starts_at }}">{{ $row->starts_at?->format('d M Y') }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('product-pricing.history', $row->product_id) }}"
                               class="btn btn-sm btn-outline-secondary">History</a>
                            @can('product-pricing.manage')
                            <button type="submit" form="remove-{{ $row->id }}" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Stop this list pricing that product? Its history stays on file.');">
                                Remove
                            </button>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            This list does not price anything yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @can('product-pricing.manage')
        @if($rows->isNotEmpty())
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="small text-muted">
                Saving starts a new price from now. The previous one stays readable at its own dates.
            </span>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
        @endif
        @endcan
    </form>

    <div class="card-footer bg-white border-0">
        <x-pagination :paginator="$rows" />
    </div>
</div>

@can('product-pricing.manage')
    {{-- Rendered outside the bulk form: a form cannot contain another form. --}}
    @foreach($rows as $row)
        <form id="remove-{{ $row->id }}" method="POST"
              action="{{ route('product-pricing.prices.remove', [$list->id, $row->product_id]) }}" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
@endcan
@endsection

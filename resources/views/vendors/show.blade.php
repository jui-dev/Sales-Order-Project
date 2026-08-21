@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Vendor Details</h1>
    <div>
        <a href="{{ route('vendors.edit', $vendor) }}" class="btn btn-primary">Edit</a>
        <a href="{{ route('vendors.index') }}" class="btn btn-secondary">Back to Vendors</a>
    </div>
</div>
@endsection

@section('content')


<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h3>{{ $vendor->name }}</h3>
                <p class="text-muted">Vendor ID: {{ $vendor->id }}</p>
                
                <div class="mb-3">
                    <h5>Contact Person</h5>
                    <p>{{ $vendor->contact_person ?? 'N/A' }}</p>
                </div>
                
                <div class="mb-3">
                    <h5>Email</h5>
                    <p>{{ $vendor->email ?? 'N/A' }}</p>
                </div>
                
                <div class="mb-3">
                    <h5>Phone</h5>
                    <p>{{ $vendor->phone ?? 'N/A' }}</p>
                </div>
                
                <div class="mb-3">
                    <h5>Address</h5>
                    <p>{{ $vendor->address ?? 'N/A' }}</p>
                </div>
                
                <div class="mb-3">
                    <h5>Created At</h5>
                    <p>{{ $vendor->created_at ? $vendor->created_at->format('F d, Y') : 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<h2>Products Supplied</h2>
<div class="card mb-4">
    <div class="card-body">
        <p class="text-muted">
            Which products this vendor can supply. What they charge is shown here but set under
            <a href="{{ route('product-pricing.index') }}">Catalog &rsaquo; Product Pricing</a>,
            so cost is decided in one place. A purchase order addressed to this vendor prices its
            lines from that figure.
            @if(config('pricing.simple_mode', false))
                It is the product's one price &mdash; every vendor who carries it is quoted the same.
            @endif
        </p>

        {{-- Add a product to the list --}}
        <form action="{{ route('vendors.products.assign', $vendor) }}" method="POST" class="row g-2 align-items-end mb-4">
            @csrf
            <div class="col-md-6">
                <label for="product_id" class="form-label">Add a product</label>
                <select name="product_id" id="product_id"
                        class="form-select @error('product_id') is-invalid @enderror" required>
                    <option value="">Select a product…</option>
                    @foreach ($assignableProducts as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                    @endforeach
                </select>
                @error('product_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"
                        @disabled($assignableProducts->isEmpty())>Add product</button>
            </div>
        </form>

        {{-- Edit the existing rows --}}
        <form action="{{ route('vendors.price-list.update', $vendor) }}" method="POST" id="price-list-form">
            @csrf
            @method('PUT')
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th style="width: 10rem;">Unit Cost</th>
                        <th style="width: 12rem;">Vendor SKU</th>
                        <th style="width: 6rem;">Active</th>
                        <th style="width: 6rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vendor->vendorProducts as $row)
                        <tr>
                            <td>{{ $row->product->name ?? 'Unknown product' }}</td>
                            <td class="text-muted">{{ $row->product->sku ?? '—' }}</td>
                            <td>
                                {{-- Shown, not edited. Costs are set under Catalog >
                                     Product Pricing so there is one place money is
                                     decided - two editable copies of a price is how
                                     they drifted apart before. --}}
                                @if($row->current_cost !== null)
                                    <span class="fw-semibold">${{ number_format($row->current_cost, 2) }}</span>
                                @else
                                    <span class="badge bg-warning text-dark">Not priced</span>
                                @endif
                                @if($row->product)
                                    <a href="{{ route('product-pricing.edit', $row->product->id) }}"
                                       class="small d-block text-decoration-none">Set price</a>
                                @endif
                            </td>
                            <td>
                                <input type="text" name="rows[{{ $row->id }}][vendor_sku]"
                                       class="form-control form-control-sm"
                                       value="{{ old("rows.{$row->id}.vendor_sku", $row->vendor_sku) }}">
                            </td>
                            <td>
                                {{-- Paired hidden field so an unchecked box still posts a value --}}
                                <input type="hidden" name="rows[{{ $row->id }}][is_active]" value="0">
                                <input type="checkbox" class="form-check-input"
                                       name="rows[{{ $row->id }}][is_active]" value="1"
                                       @checked($row->is_active)>
                            </td>
                            <td>
                                {{-- form= points at the delete form below: a form cannot be nested inside another --}}
                                <button type="submit" form="remove-{{ $row->id }}"
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Remove this product from the price list?')">
                                    Remove
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                No products assigned to this vendor yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($vendor->vendorProducts->isNotEmpty())
                <button type="submit" class="btn btn-success">Save changes</button>
            @endif
        </form>

        {{-- Delete forms live outside the table so no form is nested in another --}}
        @foreach ($vendor->vendorProducts as $row)
            <form id="remove-{{ $row->id }}" method="POST"
                  action="{{ route('vendors.products.remove', [$vendor, $row->id]) }}" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    </div>
</div>

<h2>Supply History</h2>
<div class="card">
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Supply ID</th>
                    <th>Supply Number</th>
                    <th>Status</th>
                    <th>Total Cost</th>
                    <th>Supply Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vendor->supplies as $supply)
                <tr>
                    <td>{{ $supply->id }}</td>
                    <td>{{ $supply->supply_number ?? 'N/A' }}</td>
                    <td>
                        <span class="badge bg-{{ $supply->status === 'completed' ? 'success' : ($supply->status === 'pending' ? 'warning' : 'secondary') }}">
                            {{ ucfirst($supply->status ?? 'Unknown') }}
                        </span>
                    </td>
                    <td>${{ number_format($supply->total_cost ?? 0, 2) }}</td>
                    <td>{{ $supply->supply_date ? $supply->supply_date->format('M d, Y') : 'N/A' }}</td>
                    <td>
                        <a href="{{ route('supplies.show', $supply) }}" class="btn btn-sm btn-info">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center">No supply records found for this vendor</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection 
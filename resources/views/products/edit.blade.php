@extends('layouts.app')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Edit Product</h1>
        <p class="text-muted mb-0">{{ $product->name }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('products.show', $product) }}" class="btn btn-outline-primary">
            <i class="bi bi-eye me-2"></i>
            View Product
        </a>
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>
            Back to Products
        </a>
    </div>
</div>
@endsection

@section('content')
<form action="{{ route('products.update', $product) }}" method="POST" id="product-form" class="product-form">
    @csrf
    @method('PUT')

    <section class="form-section">
        <div class="form-section-header">
            <h2 class="form-section-title">Basic Information</h2>
        </div>
        <div class="form-section-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="name" class="form-label">
                    Name <span class="text-danger">*</span>
                </label>
                <input type="text"
                       class="form-control @error('name') is-invalid @enderror"
                       id="name"
                       name="name"
                       value="{{ old('name', $product->name) }}"
                       required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label">Pricing</label>
                {{-- Not editable here on purpose. Cost and price are neither of
                     them properties of the product: cost differs per vendor,
                     price differs by where the order is fulfilled from, and both
                     change over time. They are set in one place so there is no
                     second copy to disagree with this one. --}}
                @php($editPrice = $product->currentPrice())
                @php($editCost = $product->currentCost())
                <div class="d-flex align-items-center gap-3 pt-1">
                    <span>
                        <span class="text-muted small d-block">Cost</span>
                        <strong>{{ $editCost !== null ? '$' . number_format($editCost, 2) : 'Never received' }}</strong>
                    </span>
                    <span>
                        <span class="text-muted small d-block">Sells for</span>
                        <strong>{{ $editPrice !== null ? '$' . number_format($editPrice, 2) : 'Not priced' }}</strong>
                    </span>
                    <a href="{{ route('product-pricing.edit', $product->id) }}" class="btn btn-sm btn-outline-primary">
                        Set prices
                    </a>
                </div>
            </div>
        </div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-header">
            <h2 class="form-section-title">Vendors</h2>
        </div>
        <div class="form-section-body">
            <p class="text-muted">
                Who can supply this product.
                @if(config('pricing.simple_mode', false))
                    What it costs is set under
                    <a href="{{ route('product-pricing.edit', $product->id) }}">Product Pricing</a> &mdash;
                    one price, whichever of them you buy from.
                @else
                    What each of them charges is set under
                    <a href="{{ route('product-pricing.edit', $product->id) }}">Product Pricing</a> &mdash;
                    the same product can cost different amounts from different vendors, and keeping that
                    in one place is what stops two figures disagreeing.
                @endif
            </p>
            @forelse ($vendors as $vendor)
                @if ($loop->first)<div class="row g-2">@endif
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               name="vendor_ids[]" value="{{ $vendor->id }}"
                               id="vendor-{{ $vendor->id }}"
                               @checked(in_array($vendor->id, old('vendor_ids', $assignedVendorIds)))>
                        <label class="form-check-label" for="vendor-{{ $vendor->id }}">{{ $vendor->name }}</label>
                    </div>
                </div>
                @if ($loop->last)</div>@endif
            @empty
                <p class="text-muted mb-0">No vendors exist yet.</p>
            @endforelse
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-header">
            <h2 class="form-section-title">Categorisation</h2>
        </div>
        <div class="form-section-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="category_id" class="form-label">Category</label>
                <select class="form-select @error('category_id') is-invalid @enderror"
                        id="category_id"
                        name="category_id">
                    <option value="">Select Category</option>
                    @foreach($mainCategories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id', $selectedCategoryId) == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                @error('category_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="subcategory_id" class="form-label">Subcategory</label>
                <select class="form-select @error('subcategory_id') is-invalid @enderror"
                        id="subcategory_id"
                        name="subcategory_id">
                    <option value="">Select Subcategory</option>
                    @foreach($subcategories as $subcategory)
                        <option value="{{ $subcategory->id }}" {{ old('subcategory_id', $selectedSubcategoryId) == $subcategory->id ? 'selected' : '' }}>
                            {{ $subcategory->name }}
                        </option>
                    @endforeach
                </select>
                @error('subcategory_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-header">
            <h2 class="form-section-title">Stock</h2>
        </div>
        <div class="form-section-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="warehouse_stock" class="form-label">Warehouse Stock</label>
                <input type="number"
                       class="form-control @error('warehouse_stock') is-invalid @enderror"
                       id="warehouse_stock"
                       name="warehouse_stock"
                       value="{{ old('warehouse_stock', $product->warehouse_stock ?? 0) }}"
                       min="0">
                @error('warehouse_stock')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="retailer_stock" class="form-label">Retailer Stock</label>
                <input type="number"
                       class="form-control @error('retailer_stock') is-invalid @enderror"
                       id="retailer_stock"
                       name="retailer_stock"
                       value="{{ old('retailer_stock', $product->retailer_stock ?? 0) }}"
                       min="0">
                @error('retailer_stock')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <div class="form-inset">
                    <h3 class="form-inset-title">Quick Stock Transfer</h3>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="transfer_quantity" class="form-label">Quantity to Transfer</label>
                            <input type="number" class="form-control" id="transfer_quantity" min="1">
                        </div>
                        <div class="col-md-5">
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="transferStock('warehouse_to_retailer')">
                                    Warehouse <i class="bi bi-arrow-right mx-1"></i> Retailer
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="transferStock('retailer_to_warehouse')">
                                    Retailer <i class="bi bi-arrow-right mx-1"></i> Warehouse
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">
                                Total Stock: <strong id="total_stock">{{ ($product->warehouse_stock ?? 0) + ($product->retailer_stock ?? 0) }}</strong>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </section>

    <div class="form-actions d-flex flex-wrap justify-content-between align-items-center gap-2">
        <small class="text-muted"><span class="text-danger">*</span> Required fields</small>
        <div class="d-flex gap-2">
            <a href="{{ route('products.show', $product) }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-2"></i>
                Update Product
            </button>
        </div>
    </div>
</form>
@endsection

@section('scripts')
<script>
function transferStock(direction) {
    const quantity = parseInt(document.getElementById('transfer_quantity').value);
    const warehouseInput = document.getElementById('warehouse_stock');
    const retailerInput = document.getElementById('retailer_stock');
    const totalDisplay = document.getElementById('total_stock');

    if (!quantity || quantity <= 0) {
        alert('Please enter a valid quantity to transfer');
        return;
    }

    let warehouseStock = parseInt(warehouseInput.value) || 0;
    let retailerStock = parseInt(retailerInput.value) || 0;

    if (direction === 'warehouse_to_retailer') {
        if (warehouseStock < quantity) {
            alert('Not enough warehouse stock to transfer');
            return;
        }
        warehouseStock -= quantity;
        retailerStock += quantity;
    } else if (direction === 'retailer_to_warehouse') {
        if (retailerStock < quantity) {
            alert('Not enough retailer stock to transfer');
            return;
        }
        retailerStock -= quantity;
        warehouseStock += quantity;
    }

    // Update the input fields
    warehouseInput.value = warehouseStock;
    retailerInput.value = retailerStock;
    totalDisplay.textContent = warehouseStock + retailerStock;

    // Clear transfer quantity
    document.getElementById('transfer_quantity').value = '';
}

document.addEventListener('DOMContentLoaded', function() {
    // Keep the running total in sync when the stock fields are edited directly
    const warehouseInput = document.getElementById('warehouse_stock');
    const retailerInput = document.getElementById('retailer_stock');
    const totalDisplay = document.getElementById('total_stock');

    if (warehouseInput && retailerInput && totalDisplay) {
        function updateTotal() {
            const warehouse = parseInt(warehouseInput.value) || 0;
            const retailer = parseInt(retailerInput.value) || 0;
            totalDisplay.textContent = warehouse + retailer;
        }

        warehouseInput.addEventListener('input', updateTotal);
        retailerInput.addEventListener('input', updateTotal);
    }

    // Repopulate the subcategory select whenever the category changes
    const categorySelect = document.getElementById('category_id');
    const subcategorySelect = document.getElementById('subcategory_id');

    if (categorySelect && subcategorySelect) {
        categorySelect.addEventListener('change', function() {
            const categoryId = this.value;

            subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';

            if (categoryId) {
                subcategorySelect.disabled = true;
                subcategorySelect.innerHTML = '<option value="">Loading...</option>';

                fetch(`{{ route('products.get-subcategories') }}?category_id=${categoryId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        subcategorySelect.disabled = false;
                        subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';

                        if (data.options) {
                            Object.entries(data.options).forEach(([value, text]) => {
                                if (value !== '') { // Skip the "All Subcategories" option
                                    const option = document.createElement('option');
                                    option.value = value;
                                    option.textContent = text;
                                    subcategorySelect.appendChild(option);
                                }
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error loading subcategories:', error);
                        subcategorySelect.disabled = false;
                        subcategorySelect.innerHTML = '<option value="">Error loading subcategories</option>';
                    });
            }
        });
    }
});
</script>
@endsection

@extends('layouts.app')

@section('page-header')
<div class="mb-4">
    <h1 class="h2 mb-1">Add New Product</h1>
    <p class="text-muted mb-0">Create a new product in your inventory system</p>
</div>
@endsection

@section('content')
<form action="{{ route('products.store') }}" method="POST" id="product-form" class="product-form needs-validation" novalidate>
    @csrf

    {{-- Section 1: Basic information --}}
    <div class="card product-card mb-4">
        <div class="card-header product-card__header">
            <span class="product-card__step">1</span>
            <div>
                <h2 class="product-card__title">Basic Information</h2>
                <p class="product-card__subtitle">What the product is called, and how it is identified in your inventory.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="product-panel">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">
                        Product Name <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           class="form-control @error('name') is-invalid @enderror"
                           id="name"
                           name="name"
                           value="{{ old('name') }}"
                           placeholder="Enter product name"
                           required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="sku" class="form-label">
                        SKU Code <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input type="text"
                               class="form-control @error('sku') is-invalid @enderror"
                               id="sku"
                               name="sku"
                               value="{{ old('sku') }}"
                               placeholder="Enter SKU code"
                               required>
                        <button class="btn btn-outline-secondary" type="button" id="generate-sku" title="Generate from product name">
                            <i class="bi bi-magic"></i>
                        </button>
                        @error('sku')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-text">Use the wand to generate one from the product name.</div>
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control @error('description') is-invalid @enderror"
                              id="description"
                              name="description"
                              rows="3"
                              placeholder="Enter detailed product description">{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            </div>
        </div>
    </div>

    {{-- Section 2: Categorisation --}}
    <div class="card product-card mb-4">
        <div class="card-header product-card__header">
            <span class="product-card__step">2</span>
            <div>
                <h2 class="product-card__title">Categorisation</h2>
                <p class="product-card__subtitle">Where the product sits in your catalogue.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="product-panel">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="category_id" class="form-label">
                        Product Category <span class="text-danger">*</span>
                    </label>
                    <select class="form-select @error('category_id') is-invalid @enderror"
                            id="category_id"
                            name="category_id"
                            required>
                        <option value="">Select Category</option>
                        @foreach($mainCategories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label for="subcategory_id" class="form-label">Subcategory</label>
                    <select class="form-select @error('subcategory_id') is-invalid @enderror"
                            id="subcategory_id"
                            name="subcategory_id">
                        <option value="">Select Subcategory</option>
                    </select>
                    @error('subcategory_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

            </div>
            </div>
        </div>
    </div>

    {{-- Section 3: Vendors --}}
    <div class="card product-card mb-4">
        <div class="card-header product-card__header">
            <span class="product-card__step">3</span>
            <div>
                <h2 class="product-card__title">Vendors</h2>
                <p class="product-card__subtitle">Who can supply this product. What each of them charges is set under Catalog &rsaquo; Product Pricing.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="product-panel">
                @forelse ($vendors as $vendor)
                    @if ($loop->first)<div class="row g-2">@endif
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   name="vendor_ids[]" value="{{ $vendor->id }}"
                                   id="vendor-{{ $vendor->id }}"
                                   @checked(in_array($vendor->id, old('vendor_ids', [])))>
                            <label class="form-check-label" for="vendor-{{ $vendor->id }}">
                                {{ $vendor->name }}
                            </label>
                        </div>
                    </div>
                    @if ($loop->last)</div>@endif
                @empty
                    <p class="text-muted mb-0">
                        No vendors yet. <a href="{{ route('vendors.create') }}">Add a vendor</a> first,
                        or assign one later from the product's edit page.
                    </p>
                @endforelse
                @error('vendor_ids')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    {{-- Section 4: Review & submit --}}
    <div class="card product-card product-summary mb-4">
        <div class="card-body">
            <div class="product-summary__inner">
                <div>
                    <span class="product-summary__label">Ready to Save</span>
                    <span class="product-summary__value">Review the details above before creating the product.</span>
                </div>
                <div class="product-summary__actions">
                    <a href="{{ route('products.index') }}" class="btn btn-danger">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle"></i> Create Product
                    </button>
                </div>
            </div>
            <p class="product-summary__hint">
                Fields marked <span class="text-danger">*</span> are required. Purchase price, selling price and
                stock levels are filled in automatically once goods are received.
            </p>
        </div>
    </div>
</form>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('product-form');

    // Form validation
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });

        // Real-time validation
        form.querySelectorAll('[required]').forEach(field => {
            field.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
        });
    }

    // Auto-generate SKU functionality
    const generateSkuBtn = document.getElementById('generate-sku');
    const nameInput = document.getElementById('name');
    const skuInput = document.getElementById('sku');

    if (generateSkuBtn && nameInput && skuInput) {
        generateSkuBtn.addEventListener('click', function() {
            if (nameInput.value.trim()) {
                skuInput.value = nameInput.value
                    .toLowerCase()
                    .replace(/[^a-z0-9]/g, '')
                    .substring(0, 20);

                // Show success feedback
                generateSkuBtn.innerHTML = '<i class="bi bi-check"></i>';
                generateSkuBtn.classList.remove('btn-outline-secondary');
                generateSkuBtn.classList.add('btn-success');

                setTimeout(() => {
                    generateSkuBtn.innerHTML = '<i class="bi bi-magic"></i>';
                    generateSkuBtn.classList.remove('btn-success');
                    generateSkuBtn.classList.add('btn-outline-secondary');
                }, 2000);
            } else {
                // Show error feedback
                generateSkuBtn.innerHTML = '<i class="bi bi-exclamation"></i>';
                generateSkuBtn.classList.remove('btn-outline-secondary');
                generateSkuBtn.classList.add('btn-danger');

                setTimeout(() => {
                    generateSkuBtn.innerHTML = '<i class="bi bi-magic"></i>';
                    generateSkuBtn.classList.remove('btn-danger');
                    generateSkuBtn.classList.add('btn-outline-secondary');
                }, 2000);
            }
        });
    }

    // Category/subcategory functionality
    const categorySelect = document.getElementById('category_id');
    const subcategorySelect = document.getElementById('subcategory_id');

    if (categorySelect && subcategorySelect) {
        categorySelect.addEventListener('change', function() {
            const categoryId = this.value;

            // Clear subcategory options
            subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';

            if (categoryId) {
                // Show loading state
                subcategorySelect.disabled = true;
                subcategorySelect.innerHTML = '<option value="">Loading...</option>';

                // Fetch subcategories for the selected category
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

    // Markup formatting
;
    }
});
</script>
<style>
    /* ---- Section cards ------------------------------------------------
       Mirrors the section styling used by supplies/create so both "create"
       forms read the same way. Scoped to .product-form. */
    .product-form .product-card:hover {
        /* keep sections calm; no lift on a form page */
        box-shadow: var(--card-shadow);
    }

    .product-form .product-card__header {
        display: flex;
        align-items: flex-start;
        gap: 0.85rem;
        border-bottom: 0;
        padding-bottom: 0.35rem;
    }

    /* Light inner surface for a section's main content */
    .product-form .product-panel {
        padding: 1.1rem;
        border-radius: 6px;
        background-color: #f5f8f6;
    }

    .product-form .product-card__step {
        flex: 0 0 auto;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background-color: var(--primary);
        color: #fff;
        font-size: 0.8rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-top: 1px;
    }

    .product-form .product-card__title {
        margin: 0;
        font-size: 1.02rem;
        font-weight: 600;
        color: var(--dark-text);
        letter-spacing: 0.01em;
    }

    .product-form .product-card__subtitle {
        margin: 0.15rem 0 0;
        font-size: 0.825rem;
        font-weight: 400;
        color: #6c757d;
    }

    /* Fields sit on the tinted panel, so keep them on white */
    .product-form .product-panel .form-control,
    .product-form .product-panel .form-select {
        background-color: #fff;
    }

    .product-form .product-panel .form-control:disabled,
    .product-form .product-panel .form-select:disabled {
        background-color: #f8f9fa;
    }

    /* ---- Summary card -------------------------------------------------- */
    .product-form .product-summary__inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .product-form .product-summary__label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        color: #6c757d;
    }

    .product-form .product-summary__value {
        display: block;
        margin-top: 0.15rem;
        font-size: 1.05rem;
        font-weight: 600;
        color: var(--dark-text);
    }

    .product-form .product-summary__actions {
        display: flex;
        gap: 0.6rem;
    }


    .product-form .product-summary__hint {
        margin: 0.9rem 0 0;
        padding-top: 0.9rem;
        border-top: 1px solid #e3e8e4;
        font-size: 0.82rem;
        color: #6c757d;
    }

    /* ---- Required-field cues ------------------------------------------- */
    .product-form .form-label .text-danger {
        font-weight: 600;
        margin-left: 2px;
    }

    @media (max-width: 768px) {
        .product-form .product-panel {
            padding: 0.9rem;
        }

        .product-form .product-summary__inner {
            align-items: flex-start;
            flex-direction: column;
        }

        .product-form .product-summary__actions {
            width: 100%;
        }

        .product-form .product-summary__actions .btn {
            flex: 1;
        }

        .product-form .form-label .text-danger {
            font-size: 0.9em;
        }
    }
</style>
@endsection

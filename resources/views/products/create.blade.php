@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
            <li class="breadcrumb-item active" aria-current="page">Add New Product</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-1">Add New Product</h1>
            <p class="text-muted mb-0">Create a new product in your inventory system</p>
        </div>
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>
            Back to Products
        </a>
    </div>

    <!-- Information Alert -->
    <div class="alert alert-info border-0 shadow-sm mb-4" role="alert">
        <div class="d-flex align-items-start">
            <i class="bi bi-info-circle-fill text-primary me-3 mt-1"></i>
            <div>
                <h6 class="alert-heading mb-2">Product Information</h6>
                <p class="mb-0 small">Product purchase prices and stock levels are automatically updated through supply and order transactions. GP (Gross Profit) is calculated when orders are confirmed.</p>
            </div>
        </div>
    </div>

    <!-- Main Form -->
    <form action="{{ route('products.store') }}" method="POST" id="product-form" class="needs-validation" novalidate>
        @csrf
        
        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Section 1: Basic Information -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-card-text text-primary me-2"></i>
                            Basic Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">
                                    Product Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control @if(isset($errors) && $errors->has('name')) is-invalid @endif" 
                                       id="name" 
                                       name="name" 
                                       value="{{ old('name') }}" 
                                       placeholder="Enter product name"
                                       required>
                                @if(isset($errors) && $errors->has('name'))
                                    <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                @endif
                                <div class="form-text">Enter a descriptive name for the product</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="sku" class="form-label">
                                    SKU Code <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="text" 
                                           class="form-control @if(isset($errors) && $errors->has('sku')) is-invalid @endif" 
                                           id="sku" 
                                           name="sku" 
                                           value="{{ old('sku') }}" 
                                           placeholder="Enter SKU code"
                                           required>
                                    <button class="btn btn-outline-secondary" type="button" id="generate-sku">
                                        <i class="bi bi-magic"></i>
                                    </button>
                                </div>
                                @if(isset($errors) && $errors->has('sku'))
                                    <div class="invalid-feedback">{{ $errors->first('sku') }}</div>
                                @endif
                                <div class="form-text">Click the magic wand to auto-generate SKU from product name</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @if(isset($errors) && $errors->has('description')) is-invalid @endif" 
                                      id="description" 
                                      name="description" 
                                      rows="4" 
                                      placeholder="Enter detailed product description">{{ old('description') }}</textarea>
                            @if(isset($errors) && $errors->has('description'))
                                <div class="invalid-feedback">{{ $errors->first('description') }}</div>
                            @endif
                            <div class="form-text">Provide detailed information about the product</div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Categorization -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-tags text-primary me-2"></i>
                            Categorization
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">
                                    Product Category <span class="text-danger">*</span>
                                </label>
                                <select class="form-select @if(isset($errors) && $errors->has('category_id')) is-invalid @endif" 
                                        id="category_id" 
                                        name="category_id" 
                                        required>
                                    <option value="">Select Category</option>
                                    @php
                                        try {
                                            $mainCategories = \App\Models\ProductCategory::getMainCategories();
                                        } catch (\Throwable $e) {
                                            $mainCategories = collect();
                                        }
                                    @endphp
                                    @foreach($mainCategories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if(isset($errors) && $errors->has('category_id'))
                                    <div class="invalid-feedback">{{ $errors->first('category_id') }}</div>
                                @endif
                                <div class="form-text">Choose the main category for this product</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="subcategory_id" class="form-label">Subcategory</label>
                                <select class="form-select @if(isset($errors) && $errors->has('subcategory_id')) is-invalid @endif" 
                                        id="subcategory_id" 
                                        name="subcategory_id">
                                    <option value="">Select Subcategory</option>
                                </select>
                                @if(isset($errors) && $errors->has('subcategory_id'))
                                    <div class="invalid-feedback">{{ $errors->first('subcategory_id') }}</div>
                                @endif
                                <div class="form-text">Optional: Choose a specific subcategory</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Pricing & Tax -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-currency-dollar text-primary me-2"></i>
                            Pricing & Tax
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="selling_price" class="form-label">
                                    Selling Price <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" 
                                           class="form-control @if(isset($errors) && $errors->has('selling_price')) is-invalid @endif" 
                                           id="selling_price" 
                                           name="selling_price" 
                                           value="{{ old('selling_price') }}" 
                                           min="0" 
                                           step="0.01" 
                                           placeholder="0.00"
                                           required>
                                </div>
                                @if(isset($errors) && $errors->has('selling_price'))
                                    <div class="invalid-feedback">{{ $errors->first('selling_price') }}</div>
                                @endif
                                <div class="form-text">Enter the retail price for this product</div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="discount_percentage" class="form-label">Discount %</label>
                                <div class="input-group">
                                    <input type="number" 
                                           class="form-control" 
                                           id="discount_percentage" 
                                           name="discount_percentage" 
                                           value="{{ old('discount_percentage', 0) }}" 
                                           min="0" 
                                           max="100" 
                                           step="0.01" 
                                           placeholder="0.00">
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="form-text">Optional discount percentage</div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="tax_rate" class="form-label">Tax Rate %</label>
                                <select class="form-select" id="tax_rate" name="tax_rate">
                                    <option value="0" {{ old('tax_rate', 0) == 0 ? 'selected' : '' }}>0% (No Tax)</option>
                                    <option value="5" {{ old('tax_rate') == 5 ? 'selected' : '' }}>5%</option>
                                    <option value="10" {{ old('tax_rate') == 10 ? 'selected' : '' }}>10%</option>
                                    <option value="15" {{ old('tax_rate') == 15 ? 'selected' : '' }}>15%</option>
                                    <option value="20" {{ old('tax_rate') == 20 ? 'selected' : '' }}>20%</option>
                                </select>
                                <div class="form-text">Select applicable tax rate</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Section 4: Status & Visibility -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-gear text-primary me-2"></i>
                            Status & Visibility
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">
                                Product Status <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @if(isset($errors) && $errors->has('status')) is-invalid @endif" 
                                    id="status" 
                                    name="status" 
                                    required>
                                <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @if(isset($errors) && $errors->has('status'))
                                <div class="invalid-feedback">{{ $errors->first('status') }}</div>
                            @endif
                            <div class="form-text">Active products are available for orders</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Quick Actions</label>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="preview-product">
                                    <i class="bi bi-eye me-2"></i>
                                    Preview Product
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="save-draft">
                                    <i class="bi bi-save me-2"></i>
                                    Save as Draft
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle me-2"></i>
                                Create Product
                            </button>
                            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-2"></i>
                                Cancel
                            </a>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Fields marked with <span class="text-danger">*</span> are required
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@section('styles')
<style>
/* Product Create Page Specific Styles */
.card {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    box-shadow: var(--card-shadow);
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid var(--border-color);
    padding: 1rem 1.25rem;
}

.card-title {
    font-weight: 600;
    color: var(--dark-text);
    font-size: 1.1rem;
}

.form-label {
    font-weight: 500;
    color: var(--dark-text);
    margin-bottom: 0.5rem;
}

.form-text {
    font-size: 0.875rem;
    color: var(--medium-text);
    margin-top: 0.25rem;
}

.input-group-text {
    background-color: #f8f9fa;
    border-color: var(--border-color);
    color: var(--medium-text);
}

.btn-lg {
    padding: 0.75rem 1.5rem;
    font-weight: 600;
}

/* Responsive Design */
@media (max-width: 991.98px) {
    .col-lg-4 {
        order: -1;
        margin-bottom: 1.5rem;
    }
}

@media (max-width: 767.98px) {
    .card-body {
        padding: 1rem;
    }
    
    .btn-lg {
        padding: 0.5rem 1rem;
    }
}

/* Focus States */
.form-control:focus,
.form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(44, 110, 73, 0.15);
}

/* Loading States */
.form-control:disabled,
.form-select:disabled {
    background-color: #f8f9fa;
    opacity: 0.6;
}

/* Validation States */
.form-control.is-valid,
.form-select.is-valid {
    border-color: var(--success);
    box-shadow: 0 0 0 0.2rem rgba(82, 183, 136, 0.15);
}

.form-control.is-invalid,
.form-select.is-invalid {
    border-color: var(--danger);
    box-shadow: 0 0 0 0.2rem rgba(231, 111, 81, 0.15);
}

.invalid-feedback {
    color: var(--danger);
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

/* Breadcrumb Styling */
.breadcrumb {
    background: transparent;
    padding: 0.5rem 0;
    margin-bottom: 0;
}

.breadcrumb-item + .breadcrumb-item::before {
    content: ">";
    color: var(--medium-text);
}

.breadcrumb-item a {
    color: var(--primary);
    text-decoration: none;
}

.breadcrumb-item a:hover {
    color: var(--primary-dark);
}

.breadcrumb-item.active {
    color: var(--medium-text);
}
</style>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Product create page script loaded');
    
    // Form validation
    const form = document.getElementById('product-form');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    }
    
    // Auto-generate SKU functionality
    const generateSkuBtn = document.getElementById('generate-sku');
    const nameInput = document.getElementById('name');
    const skuInput = document.getElementById('sku');
    
    if (generateSkuBtn && nameInput && skuInput) {
        generateSkuBtn.addEventListener('click', function() {
            if (nameInput.value.trim()) {
                const sku = nameInput.value
                    .toLowerCase()
                    .replace(/[^a-z0-9]/g, '')
                    .substring(0, 20);
                skuInput.value = sku;
                
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
        console.log('Category and subcategory selects found');
        
        categorySelect.addEventListener('change', function() {
            const categoryId = this.value;
            console.log('Category changed to:', categoryId);
            
            // Clear subcategory options
            subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
            
            if (categoryId) {
                console.log('Fetching subcategories for category:', categoryId);
                
                // Show loading state
                subcategorySelect.disabled = true;
                subcategorySelect.innerHTML = '<option value="">Loading...</option>';
                
                // Fetch subcategories for the selected category
                fetch(`{{ route('products.get-subcategories') }}?category_id=${categoryId}`)
                    .then(response => {
                        console.log('Response status:', response.status);
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Subcategories data:', data);
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
                            console.log('Subcategories loaded successfully');
                        }
                    })
                    .catch(error => {
                        console.error('Error loading subcategories:', error);
                        subcategorySelect.disabled = false;
                        subcategorySelect.innerHTML = '<option value="">Error loading subcategories</option>';
                    });
            }
        });
        
        // Handle subcategory selection - Keep both values separate
        subcategorySelect.addEventListener('change', function() {
            const subcategoryId = this.value;
            console.log('Subcategory changed to:', subcategoryId);
            // Keep both category and subcategory values independent
        });
    }
    
    // Price formatting
    const priceInput = document.getElementById('selling_price');
    if (priceInput) {
        priceInput.addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    }
    
    // Discount percentage formatting
    const discountInput = document.getElementById('discount_percentage');
    if (discountInput) {
        discountInput.addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    }
    
    // Preview product functionality
    const previewBtn = document.getElementById('preview-product');
    if (previewBtn) {
        previewBtn.addEventListener('click', function() {
            // Show preview modal or alert
            alert('Product preview functionality will be implemented here');
        });
    }
    
    // Save draft functionality
    const saveDraftBtn = document.getElementById('save-draft');
    if (saveDraftBtn) {
        saveDraftBtn.addEventListener('click', function() {
            // Save as draft functionality
            alert('Save as draft functionality will be implemented here');
        });
    }
    
    // Real-time validation
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        field.addEventListener('blur', function() {
            if (this.value.trim() === '') {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    });
});
</script>
@endsection 
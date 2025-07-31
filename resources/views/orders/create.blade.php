@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Create New Order</h1>
    <a href="{{ route('orders.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Orders
    </a>
</div>

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<!-- Info Banner for Warehouse to Retailer Logic -->
<div class="alert alert-info">
    <div class="d-flex align-items-start">
        <i class="bi bi-info-circle-fill me-3 mt-1" style="font-size: 1.2rem;"></i>
        <div>
            <h6 class="mb-1">Order Fulfillment Logic</h6>
            <p class="mb-2"><strong>Warehouse Orders:</strong> Products will be picked directly from the warehouse and shipped to customers.</p>
            <p class="mb-0"><strong>Retailer Orders:</strong> Only retailers with ALL selected products in stock will be available for selection. Products must be transferred from warehouse to retailer first.</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-light mb-4">
            <i class="bi bi-exclamation-circle text-danger"></i> Fields marked with <span class="text-danger">*</span> are required and must be filled before submitting the form.
        </div>
        
        <form action="{{ route('orders.store') }}" method="POST" id="orderForm">
            @csrf
            
            <!-- Step 1: Customer Selection -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-1-circle me-2"></i>Step 1: Select Customer
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customer_id" class="form-label">Customer <span class="text-danger">*</span></label>
                                <select name="customer_id" id="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                                    <option value="">Select Customer</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                            {{ $customer->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('customer_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="order_date" class="form-label">Order Date <span class="text-danger">*</span></label>
                                <input type="date" name="order_date" id="order_date" class="form-control @error('order_date') is-invalid @enderror" 
                                    value="{{ old('order_date', date('Y-m-d')) }}" required>
                                @error('order_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Product Selection -->
            <div class="card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-2-circle me-2"></i>Step 2: Select Products <span class="text-danger">*</span>
                    </h5>
                    <button type="button" id="add-item" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i>Add Item
                    </button>
                </div>
                <div class="card-body">
                    <div id="order-items">
                        <div class="order-item card mb-3">
                            <div class="card-body">
                                <!-- First Row: Product and Fulfillment Location -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Product <span class="text-danger">*</span></label>
                                        <select name="products[0][product_id]" class="form-select product-select" required>
                                            <option value="">Select Product</option>
                                            @foreach($products as $product)
                                                <option value="{{ $product->id }}"
                                                        data-price="{{ $product->selling_price }}"
                                                        data-stock="{{ $product->current_stock }}">
                                                    {{ $product->name }} ({{ $product->current_stock }} in stock) - ${{ number_format($product->selling_price, 2) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="stock-info form-text mt-1"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Fulfillment Location <span class="text-danger">*</span></label>
                                        <select name="products[0][fulfillment_location_id]" class="form-select fulfillment-location-select" required>
                                            <option value="">Select product first</option>
                                        </select>
                                        <!-- Hidden input will be updated dynamically to store location type (warehouse / retailer / other) -->
                                        <input type="hidden" name="products[0][fulfillment_location_type]" class="fulfillment-location-type">
                                        <div class="location-stock-info form-text mt-1"></div>
                                    </div>
                                </div>

                                <!-- Second Row: Quantity, Unit Price, Subtotal, and Remove Button -->
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Quantity <span class="text-danger">*</span></label>
                                        <input type="number" name="products[0][quantity]" class="form-control item-quantity" min="1" value="1" required>
                                        <div class="invalid-feedback quantity-error"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Unit Price</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" name="products[0][unit_price]" class="form-control item-unit-price" placeholder="0.00" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Subtotal</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control item-subtotal" placeholder="0.00" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="button" class="btn btn-danger remove-item w-100">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Additional Information -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-3-circle me-2"></i>Step 3: Additional Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="notes" class="form-label">Order Notes</label>
                        <textarea name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror" rows="3" 
                            placeholder="Add any special instructions or notes for this order...">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-receipt me-2"></i>Order Summary
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Total: $<span id="order-total">0.00</span></h4>
                        <button type="submit" class="btn btn-success" id="submitOrder">
                            <i class="bi bi-check-circle me-1"></i>Create Order
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@section('styles')
<style>
    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid rgba(0,0,0,.125);
    }
    .card-title {
        color: #495057;
        font-size: 1.1rem;
    }
    .form-label {
        color: #495057;
        margin-bottom: 0.5rem;
    }
    .stock-info {
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }
    .btn-danger {
        padding: 0.375rem 0.75rem;
    }
    .btn-danger i {
        font-size: 1rem;
    }
    .order-item {
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        transition: all 0.2s ease-in-out;
    }
    .order-item:hover {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    .input-group-text {
        background-color: #f8f9fa;
        border-color: #ced4da;
    }
    .form-control:read-only {
        background-color: #f8f9fa;
        cursor: default;
    }
    .form-control.item-subtotal {
        font-size: 1rem;
        font-weight: 600;
        text-align: right;
    }
    .invalid-feedback {
        font-size: 0.875rem;
    }
    
    /* Required field indicator styling - consistent with supply page */
    .text-danger {
        color: var(--danger) !important;
    }
    
    .form-label .text-danger {
        font-weight: 600;
        margin-left: 2px;
    }
    
    /* Enhanced focus states for required fields */
    .form-control:required:focus,
    .form-select:required:focus {
        border-color: var(--danger);
        box-shadow: 0 0 0 0.2rem rgba(231, 111, 81, 0.25);
    }
    
    /* Subtle indicator for required field groups */
    h5 .text-danger {
        font-size: 0.8em;
        vertical-align: super;
        margin-left: 4px;
    }
    
    @media (max-width: 768px) {
        /* Ensure required indicators are visible on mobile */
        .form-label .text-danger {
            font-size: 0.9em;
        }
        
        h5 .text-danger {
            font-size: 0.7em;
        }
    }
</style>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let itemIndex = 0;
    let calculateTimeout;
    let selectedProducts = new Set();

    // Show current stock of selected product in the small helper text below the select.
    function displayProductStock(productSelect) {
        const stockInfoDiv   = productSelect.closest('.order-item').querySelector('.stock-info');
        const selectedOption = productSelect.options[productSelect.selectedIndex];

        if (selectedOption && selectedOption.dataset.stock) {
            stockInfoDiv.innerHTML = `${selectedOption.dataset.stock} units in stock`;
        } else {
            stockInfoDiv.innerHTML = '';
        }
    }

    // Function to update fulfillment location options for a product
    async function updateFulfillmentLocations(productSelect) {
        const productId = productSelect.value;
        const fulfillmentSelect = productSelect.closest('.order-item').querySelector('.fulfillment-location-select');
        const locationStockInfo = productSelect.closest('.order-item').querySelector('.location-stock-info');
        const quantityInput = productSelect.closest('.order-item').querySelector('.item-quantity');
        
        if (!productId) {
            fulfillmentSelect.innerHTML = '<option value="">Select product first</option>';
            locationStockInfo.innerHTML = '';
            quantityInput.value = '1';
            quantityInput.removeAttribute('max');
            const locTypeInput = productSelect.closest('.order-item').querySelector('.fulfillment-location-type');
            if (locTypeInput) locTypeInput.value = '';
            return;
        }

        try {
            // Create URLSearchParams to properly encode the array
            const params = new URLSearchParams();
            params.append('product_ids[]', productId);
            
            const response = await fetch(`/api/fulfillment-locations?${params.toString()}`);
            const data = await response.json();
            
            // Update warehouse options with stock info
            let warehouseHtml = '<option value="">Select fulfillment location</option>';
            
            if (data.available_locations && data.available_locations.length > 0) {
                data.available_locations.forEach(location => {
                    warehouseHtml += `<option value="${location.id}" data-location-type="${location.type}" data-available-stock="${location.available_quantity}">`;
                    warehouseHtml += `${location.name} (${location.type}) - ${location.available_quantity} available`;
                    warehouseHtml += `</option>`;
                });
            } else {
                warehouseHtml += '<option value="" disabled>No locations have sufficient stock for this product</option>';
            }

            fulfillmentSelect.innerHTML = warehouseHtml;
            locationStockInfo.innerHTML = '';
            
            // Reset quantity input
            quantityInput.value = '1';
            quantityInput.removeAttribute('max');
        } catch (error) {
            console.error('Error updating fulfillment locations:', error);
            fulfillmentSelect.innerHTML = '<option value="">Error loading locations</option>';
            locationStockInfo.innerHTML = '<span class="text-danger">Error loading locations</span>';
        }
    }

    // Function to update stock info when fulfillment location changes
    function updateStockInfo(fulfillmentSelect) {
        const item = fulfillmentSelect.closest('.order-item');
        const productSelect = item.querySelector('.product-select');
        const quantityInput = item.querySelector('.item-quantity');
        const locationStockInfo = item.querySelector('.location-stock-info');
        const quantityError = item.querySelector('.quantity-error');
        const locationTypeInput = item.querySelector('.fulfillment-location-type');
        
        const selectedOption = fulfillmentSelect.options[fulfillmentSelect.selectedIndex];
        if (selectedOption && selectedOption.dataset.availableStock) {
            const availableStock = parseInt(selectedOption.dataset.availableStock);
            quantityInput.setAttribute('max', availableStock);
            
            // Validate current quantity
            const currentQuantity = parseInt(quantityInput.value) || 0;
            if (currentQuantity > availableStock) {
                quantityInput.value = availableStock;
                quantityInput.classList.add('is-invalid');
                quantityError.textContent = `Only ${availableStock} units available`;
            } else {
                quantityInput.classList.remove('is-invalid');
                quantityError.textContent = '';
            }
            
            locationStockInfo.innerHTML = `<span class="text-${availableStock < 5 ? 'warning' : 'success'}">${availableStock} units available</span>`;
            
            // Persist selected location type to hidden input so backend knows where stock is reserved.
            if (locationTypeInput) {
                locationTypeInput.value = selectedOption.dataset.locationType || '';
            }
        } else {
            quantityInput.removeAttribute('max');
            locationStockInfo.innerHTML = '';
            quantityInput.classList.remove('is-invalid');
            quantityError.textContent = '';
            
            if (locationTypeInput) {
                locationTypeInput.value = '';
            }
        }
        
        updateSubtotal(item);
    }

    // Add event listeners for product selection
    document.querySelectorAll('.product-select').forEach(select => {
        select.addEventListener('change', function() {
            displayProductStock(this);
            updateFulfillmentLocations(this);
            updateSubtotal(this.closest('.order-item'));
        });
    });

    // Add event listeners for fulfillment location selection
    document.querySelectorAll('.fulfillment-location-select').forEach(select => {
        select.addEventListener('change', function() {
            updateStockInfo(this);
        });
    });

    // Add event listeners for quantity input
    document.querySelectorAll('.item-quantity').forEach(input => {
        input.addEventListener('input', function() {
            const max = parseInt(this.getAttribute('max')) || 0;
            const value = parseInt(this.value) || 0;
            const item = this.closest('.order-item');
            const quantityError = item.querySelector('.quantity-error');
            
            // Reset validation state
            this.classList.remove('is-invalid');
            quantityError.textContent = '';
            
            // Validate input
            if (isNaN(value)) {
                this.classList.add('is-invalid');
                quantityError.textContent = 'Please enter a valid number';
            } else if (value <= 0) {
                this.classList.add('is-invalid');
                quantityError.textContent = 'Quantity must be at least 1';
            } else if (value > max) {
                this.classList.add('is-invalid');
                quantityError.textContent = `Only ${max} units available`;
            }
            
            updateSubtotal(item);
        });

        // Add keydown event to prevent non-numeric input
        input.addEventListener('keydown', function(e) {
            // Allow: backspace, delete, tab, escape, enter
            if ([46, 8, 9, 27, 13].indexOf(e.keyCode) !== -1 ||
                // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode >= 35 && e.keyCode <= 39) ||
                // Allow: home, end, left, right
                (e.keyCode >= 35 && e.keyCode <= 39)) {
                return;
            }
            // Block any non-numeric input
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && 
                (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
    });

    // Add item button
    document.getElementById('add-item').addEventListener('click', function() {
        itemIndex++;
        const template = document.querySelector('.order-item').cloneNode(true);
        
        // Update name attributes with new index
        template.querySelectorAll('[name]').forEach(input => {
            input.name = input.name.replace('[0]', `[${itemIndex}]`);
            if (input.classList.contains('item-quantity')) {
                input.value = '1';
                input.removeAttribute('max');
            }
        });
        
        // Clear any selected values
        template.querySelector('.product-select').selectedIndex = 0;
        template.querySelector('.fulfillment-location-select').innerHTML = '<option value="">Select product first</option>';
        template.querySelector('.item-subtotal').value = '';
        template.querySelector('.item-unit-price').value = '';
        template.querySelector('.stock-info').innerHTML = '';
        template.querySelector('.location-stock-info').innerHTML = '';
        template.querySelector('.quantity-error').textContent = '';
        template.querySelector('.fulfillment-location-type').value = '';
        
        // Required field indicators are preserved in the cloned template
        
        document.getElementById('order-items').appendChild(template);
        setupEventListeners();
        
        // Scroll to new item
        template.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    // Setup event listeners for all items
    function setupEventListeners() {
        // Remove item button
        document.querySelectorAll('.remove-item').forEach(button => {
            button.onclick = function() {
                if (document.querySelectorAll('.order-item').length > 1) {
                    const item = this.closest('.order-item');
                    item.style.opacity = '0.5';
                    setTimeout(() => {
                        item.remove();
                        updateTotal();
                    }, 150);
                }
            };
        });
        
        // Product selection change
        document.querySelectorAll('.product-select').forEach(select => {
            select.addEventListener('change', function() {
                displayProductStock(this);
                updateFulfillmentLocations(this);
                updateSubtotal(this.closest('.order-item'));
            });
        });
        
        // Fulfillment location change
        document.querySelectorAll('.fulfillment-location-select').forEach(select => {
            select.addEventListener('change', function() {
                updateStockInfo(this);
            });
        });
        
        // Quantity change
        document.querySelectorAll('.item-quantity').forEach(input => {
            input.addEventListener('input', function() {
                const max = parseInt(this.getAttribute('max')) || 0;
                const value = parseInt(this.value) || 0;
                const item = this.closest('.order-item');
                const quantityError = item.querySelector('.quantity-error');
                
                // Reset validation state
                this.classList.remove('is-invalid');
                quantityError.textContent = '';
                
                // Validate input
                if (isNaN(value)) {
                    this.classList.add('is-invalid');
                    quantityError.textContent = 'Please enter a valid number';
                } else if (value <= 0) {
                    this.classList.add('is-invalid');
                    quantityError.textContent = 'Quantity must be at least 1';
                } else if (value > max) {
                    this.classList.add('is-invalid');
                    quantityError.textContent = `Only ${max} units available`;
                }
                
                updateSubtotal(item);
            });
        });
    }
    
    // Update subtotal for an item
    function updateSubtotal(item) {
        const select = item.querySelector('.product-select');
        const quantityInput = item.querySelector('.item-quantity');
        const unitPriceInput = item.querySelector('.item-unit-price');
        const subtotalInput = item.querySelector('.item-subtotal');
        
        if (select.selectedIndex > 0) {
            const option = select.options[select.selectedIndex];
            const price = parseFloat(option.dataset.price);
            const quantity = parseInt(quantityInput.value) || 0;
            
            unitPriceInput.value = price.toFixed(2);
            subtotalInput.value = (price * quantity).toFixed(2);
            
            clearTimeout(calculateTimeout);
            calculateTimeout = setTimeout(updateTotal, 100);
        } else {
            unitPriceInput.value = '';
            subtotalInput.value = '';
        }
    }
    
    // Update total order amount
    function updateTotal() {
        const total = Array.from(document.querySelectorAll('.item-subtotal'))
            .reduce((sum, input) => sum + (parseFloat(input.value) || 0), 0);
        document.getElementById('order-total').textContent = total.toFixed(2);
    }
    
    // Form submission validation
    document.getElementById('orderForm').addEventListener('submit', function(e) {
        const items = document.querySelectorAll('.order-item');
        let hasErrors = false;

        items.forEach(item => {
            const productSelect = item.querySelector('.product-select');
            const fulfillmentSelect = item.querySelector('.fulfillment-location-select');
            const quantityInput = item.querySelector('.item-quantity');
            
            // Reset validation states
            productSelect.classList.remove('is-invalid');
            fulfillmentSelect.classList.remove('is-invalid');
            quantityInput.classList.remove('is-invalid');
            
            if (!productSelect.value) {
                hasErrors = true;
                productSelect.classList.add('is-invalid');
            }
            
            if (!fulfillmentSelect.value) {
                hasErrors = true;
                fulfillmentSelect.classList.add('is-invalid');
            }
            
            if (productSelect.value && fulfillmentSelect.value) {
                const selectedOption = fulfillmentSelect.options[fulfillmentSelect.selectedIndex];
                const availableStock = parseInt(selectedOption.dataset.availableStock);
                const quantity = parseInt(quantityInput.value);
                
                if (isNaN(quantity) || quantity <= 0) {
                    hasErrors = true;
                    quantityInput.classList.add('is-invalid');
                    item.querySelector('.quantity-error').textContent = 'Quantity must be at least 1';
                } else if (quantity > availableStock) {
                    hasErrors = true;
                    quantityInput.classList.add('is-invalid');
                    item.querySelector('.quantity-error').textContent = `Only ${availableStock} units available`;
                }
            }
        });

        if (hasErrors) {
            e.preventDefault();
            alert('Please correct the errors before submitting');
        }
    });
    
    // Initial setup
    setupEventListeners();
    updateTotal();
});
</script>
@endsection
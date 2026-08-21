@extends('layouts.app')
@section('page-header')
<div class="mb-4">
    <h1>Create New Order</h1>
</div>
@endsection

@section('content')

<div class="order-form">

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="order-notice">
        <i class="bi bi-info-circle"></i>
        <div>
            <strong>Warehouse orders</strong> are picked directly from the warehouse and shipped to the customer.
            <strong>Retailer orders</strong> only list retailers holding every selected product — stock must be
            transferred from a warehouse to the retailer first.
        </div>
    </div>

    <form action="{{ route('orders.store') }}" method="POST" id="orderForm">
        @csrf

        {{-- Section 1: Customer --}}
        <div class="card order-card mb-4">
            <div class="card-header order-card__header">
                <span class="order-card__step">1</span>
                <div>
                    <h2 class="order-card__title">Customer Details</h2>
                    <p class="order-card__subtitle">Who the order is for, and when it was placed.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="order-panel">
                <div class="row g-3">
                    <div class="col-md-6">
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

                    <div class="col-md-6">
                        <label for="order_date" class="form-label">Order Date <span class="text-danger">*</span></label>
                        <input type="date" name="order_date" id="order_date" class="form-control @error('order_date') is-invalid @enderror"
                            value="{{ old('order_date', date('Y-m-d')) }}" required>
                        @error('order_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Only shown where channels are actually in use. Selecting one can
                         change every price on the form, so it sits with the customer. --}}
                    @if($salesChannels->isNotEmpty())
                    <div class="col-md-6">
                        <label for="sales_channel_id" class="form-label">Sales Channel</label>
                        <select name="sales_channel_id" id="sales_channel_id" class="form-select @error('sales_channel_id') is-invalid @enderror">
                            <option value="">Default</option>
                            @foreach($salesChannels as $channel)
                                <option value="{{ $channel->id }}" {{ old('sales_channel_id') == $channel->id ? 'selected' : '' }}>
                                    {{ $channel->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('sales_channel_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @endif
                </div>
                </div>
            </div>
        </div>

        {{-- Section 2: Products --}}
        <div class="card order-card mb-4">
            <div class="card-header order-card__header">
                <span class="order-card__step">2</span>
                <div>
                    <h2 class="order-card__title">Order Items <span class="text-danger">*</span></h2>
                    <p class="order-card__subtitle">Add each product being sold, with its fulfilment location and quantity.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="order-panel">

                <div id="order-items">
                    <div class="order-item">
                        <div class="order-item__bar">
                            <span class="order-item__label">Item</span>
                            <button type="button" class="remove-item" title="Remove this item" aria-label="Remove this item">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label">Product <span class="text-danger">*</span></label>
                                <select name="products[0][product_id]" class="form-select product-select" required>
                                    <option value="">Select Product</option>
                                    {{-- No price in the markup: what this costs depends on the
                                         customer, the channel and the quantity, which are not
                                         known until the form is filled in. Fetched per line. --}}
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}"
                                                data-stock="{{ $product->current_stock }}">
                                            {{ $product->name }} ({{ $product->current_stock }} in stock)
                                        </option>
                                    @endforeach
                                </select>
                                <div class="stock-info form-text mt-1"></div>
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label">Fulfillment Location <span class="text-danger">*</span></label>
                                <select name="products[0][fulfillment_location_id]" class="form-select fulfillment-location-select" required>
                                    <option value="">Select product first</option>
                                </select>
                                {{-- Hidden input is updated dynamically to store location type (warehouse / retailer / other) --}}
                                <input type="hidden" name="products[0][fulfillment_location_type]" class="fulfillment-location-type">
                                <div class="location-stock-info form-text mt-1"></div>
                            </div>
                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <label class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" name="products[0][quantity]" class="form-control item-quantity" min="1" value="1" required>
                                <div class="invalid-feedback quantity-error"></div>
                            </div>
                            <div class="col-lg-4 col-md-4 col-sm-6">
                                <label class="form-label">Unit Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="text" name="products[0][unit_price]" class="form-control item-unit-price" placeholder="0.00" readonly>
                                </div>
                                {{-- Where the figure came from: an agreed list price, or one
                                     derived from cost because nobody has set one. --}}
                                <div class="price-source-note form-text mt-1"></div>
                            </div>
                            <div class="col-lg-5 col-md-4">
                                <label class="form-label">Subtotal</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="text" class="form-control item-subtotal subtotal-highlight" placeholder="0.00" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" id="add-item" class="btn btn-outline-primary order-add-btn">
                    <i class="bi bi-plus-lg"></i> Add Another Item
                </button>
                </div>
            </div>
        </div>

        {{-- Section 3: Additional information --}}
        <div class="card order-card mb-4">
            <div class="card-header order-card__header">
                <span class="order-card__step">3</span>
                <div>
                    <h2 class="order-card__title">Additional Information</h2>
                    <p class="order-card__subtitle">Anything worth remembering about this order.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="order-panel">
                    <label for="notes" class="form-label">Order Notes</label>
                    <textarea name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror" rows="3"
                        placeholder="Add any special instructions or notes for this order (optional)">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Section 4: Review & submit --}}
        <div class="card order-card order-summary mb-4">
            <div class="card-body">
                <div class="order-summary__inner">
                    <div>
                        <span class="order-summary__label">Order Total</span>
                        <span class="order-summary__value">$<span id="order-total">0.00</span></span>
                    </div>
                    <div class="order-summary__actions">
                        <a href="{{ route('orders.index') }}" class="btn btn-danger">Cancel</a>
                        <button type="submit" class="btn btn-primary" id="submitOrder">
                            <i class="bi bi-check2-circle"></i> Create Order
                        </button>
                    </div>
                </div>
                <p class="order-summary__hint">
                    Fields marked <span class="text-danger">*</span> are required and must be filled before
                    submitting the form.
                </p>
            </div>
        </div>
    </form>
</div>

@endsection

@section('styles')
<style>
    /* ---- Page shell ------------------------------------------------- */
    .order-form {
        width: 100%;
    }

    .order-notice {
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
        padding: 0.85rem 1.1rem;
        margin-bottom: 1.5rem;
        border-radius: 6px;
        border: 1px solid #e3e8e4;
        background-color: #f8f9fa;
        color: var(--dark-text);
        font-size: 0.925rem;
        line-height: 1.5;
    }

    .order-notice .bi {
        color: #6c757d;
        font-size: 1.05rem;
        line-height: 1.4;
    }

    /* ---- Section cards ---------------------------------------------- */
    .order-card:hover {
        /* keep sections calm; no lift on a form page */
        box-shadow: var(--card-shadow);
    }

    .order-card__header {
        display: flex;
        align-items: flex-start;
        gap: 0.85rem;
        border-bottom: 0;
        padding-bottom: 0.35rem;
    }

    /* Light inner surface for a section's main content */
    .order-panel {
        padding: 1.1rem;
        border-radius: 6px;
        background-color: #f5f8f6;
    }

    .order-card__step {
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

    .order-card__title {
        margin: 0;
        font-size: 1.02rem;
        font-weight: 600;
        color: var(--dark-text);
        letter-spacing: 0.01em;
    }

    .order-card__subtitle {
        margin: 0.15rem 0 0;
        font-size: 0.825rem;
        font-weight: 400;
        color: #6c757d;
    }

    /* ---- Item rows --------------------------------------------------- */
    #order-items {
        counter-reset: order-item;
    }

    .order-item {
        position: relative;
        padding: 1rem 1.1rem 1.1rem;
        margin-bottom: 1rem;
        border: 1px solid #e3e8e4;
        border-radius: 6px;
        background-color: #fff;
        transition: opacity 0.3s ease, border-color 0.2s ease;
    }

    .order-item:focus-within {
        border-color: var(--primary-light);
    }

    .order-item__bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.85rem;
    }

    .order-item__label {
        counter-increment: order-item;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        color: var(--primary);
    }

    .order-item__label::after {
        content: " " counter(order-item);
    }

    .order-item .remove-item {
        border: 0;
        background: transparent;
        color: #9aa0a6;
        line-height: 1;
        padding: 0.3rem 0.4rem;
        border-radius: 4px;
        font-size: 0.8rem;
        transition: color 0.2s ease, background-color 0.2s ease;
    }

    .order-item .remove-item:hover {
        color: var(--danger);
        background-color: rgba(231, 111, 81, 0.1);
    }

    .order-item .form-label {
        font-size: 0.82rem;
        font-weight: 500;
        margin-bottom: 0.3rem;
    }

    .stock-info,
    .location-stock-info {
        font-size: 0.8rem;
    }

    .input-group-text {
        background-color: #f8f9fa;
        border-color: #ced4da;
    }

    .form-control:read-only {
        background-color: #f8f9fa;
        cursor: default;
    }

    .subtotal-highlight {
        font-weight: 600;
        background-color: #f1f5f2;
    }

    .item-subtotal {
        transition: all 0.3s ease;
    }

    .invalid-feedback {
        font-size: 0.8rem;
    }

    .order-add-btn {
        border-style: dashed;
        width: 100%;
        padding: 0.6rem 1rem;
        font-weight: 500;
        background-color: #fff;
    }

    .order-add-btn:hover {
        background-color: var(--primary);
    }

    /* ---- Summary card ------------------------------------------------ */
    .order-summary__inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .order-summary__label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        color: #6c757d;
    }

    .order-summary__value {
        display: block;
        margin-top: 0.15rem;
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--dark-text);
    }

    #order-total {
        transition: all 0.3s ease;
    }

    .order-summary__actions {
        display: flex;
        gap: 0.6rem;
    }

    .order-summary__hint {
        margin: 0.9rem 0 0;
        padding-top: 0.9rem;
        border-top: 1px solid #e3e8e4;
        font-size: 0.82rem;
        color: #6c757d;
    }

    /* ---- Required-field cues ----------------------------------------- */
    .text-danger {
        color: var(--danger) !important;
    }

    .form-label .text-danger {
        font-weight: 600;
        margin-left: 2px;
    }

    .form-control:required:focus,
    .form-select:required:focus {
        border-color: var(--danger);
        box-shadow: 0 0 0 0.2rem rgba(231, 111, 81, 0.25);
    }

    .order-card__title .text-danger {
        font-size: 0.8em;
        vertical-align: super;
        margin-left: 2px;
    }

    @media (max-width: 768px) {
        .order-item {
            padding: 0.9rem;
        }

        .order-summary__inner {
            align-items: flex-start;
            flex-direction: column;
        }

        .order-summary__actions {
            width: 100%;
        }

        .order-summary__actions .btn {
            flex: 1;
        }

        .form-label .text-danger {
            font-size: 0.9em;
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
    
    // Ask the server what this line costs.
    //
    // The price depends on who is buying, through which channel, and how many
    // they want, so it cannot be baked into the option list. Resolved server
    // side by the same rules that validate the submission, which is what keeps
    // the figure shown and the figure charged from drifting apart.
    async function fetchUnitPrice(productId, quantity) {
        const params = new URLSearchParams({ product_id: productId, quantity: quantity || 1 });

        const customerId = document.querySelector('[name="customer_id"]')?.value;
        if (customerId) params.append('customer_id', customerId);

        const channelId = document.querySelector('[name="sales_channel_id"]')?.value;
        if (channelId) params.append('sales_channel_id', channelId);

        const response = await fetch(`{{ route('orders.price-quote') }}?${params}`, {
            headers: { 'Accept': 'application/json' },
        });

        if (!response.ok) throw new Error('Could not fetch price');

        return response.json();
    }

    // Update subtotal for an item
    async function updateSubtotal(item) {
        const select = item.querySelector('.product-select');
        const quantityInput = item.querySelector('.item-quantity');
        const unitPriceInput = item.querySelector('.item-unit-price');
        const subtotalInput = item.querySelector('.item-subtotal');
        const priceNote = item.querySelector('.price-source-note');

        if (select.selectedIndex <= 0) {
            unitPriceInput.value = '';
            subtotalInput.value = '';
            if (priceNote) priceNote.textContent = '';
            return;
        }

        const quantity = parseInt(quantityInput.value) || 0;

        try {
            const quote = await fetchUnitPrice(select.value, quantity);

            if (!quote.priced) {
                unitPriceInput.value = '';
                subtotalInput.value = '';
                if (priceNote) priceNote.textContent = quote.message || 'No price agreed yet.';
                return;
            }

            unitPriceInput.value = quote.unit_price.toFixed(2);
            subtotalInput.value = (quote.unit_price * quantity).toFixed(2);

            if (priceNote) {
                // Say where the number came from. A derived price is a stopgap
                // for a product nobody has priced, not an agreed rate.
                priceNote.textContent = quote.derived
                    ? 'Derived from cost - no agreed price yet'
                    : (quote.price_list_name ? `From ${quote.price_list_name}` : '');
            }
        } catch (e) {
            unitPriceInput.value = '';
            subtotalInput.value = '';
            if (priceNote) priceNote.textContent = 'Could not fetch the price. Try again.';
        }

        clearTimeout(calculateTimeout);
        calculateTimeout = setTimeout(updateTotal, 100);
    }

    // Changing the customer or the channel can change every price on the form.
    ['customer_id', 'sales_channel_id'].forEach(function (field) {
        const input = document.querySelector(`[name="${field}"]`);
        if (!input) return;

        input.addEventListener('change', function () {
            document.querySelectorAll('.order-item').forEach(updateSubtotal);
        });
    });
    
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
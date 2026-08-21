@extends('layouts.app')
@section('styles')
<style>
.stock-info {
    background: linear-gradient(135deg, #e3f2fd 0%, #f0f8ff 100%);
    border-radius: 6px;
    padding: 10px 12px;
    border-left: 4px solid #2196f3;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stock-badge .badge {
    font-size: 0.8rem;
    padding: 0.6rem 0.8rem;
    font-weight: 600;
}

.quantity-error {
    background: linear-gradient(135deg, #ffebee 0%, #fce4ec 100%);
    border-radius: 6px;
    padding: 8px 10px;
    border-left: 4px solid #f44336;
    box-shadow: 0 2px 4px rgba(244, 67, 54, 0.1);
}

.quantity-success {
    background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%);
    border-radius: 6px;
    padding: 8px 10px;
    border-left: 4px solid #4caf50;
    box-shadow: 0 2px 4px rgba(76, 175, 80, 0.1);
}

.is-invalid {
    border-color: #f44336 !important;
    box-shadow: 0 0 0 0.2rem rgba(244, 67, 54, 0.25) !important;
}

.is-valid {
    border-color: #4caf50 !important;
    box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25) !important;
}

.stock-text {
    font-weight: 600;
    color: #1976d2;
}

.info-banner {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.loading-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #2196f3;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.warehouse-selection-warning {
    background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%);
    border: 1px solid #ffeaa7;
    border-radius: 6px;
    padding: 12px;
    margin-top: 10px;
}

.product-grid {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 15px;
    align-items: end;
}

@media (max-width: 768px) {
    .product-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }
}

/* ==========================================================================
   Section styling — mirrors supplies/create so both "create" forms read the
   same way. Scoped to .transfer-form.
   ========================================================================== */
.transfer-notice {
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

.transfer-notice .bi {
    color: #6c757d;
    font-size: 1.05rem;
    line-height: 1.4;
}

/* ---- Section cards ---------------------------------------------- */
.transfer-form .transfer-card:hover {
    /* keep sections calm; no lift on a form page */
    box-shadow: var(--card-shadow);
}

.transfer-form .transfer-card__header {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    border-bottom: 0;
    padding-bottom: 0.35rem;
    background-color: var(--light-panel);
}

/* Light inner surface for a section's main content */
.transfer-form .transfer-panel {
    padding: 1.1rem;
    border-radius: 6px;
    background-color: #f5f8f6;
}

.transfer-form .transfer-card__step {
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

.transfer-form .transfer-card__title {
    margin: 0;
    font-size: 1.02rem;
    font-weight: 600;
    color: var(--dark-text);
    letter-spacing: 0.01em;
}

.transfer-form .transfer-card__subtitle {
    margin: 0.15rem 0 0;
    font-size: 0.825rem;
    font-weight: 400;
    color: #6c757d;
}

/* ---- Item rows --------------------------------------------------- */
#items-container {
    counter-reset: transfer-item;
}

.transfer-form .item-row {
    position: relative;
    padding: 1rem 1.1rem 1.1rem;
    margin-bottom: 1rem;
    border: 1px solid #e3e8e4;
    border-radius: 6px;
    background: #fff;
    transition: opacity 0.3s ease, border-color 0.2s ease;
}

.transfer-form .item-row:focus-within {
    border-color: var(--primary-light);
}

.transfer-form .item-row:hover {
    box-shadow: none;
    transform: none;
}

.transfer-form .item-row__bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.85rem;
}

.transfer-form .item-row__label {
    counter-increment: transfer-item;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: var(--primary);
}

.transfer-form .item-row__label::after {
    content: " " counter(transfer-item);
}

.transfer-form .item-row .remove-item {
    border: 0;
    background: transparent;
    color: #9aa0a6;
    line-height: 1;
    padding: 0.3rem 0.4rem;
    border-radius: 4px;
    font-size: 0.8rem;
    transition: color 0.2s ease, background-color 0.2s ease;
}

.transfer-form .item-row .remove-item:hover {
    color: var(--danger);
    background-color: rgba(231, 111, 81, 0.1);
}

.transfer-form .item-row .form-label {
    font-size: 0.82rem;
    font-weight: 500;
    margin-bottom: 0.3rem;
}

.transfer-form .subtotal-highlight {
    font-weight: 600;
    background-color: #f1f5f2;
}

.transfer-form .transfer-add-btn {
    border-style: dashed;
    width: 100%;
    padding: 0.6rem 1rem;
    font-weight: 500;
    background-color: #fff;
}

.transfer-form .transfer-add-btn:hover {
    background-color: var(--primary);
}

/* ---- Summary card ------------------------------------------------ */
.transfer-form .transfer-summary__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.transfer-form .transfer-summary__label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: #6c757d;
}

.transfer-form .transfer-summary__value {
    display: block;
    margin-top: 0.15rem;
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--dark-text);
}

.transfer-form .transfer-summary__actions {
    display: flex;
    gap: 0.6rem;
}

.transfer-form .transfer-summary__hint {
    margin: 0.9rem 0 0;
    padding-top: 0.9rem;
    border-top: 1px solid #e3e8e4;
    font-size: 0.82rem;
    color: #6c757d;
}

/* ---- Required-field cues ----------------------------------------- */
.transfer-form .form-label .text-danger {
    font-weight: 600;
    margin-left: 2px;
}

.transfer-form .transfer-card__title .text-danger {
    font-size: 0.8em;
    vertical-align: super;
    margin-left: 2px;
}

@media (max-width: 768px) {
    .transfer-form .item-row {
        padding: 0.9rem;
    }

    .transfer-form .transfer-summary__inner {
        align-items: flex-start;
        flex-direction: column;
    }

    .transfer-form .transfer-summary__actions {
        width: 100%;
    }

    .transfer-form .transfer-summary__actions .btn {
        flex: 1;
    }

    .transfer-form .form-label .text-danger {
        font-size: 0.9em;
    }
}
</style>
@endsection

@section('page-header')
<div class="mb-4">
    <h1><i class="bi bi-arrow-left-right me-2"></i>Create Stock Transfer to Retailer</h1>
</div>
@endsection

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    

    <!-- Info Banner -->
    <div class="transfer-notice">
        <i class="bi bi-info-circle"></i>
        <div>
            Transfers are completed <strong>immediately</strong>. Stock leaves the warehouse and lands at the retailer
            as soon as this transfer is created.
        </div>
    </div>

    <form action="{{ route('stock-transfers.warehouse-to-retailer.store') }}" method="POST" id="transferForm" class="transfer-form">
        @csrf

        {{-- Section 1: Transfer details --}}
        <div class="card transfer-card mb-4">
            <div class="card-header transfer-card__header">
                <span class="transfer-card__step">1</span>
                <div>
                    <h2 class="transfer-card__title">Transfer Information</h2>
                    <p class="transfer-card__subtitle">Where the stock is moving from, and which retailer it is headed to.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="transfer-panel">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="from_location_id" class="form-label">From Warehouse <span class="text-danger">*</span></label>
                        <select class="form-select" id="from_location_id" name="from_location_id" required>
                            <option value="">Select warehouse...</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }} (ID: {{ $warehouse->id }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="to_location_id" class="form-label">To Retailer <span class="text-danger">*</span></label>
                        <select class="form-select" id="to_location_id" name="to_location_id" required>
                            <option value="">Select retailer...</option>
                            @foreach($retailers as $retailer)
                                <option value="{{ $retailer->id }}">{{ $retailer->name }} (ID: {{ $retailer->id }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any notes about this transfer..."></textarea>
                    </div>
                </div>
                </div>
            </div>
        </div>

        {{-- Section 2: Items --}}
        <div class="card transfer-card mb-4">
            <div class="card-header transfer-card__header">
                <span class="transfer-card__step">2</span>
                <div>
                    <h2 class="transfer-card__title">Items to Transfer <span class="text-danger">*</span></h2>
                    <p class="transfer-card__subtitle">Add each product being moved, with the quantity leaving the warehouse.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="transfer-panel">

                <div id="warehouse-warning" class="alert alert-warning" style="display: none;">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Please select a warehouse first to see available products.
                </div>

                <div id="items-container">
                    <!-- Items will be added here -->
                </div>

                <button type="button" class="btn btn-outline-primary transfer-add-btn" onclick="addItem()">
                    <i class="bi bi-plus-lg"></i> Add Another Item
                </button>
                </div>
            </div>
        </div>

        {{-- Section 3: Review & submit --}}
        <div class="card transfer-card transfer-summary mb-4">
            <div class="card-body">
                <div class="transfer-summary__inner">
                    <div>
                        <span class="transfer-summary__label">Total Value</span>
                        <span class="transfer-summary__value">$<span id="transfer-total">0.00</span></span>
                    </div>
                    <div class="transfer-summary__actions">
                        <a href="{{ route('stock-transfers.warehouse-to-retailer') }}" class="btn btn-danger">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle"></i> Confirm Transfer
                        </button>
                    </div>
                </div>
                <p class="transfer-summary__hint">
                    Fields marked <span class="text-danger">*</span> are required. Stock moves
                    <strong>immediately</strong> once the transfer is confirmed.
                </p>
            </div>
        </div>
    </form>
</div>

@endsection

@section('scripts')
<script>
let products = @json($products);

// Debounce function to limit how often a function can be called
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

async function calculateAvailableStock(productId, warehouseId) {
    try {
        const product = products.find(p => p.id === parseInt(productId));
        if (!product || !product.warehouse_stocks) {
            return 0;
        }

        const warehouseStocksArray = Array.isArray(product.warehouse_stocks) 
            ? product.warehouse_stocks 
            : Object.values(product.warehouse_stocks);

        const stock = warehouseStocksArray.find(s => parseInt(s.warehouse_id) === parseInt(warehouseId));
        if (!stock) {
            return 0;
        }

        const availableStock = parseInt(stock.available_stock) || 0;
        return availableStock;
    } catch (error) {
        console.error('Error calculating available stock:', error);
        return 0;
    }
}

function calculateSubtotal(quantity, unitCost) {
    return (parseFloat(quantity) || 0) * (parseFloat(unitCost) || 0);
}

// Sum every row's subtotal into the summary card
function updateTransferTotal() {
    const total = Array.from(document.querySelectorAll('input[name$="[subtotal]"]'))
        .reduce((sum, field) => sum + (parseFloat(field.value) || 0), 0);

    document.getElementById('transfer-total').textContent = total.toFixed(2);
}

const updateItemTotals = debounce(function(input) {
    const itemRow = input.closest('.item-row');
    const quantity = parseInt(input.value) || 0;
    const unitCost = parseFloat(itemRow.querySelector('input[name$="[unit_cost]"]').value) || 0;
    const subtotal = calculateSubtotal(quantity, unitCost);

    itemRow.querySelector('input[name$="[subtotal]"]').value = subtotal.toFixed(2);
    updateTransferTotal();
}, 300);

async function addItem() {
    try {
        const container = document.getElementById('items-container');
        const warehouseId = document.getElementById('from_location_id').value;
        
        if (!warehouseId) {
            document.getElementById('warehouse-warning').style.display = 'block';
            return;
        }
        
        document.getElementById('warehouse-warning').style.display = 'none';
        
        const itemCount = container.children.length;
        const itemDiv = document.createElement('div');
        itemDiv.className = 'item-row';
        
        // Filter products based on warehouse stock
        const availableProducts = await Promise.all(products.map(async product => {
            const availableStock = await calculateAvailableStock(product.id, warehouseId);
            return { product, availableStock };
        }));
        
        const filteredProducts = availableProducts.filter(({ availableStock }) => availableStock > 0);
        
        const productOptions = filteredProducts.map(({ product, availableStock }) => {
            const warehouseStocksArray = Array.isArray(product.warehouse_stocks) 
                ? product.warehouse_stocks 
                : Object.values(product.warehouse_stocks);

            const stock = warehouseStocksArray.find(s => parseInt(s.warehouse_id) === parseInt(warehouseId));
            const unitCost = stock ? parseFloat(stock.unit_cost) || 0 : 0;
            
            return `<option value="${product.id}" data-stock="${availableStock}" data-price="${unitCost.toFixed(2)}">
                ${product.name} (Available: ${availableStock})
            </option>`;
        }).join('');
        
        itemDiv.innerHTML = `
            <div class="item-row__bar">
                <span class="item-row__label">Item</span>
                <button type="button" class="remove-item" title="Remove this item" aria-label="Remove this item"
                        onclick="removeItem(this)">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Product <span class="text-danger">*</span></label>
                    <select class="form-select" name="items[${itemCount}][product_id]" required>
                        <option value="">Select product...</option>
                        ${productOptions}
                    </select>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <label class="form-label">Quantity <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="items[${itemCount}][quantity]"
                            min="1" required>
                        <span class="input-group-text">units</span>
                    </div>
                    <small class="text-muted quantity-hint">Select product first</small>
                    <div class="invalid-feedback quantity-error"></div>
                </div>
                <div class="col-lg-4 col-md-4 col-sm-6">
                    {{-- This is what the goods COST us, not what they sell for. It was
                             labelled "Unit Price" while being populated from the product
                             cost, which is exactly the confusion this rename removes. --}}
                    <label class="form-label">Unit Cost</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" name="items[${itemCount}][unit_cost]" readonly>
                    </div>
                </div>
                <div class="col-lg-5 col-md-4">
                    <label class="form-label">Subtotal</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" class="form-control subtotal-highlight" name="items[${itemCount}][subtotal]" readonly>
                    </div>
                </div>
            </div>
        `;
        
        container.appendChild(itemDiv);
        
        // Add event listeners after adding the item
        const select = itemDiv.querySelector('select');
        const quantityInput = itemDiv.querySelector('input[name$="[quantity]"]');
        
        select.addEventListener('change', () => updateProductDetails(select));
        quantityInput.addEventListener('input', () => validateQuantity(quantityInput));
        quantityInput.addEventListener('change', () => validateQuantity(quantityInput));
        
    } catch (error) {
        console.error('Error adding item:', error);
    }
}

const validateQuantity = debounce(function(input) {
    try {
        const quantity = parseInt(input.value) || 0;
        const errorDiv = input.closest('.item-row').querySelector('.quantity-error');
        
        if (quantity <= 0) {
            errorDiv.textContent = 'Quantity must be greater than 0';
            input.classList.add('is-invalid');
            return false;
        } else {
            input.classList.remove('is-invalid');
            errorDiv.textContent = '';
            updateItemTotals(input);
            return true;
        }
    } catch (error) {
        console.error('Error validating quantity:', error);
        return false;
    }
}, 300);

async function updateProductDetails(select) {
    try {
        const itemRow = select.closest('.item-row');
        const quantityInput = itemRow.querySelector('input[name$="[quantity]"]');
        const quantityHint = itemRow.querySelector('.quantity-hint');
        const unitCostInput = itemRow.querySelector('input[name$="[unit_cost]"]');
        const subtotalInput = itemRow.querySelector('input[name$="[subtotal]"]');
        const warehouseId = document.getElementById('from_location_id').value;
        const productId = select.value;
        
        if (productId && warehouseId) {
            const availableStock = await calculateAvailableStock(productId, warehouseId);
            const product = products.find(p => p.id === parseInt(productId));
            
            const warehouseStocksArray = Array.isArray(product.warehouse_stocks) 
                ? product.warehouse_stocks 
                : Object.values(product.warehouse_stocks);

            const stock = warehouseStocksArray.find(s => parseInt(s.warehouse_id) === parseInt(warehouseId));
            const unitCost = stock ? parseFloat(stock.unit_cost) || 0 : 0;
            
            quantityInput.setAttribute('max', availableStock);
            quantityInput.setAttribute('data-available-stock', availableStock);
            quantityHint.textContent = `Available: ${availableStock} units`;
            unitCostInput.value = unitCost.toFixed(2);
            
            quantityInput.value = '';
            subtotalInput.value = '';
            validateQuantity(quantityInput);
            updateTransferTotal();
        } else {
            quantityHint.textContent = 'Select product first';
            quantityInput.setAttribute('max', 0);
            quantityInput.setAttribute('data-available-stock', 0);
            unitCostInput.value = '';
            subtotalInput.value = '';
            quantityInput.value = '';
            updateTransferTotal();
        }
    } catch (error) {
        console.error('Error updating product details:', error);
    }
}

function removeItem(button) {
    button.closest('.item-row').remove();
    updateTransferTotal();
}

// Add event listener for warehouse selection
document.getElementById('from_location_id').addEventListener('change', async function() {
    try {
        const warehouseId = this.value;
        const container = document.getElementById('items-container');
        const warningElement = document.getElementById('warehouse-warning');
        
        container.innerHTML = '';
        updateTransferTotal();

        if (warehouseId) {
            warningElement.style.display = 'none';
            await addItem();
        } else {
            warningElement.style.display = 'block';
        }
    } catch (error) {
        console.error('Error handling warehouse selection:', error);
    }
});

// Form validation
document.getElementById('transferForm').addEventListener('submit', async function(e) {
    try {
        e.preventDefault();
        
        // Check if warehouse and retailer are selected
        const warehouseId = document.getElementById('from_location_id').value;
        const retailerId = document.getElementById('to_location_id').value;
        
        if (!warehouseId) {
            alert('Please select a source warehouse.');
            return;
        }
        
        if (!retailerId) {
            alert('Please select a destination retailer.');
            return;
        }
        
        // Check if at least one item exists
        const items = document.querySelectorAll('.item-row');
        if (items.length === 0) {
            alert('Please add at least one product to transfer.');
            return;
        }
        
        let isValid = true;
        const errors = [];
        
        // Validate all items
        items.forEach(item => {
            const productId = item.querySelector('select[name$="[product_id]"]').value;
            const quantity = parseInt(item.querySelector('input[name$="[quantity]"]').value) || 0;
            
            if (!productId) {
                isValid = false;
                errors.push('Please select a product for all items.');
            }
            
            if (quantity <= 0) {
                isValid = false;
                errors.push('Quantity must be greater than 0.');
            }
        });
        
        if (!isValid) {
            alert(errors.join('\n'));
            return;
        }
        
        // Prepare form data
        const formData = new FormData(this);
        
        // Show loading state
        const submitButton = this.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Processing...';
        
        try {
            const response = await fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            // Always read the body first - the server reports why it failed in there.
            let result = null;
            const rawBody = await response.text();
            try {
                result = JSON.parse(rawBody);
            } catch (parseError) {
                result = null;
            }

            if (!response.ok) {
                // Laravel returns a 422 with an `errors` bag for validation failures.
                const validationMessages = result && result.errors
                    ? Object.values(result.errors).flat().join('\n')
                    : null;

                throw new Error(
                    validationMessages
                    || (result && result.message)
                    || `Server error ${response.status}: ${rawBody.slice(0, 300)}`
                );
            }

            if (result && result.success) {
                // Redirect immediately without showing alert
                if (result.redirect_url) {
                    window.location.href = result.redirect_url;
                } else {
                    window.location.href = '{{ route("stock-transfers.warehouse-to-retailer") }}';
                }
            } else {
                // Show error message from server
                throw new Error((result && result.message) || 'An error occurred while processing the transfer.');
            }
        } catch (error) {
            console.error('Error submitting form:', error);
            alert(error.message || 'An error occurred while processing the transfer. Please try again.');
        } finally {
            // Reset button state
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        }
    } catch (error) {
        console.error('Error in form submission:', error);
        alert('An error occurred. Please try again.');
    }
});
</script>

<style>
.quantity-hint {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: #6c757d;
}

.input-group-text {
    background-color: #f8f9fa;
    border-color: #ced4da;
}

.form-control:read-only {
    background-color: #f8f9fa;
    cursor: not-allowed;
}

.invalid-feedback {
    display: block;
    color: #dc3545;
    font-size: 0.875rem;
    margin-top: 0.5rem;
}

.form-label {
    font-weight: 500;
    color: #495057;
    margin-bottom: 0.5rem;
}

.btn-outline-danger {
    width: auto;
    padding: 0.5rem 1rem;
}

.form-select, .form-control {
    padding: 0.5rem 0.75rem;
}
</style>
@endsection
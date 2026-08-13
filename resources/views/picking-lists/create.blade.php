@extends('layouts.app')
@section('styles')
<style>
.stock-info {
    background-color: #f8f9fa;
    border-radius: 4px;
    padding: 8px;
    border-left: 3px solid #17a2b8;
}

.stock-badge .badge {
    font-size: 0.75rem;
    padding: 0.5rem 0.75rem;
}

.quantity-error {
    background-color: #f8d7da;
    border-radius: 4px;
    padding: 6px 8px;
    border-left: 3px solid #dc3545;
}

.is-invalid {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
}

.item-row {
    transition: all 0.3s ease;
}

.item-row:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stock-text {
    font-weight: 500;
}

.info-banner {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}
</style>
@endsection

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-arrow-left-right me-2"></i>Create Stock Transfer to Retailer</h1>
    <a href="{{ route('picking-lists.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Picking Lists
    </a>
</div>
@endsection

@section('content')


<!-- Info Banner -->
<div class="info-banner">
    <div class="d-flex align-items-center">
        <i class="bi bi-info-circle-fill me-3" style="font-size: 1.5rem;"></i>
        <div>
            <h6 class="mb-1">Stock Transfer to Retailers</h6>
            <p class="mb-0 small">This picking list is specifically designed for transferring stock from warehouses to retail locations. Select a warehouse as the source and a retailer as the destination.</p>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-10">
        <form action="{{ route('picking-lists.store') }}" method="POST" id="pickingForm">
            @csrf
            
            <!-- Hidden field for picking type -->
            <input type="hidden" name="picking_type" value="retailer_distribution">
            
            <!-- Transfer Information Section -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header py-3" style="background-color: #f0fdf4;">
                    <div class="d-flex align-items-center">
                        <h5 class="mb-0 flex-grow-1 text-dark">
                            <i class="bi bi-info-circle me-2 text-secondary"></i>
                            <span class="fw-semibold">Transfer Information</span>
                            <span class="badge bg-white text-dark border ms-2 px-3">Step 1</span>
                        </h5>
                    </div>
                </div>
                <div class="card-body bg-white">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="from_location_id" class="form-label fw-semibold">
                                <i class="bi bi-house-fill me-1 text-primary"></i>From Warehouse
                            </label>
                            <select class="form-select" id="from_location_id" name="from_location_id" required>
                                <option value="">Select source warehouse</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ old('from_location_id') == $warehouse->id ? 'selected' : '' }}>
                                        {{ $warehouse->name }}
                                        @if($warehouse->address)
                                            <small class="text-muted"> - {{ Str::limit($warehouse->address, 30) }}</small>
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('from_location_id')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Stock will be transferred from this warehouse</small>
                        </div>

                        <div class="col-md-6">
                            <label for="to_location_id" class="form-label fw-semibold">
                                <i class="bi bi-shop me-1 text-success"></i>To Retailer
                            </label>
                            <select class="form-select" id="to_location_id" name="to_location_id" required>
                                <option value="">Select destination retailer</option>
                                @foreach($retailers as $retailer)
                                    <option value="{{ $retailer->id }}" {{ old('to_location_id') == $retailer->id ? 'selected' : '' }}>
                                        {{ $retailer->name }}
                                        @if($retailer->address)
                                            <small class="text-muted"> - {{ Str::limit($retailer->address, 30) }}</small>
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('to_location_id')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Stock will be transferred to this retailer</small>
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label fw-semibold">Transfer Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any special instructions or notes for this stock transfer...">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items to Transfer Section -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header py-3" style="background-color: #f0fdf4;">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 text-dark">
                            <i class="bi bi-box-seam me-2 text-secondary"></i>
                            <span class="fw-semibold">Items to Transfer</span>
                            <span class="badge bg-white text-dark border ms-2 px-3">Step 2</span>
                        </h5>
                        <button type="button" class="btn btn-dark btn-sm px-3" onclick="addItem()">
                            <i class="bi bi-plus-lg me-1"></i> Add Product
                        </button>
                    </div>
                </div>
                <div class="card-body bg-white">
                    <div id="items-container">
                        <!-- Items will be added here -->
                    </div>
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="bi bi-lightbulb me-2"></i>
                        <div>
                            <strong>Tip:</strong> Stock quantities shown are available at the selected warehouse. Make sure to select the source warehouse first to see accurate stock levels.
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('picking-lists.index') }}" class="btn btn-light border shadow-sm">Cancel</a>
                <button type="submit" class="btn btn-primary shadow-sm">
                    <i class="bi bi-arrow-right-circle me-1"></i> Create Stock Transfer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Item Template -->
<div id="item-template" style="display: none;">
    <div class="item-row border rounded p-3 mb-3" style="background-color: #f8f9fa;">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold">Product</label>
                <select class="form-select product-select" name="items[INDEX][product_id]" required onchange="updateStockInfo(this, INDEX)">
                    <option value="">Select product to transfer</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
                <div class="stock-info mt-2" id="stock-info-INDEX" style="display: none;">
                    <small class="text-muted">
                        <i class="bi bi-box-seam me-1"></i>
                        <span class="stock-text"></span>
                    </small>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Transfer Quantity</label>
                <input type="number" class="form-control quantity-input" name="items[INDEX][quantity]" min="1" required 
                       placeholder="Enter quantity"
                       onchange="validateQuantity(this, INDEX)" oninput="validateQuantity(this, INDEX)">
                <div class="quantity-error mt-1" id="quantity-error-INDEX" style="display: none;">
                    <small class="text-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <span class="error-text"></span>
                    </small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stock-badge" id="stock-badge-INDEX" style="display: none;">
                    <span class="badge bg-info">
                        <i class="bi bi-box me-1"></i>
                        <span class="available-stock">0</span> available
                    </span>
                </div>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-danger w-100" onclick="removeItem(this)">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
let itemIndex = 0;
let stockCache = {}; // Cache stock information to avoid repeated API calls

function addItem() {
    const container = document.getElementById('items-container');
    const template = document.getElementById('item-template');
    const newItem = template.cloneNode(true);
    
    newItem.style.display = 'block';
    newItem.id = '';
    newItem.innerHTML = newItem.innerHTML.replace(/INDEX/g, itemIndex);
    
    container.appendChild(newItem);
    itemIndex++;
}

function removeItem(button) {
    button.closest('.item-row').remove();
}

async function updateStockInfo(selectElement, index) {
    const productId = selectElement.value;
    const fromLocationId = document.getElementById('from_location_id').value;
    
    const stockInfoDiv = document.getElementById(`stock-info-${index}`);
    const stockBadgeDiv = document.getElementById(`stock-badge-${index}`);
    const quantityInput = document.querySelector(`input[name="items[${index}][quantity]"]`);
    
    if (!productId || !fromLocationId) {
        stockInfoDiv.style.display = 'none';
        stockBadgeDiv.style.display = 'none';
        if (quantityInput) {
            quantityInput.max = '';
            quantityInput.placeholder = 'Select warehouse first';
        }
        return;
    }
    
    try {
        // Check cache first
        const cacheKey = `${productId}-${fromLocationId}`;
        let stockData;
        
        if (stockCache[cacheKey]) {
            stockData = stockCache[cacheKey];
        } else {
            const response = await fetch(`/api/stock-info/${productId}/${fromLocationId}`);
            stockData = await response.json();
            
            if (response.ok) {
                stockCache[cacheKey] = stockData; // Cache the result
            } else {
                throw new Error(stockData.error || 'Failed to fetch stock info');
            }
        }
        
        // Update stock info display
        const stockText = stockInfoDiv.querySelector('.stock-text');
        stockText.textContent = `Warehouse Stock - Total: ${stockData.total_quantity}, Reserved: ${stockData.reserved_quantity}, Available: ${stockData.available_quantity}`;
        stockInfoDiv.style.display = 'block';
        
        // Update stock badge
        const availableStockSpan = stockBadgeDiv.querySelector('.available-stock');
        availableStockSpan.textContent = stockData.available_quantity;
        
        // Change badge color based on stock availability
        const badge = stockBadgeDiv.querySelector('.badge');
        if (stockData.available_quantity === 0) {
            badge.className = 'badge bg-danger';
            badge.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Out of stock';
        } else if (stockData.available_quantity < 10) {
            badge.className = 'badge bg-warning text-dark';
            badge.innerHTML = `<i class="bi bi-box me-1"></i><span class="available-stock">${stockData.available_quantity}</span> available (Low)`;
        } else {
            badge.className = 'badge bg-success';
            badge.innerHTML = `<i class="bi bi-box me-1"></i><span class="available-stock">${stockData.available_quantity}</span> available`;
        }
        
        stockBadgeDiv.style.display = 'block';
        
        // Set max quantity for input
        if (quantityInput) {
            quantityInput.max = stockData.available_quantity;
            quantityInput.setAttribute('data-available-stock', stockData.available_quantity);
            
            // Disable input if no stock available
            if (stockData.available_quantity === 0) {
                quantityInput.disabled = true;
                quantityInput.placeholder = 'No stock available';
            } else {
                quantityInput.disabled = false;
                quantityInput.placeholder = `Max: ${stockData.available_quantity}`;
            }
            
            // Validate current quantity if any
            if (quantityInput.value) {
                validateQuantity(quantityInput, index);
            }
        }
        
    } catch (error) {
        console.error('Error fetching stock info:', error);
        stockInfoDiv.style.display = 'none';
        stockBadgeDiv.style.display = 'none';
        if (quantityInput) {
            quantityInput.placeholder = 'Error loading stock info';
        }
    }
}

function validateQuantity(input, index) {
    const quantity = parseInt(input.value) || 0;
    const availableStock = parseInt(input.getAttribute('data-available-stock')) || 0;
    const errorDiv = document.getElementById(`quantity-error-${index}`);
    const errorText = errorDiv.querySelector('.error-text');
    
    if (quantity > availableStock) {
        errorText.textContent = `Cannot exceed available warehouse stock (${availableStock})`;
        errorDiv.style.display = 'block';
        input.classList.add('is-invalid');
        return false;
    } else if (quantity <= 0) {
        errorText.textContent = 'Transfer quantity must be greater than 0';
        errorDiv.style.display = 'block';
        input.classList.add('is-invalid');
        return false;
    } else {
        errorDiv.style.display = 'none';
        input.classList.remove('is-invalid');
        return true;
    }
}

async function updateAllStockInfo() {
    // Clear cache when location changes
    stockCache = {};
    
    // Update stock info for all existing items
    const productSelects = document.querySelectorAll('.product-select');
    productSelects.forEach((select, index) => {
        if (select.value) {
            const actualIndex = select.name.match(/\[(\d+)\]/)[1];
            updateStockInfo(select, actualIndex);
        }
    });
}

function validateForm() {
    let isValid = true;
    const quantityInputs = document.querySelectorAll('.quantity-input');
    
    // Check if warehouse is selected
    const fromLocationId = document.getElementById('from_location_id').value;
    if (!fromLocationId) {
        alert('Please select a source warehouse.');
        return false;
    }
    
    // Check if retailer is selected
    const toLocationId = document.getElementById('to_location_id').value;
    if (!toLocationId) {
        alert('Please select a destination retailer.');
        return false;
    }
    
    // Check if at least one item is added
    if (quantityInputs.length === 0) {
        alert('Please add at least one product to transfer.');
        return false;
    }
    
    quantityInputs.forEach((input, index) => {
        const actualIndex = input.name.match(/\[(\d+)\]/)[1];
        if (!validateQuantity(input, actualIndex)) {
            isValid = false;
        }
    });
    
    if (!isValid) {
        alert('Please fix the quantity errors before submitting the form.');
        return false;
    }
    
    return true;
}

// Add event listeners
document.addEventListener('DOMContentLoaded', function() {
    addItem();
    
    // Add event listener for from location changes
    document.getElementById('from_location_id').addEventListener('change', updateAllStockInfo);
    
    // Add form validation on submit
    document.getElementById('pickingForm').addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });
});
</script>
@endsection 
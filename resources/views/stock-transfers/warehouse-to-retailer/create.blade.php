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

.item-row {
    transition: all 0.3s ease;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border: 1px solid #e9ecef;
}

.item-row:hover {
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    transform: translateY(-2px);
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
</style>
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-arrow-left-right me-2"></i>Create Stock Transfer to Retailer</h1>
        <a href="{{ route('stock-transfers.warehouse-to-retailer') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Transfers
        </a>
    </div>

    <!-- Info Banner -->
    <div class="alert alert-info mb-4">
        <div class="d-flex align-items-center">
            <i class="bi bi-info-circle-fill me-3" style="font-size: 1.5rem;"></i>
            <div>
                <h6 class="mb-1">Stock Transfer to Retailers</h6>
                <p class="mb-0">Create immediate stock transfers from warehouses to retail locations. Transfers are completed automatically and stock is moved immediately upon creation.</p>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-10">
            <form action="{{ route('stock-transfers.warehouse-to-retailer.store') }}" method="POST" id="transferForm">
                @csrf
                
                <!-- Transfer Information Section -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            Transfer Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="from_location_id" class="form-label">From Warehouse <span class="text-danger">*</span></label>
                                <select class="form-select" id="from_location_id" name="from_location_id" required>
                                    <option value="">Select warehouse...</option>
                                    @foreach($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="to_location_id" class="form-label">To Retailer <span class="text-danger">*</span></label>
                                <select class="form-select" id="to_location_id" name="to_location_id" required>
                                    <option value="">Select retailer...</option>
                                    @foreach($retailers as $retailer)
                                        <option value="{{ $retailer->id }}">{{ $retailer->name }}</option>
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

                <!-- Items to Transfer Section -->
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-box-seam me-2"></i>
                            Items to Transfer
                        </h5>
                        <button type="button" class="btn btn-primary" onclick="addItem()">
                            <i class="bi bi-plus-lg me-1"></i> Add Product
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="warehouse-warning" class="alert alert-warning" style="display: none;">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Please select a warehouse first to see available products.
                        </div>
                        
                        <div id="items-container">
                            <!-- Items will be added here -->
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('stock-transfers.warehouse-to-retailer') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Confirm Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>
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
        console.log('Calculating stock for:', { productId, warehouseId });
        
        const product = products.find(p => p.id === parseInt(productId));
        if (!product || !product.warehouse_stocks) {
            console.log('No product or warehouse stocks found');
            return 0;
        }

        const warehouseStocksArray = Array.isArray(product.warehouse_stocks) 
            ? product.warehouse_stocks 
            : Object.values(product.warehouse_stocks);

        const stock = warehouseStocksArray.find(s => parseInt(s.warehouse_id) === parseInt(warehouseId));
        if (!stock) {
            console.log('No stock found for warehouse');
            return 0;
        }

        const availableStock = parseInt(stock.available_stock) || 0;
        console.log('Stock details:', {
            product: product.name,
            warehouse: warehouseId,
            available: availableStock,
            raw_stock: stock
        });

        return availableStock;
    } catch (error) {
        console.error('Error calculating available stock:', error);
        return 0;
    }
}

function calculateSubtotal(quantity, unitPrice) {
    return (parseFloat(quantity) || 0) * (parseFloat(unitPrice) || 0);
}

const updateItemTotals = debounce(function(input) {
    const itemRow = input.closest('.item-row');
    const quantity = parseInt(input.value) || 0;
    const unitPrice = parseFloat(itemRow.querySelector('input[name$="[unit_price]"]').value) || 0;
    const subtotal = calculateSubtotal(quantity, unitPrice);
    
    itemRow.querySelector('input[name$="[subtotal]"]').value = subtotal.toFixed(2);
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
        itemDiv.className = 'item-row p-3 mb-3 border rounded';
        
        // Filter products based on warehouse stock
        const availableProducts = await Promise.all(products.map(async product => {
            const availableStock = await calculateAvailableStock(product.id, warehouseId);
            console.log('Filtering product:', {
                name: product.name,
                id: product.id,
                stock: availableStock
            });
            return { product, availableStock };
        }));
        
        const filteredProducts = availableProducts.filter(({ availableStock }) => availableStock > 0);
        
        const productOptions = filteredProducts.map(({ product, availableStock }) => {
            const warehouseStocksArray = Array.isArray(product.warehouse_stocks) 
                ? product.warehouse_stocks 
                : Object.values(product.warehouse_stocks);

            const stock = warehouseStocksArray.find(s => parseInt(s.warehouse_id) === parseInt(warehouseId));
            const unitPrice = stock ? parseFloat(stock.unit_cost) || 0 : 0;
            
            console.log('Creating option for:', {
                product: product.name,
                stock: availableStock,
                raw_stock: stock
            });
            
            return `<option value="${product.id}" data-stock="${availableStock}" data-price="${unitPrice.toFixed(2)}">
                ${product.name} (Available: ${availableStock})
            </option>`;
        }).join('');
        
        itemDiv.innerHTML = `
            <div class="row">
                <div class="col-12 mb-3">
                    <label class="form-label">Product <span class="text-danger">*</span></label>
                    <select class="form-select" name="items[${itemCount}][product_id]" required>
                        <option value="">Select product...</option>
                        ${productOptions}
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Quantity <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="items[${itemCount}][quantity]" 
                            min="1" required>
                        <span class="input-group-text">units</span>
                    </div>
                    <small class="text-muted quantity-hint">Select product first</small>
                    <div class="invalid-feedback quantity-error"></div>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Unit Price</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" name="items[${itemCount}][unit_price]" readonly>
                    </div>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Subtotal</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" class="form-control" name="items[${itemCount}][subtotal]" readonly>
                    </div>
                </div>
                <div class="col-12">
                    <button type="button" class="btn btn-outline-danger" onclick="removeItem(this)">
                        <i class="bi bi-trash me-1"></i> Remove Item
                    </button>
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
        const availableStock = parseInt(input.getAttribute('data-available-stock')) || 0;
        const errorDiv = input.closest('.item-row').querySelector('.quantity-error');
        
        if (quantity > availableStock) {
            errorDiv.textContent = `Cannot exceed available stock (${availableStock})`;
            input.classList.add('is-invalid');
            input.value = availableStock;
            updateItemTotals(input);
            return false;
        } else if (quantity <= 0) {
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
        const unitPriceInput = itemRow.querySelector('input[name$="[unit_price]"]');
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
            const unitPrice = stock ? parseFloat(stock.unit_cost) || 0 : 0;
            
            console.log('Updating product details:', {
                product: product.name,
                availableStock,
                unitPrice,
                raw_stock: stock
            });
            
            quantityInput.setAttribute('max', availableStock);
            quantityInput.setAttribute('data-available-stock', availableStock);
            quantityHint.textContent = `Available: ${availableStock} units`;
            unitPriceInput.value = unitPrice.toFixed(2);
            
            quantityInput.value = '';
            subtotalInput.value = '';
            validateQuantity(quantityInput);
        } else {
            quantityHint.textContent = 'Select product first';
            quantityInput.setAttribute('max', 0);
            quantityInput.setAttribute('data-available-stock', 0);
            unitPriceInput.value = '';
            subtotalInput.value = '';
            quantityInput.value = '';
        }
    } catch (error) {
        console.error('Error updating product details:', error);
    }
}

function removeItem(button) {
    button.closest('.item-row').remove();
}

// Add event listener for warehouse selection
document.getElementById('from_location_id').addEventListener('change', async function() {
    try {
        const warehouseId = this.value;
        const container = document.getElementById('items-container');
        const warningElement = document.getElementById('warehouse-warning');
        
        container.innerHTML = '';
        
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
            const availableStock = parseInt(item.querySelector('input[name$="[quantity]"]').getAttribute('data-available-stock')) || 0;
            
            if (!productId) {
                isValid = false;
                errors.push('Please select a product for all items.');
            }
            
            if (quantity <= 0) {
                isValid = false;
                errors.push('Quantity must be greater than 0.');
            }
            
            if (quantity > availableStock) {
                isValid = false;
                errors.push(`Quantity exceeds available stock (${availableStock}) for one or more products.`);
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
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success message
                alert(result.message);
                
                // Redirect to the warehouse to retailer page
                if (result.redirect_url) {
                    window.location.href = result.redirect_url;
                } else {
                    window.location.href = '{{ route("stock-transfers.warehouse-to-retailer") }}';
                }
            } else {
                alert(result.message || 'An error occurred while processing the transfer.');
            }
        } catch (error) {
            console.error('Error submitting form:', error);
            alert('An error occurred while processing the transfer. Please try again.');
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
.item-row {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    transition: all 0.3s ease;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.item-row:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

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
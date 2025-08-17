@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Record New Supply</h1>
    <a href="{{ route('supplies.index') }}" class="btn btn-secondary">Back to Supplies</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-info mb-4">
            <i class="bi bi-info-circle"></i> New supplies will be created with a <strong>pending</strong> status. The product stock will only be updated when you mark the supply as <strong>completed</strong>.
        </div>
        
        <div class="alert alert-light mb-4">
            <i class="bi bi-exclamation-circle text-danger"></i> Fields marked with <span class="text-danger">*</span> are required and must be filled before submitting the form.
        </div>
        
        <form action="{{ route('supplies.store') }}" method="POST">
            @csrf
            
            <div class="mb-3">
                <label for="vendor_id" class="form-label">Vendor <span class="text-danger">*</span></label>
                <select name="vendor_id" id="vendor_id" class="form-select @error('vendor_id') is-invalid @enderror" required>
                    <option value="">Select Vendor</option>
                    @foreach($vendors as $vendor)
                        <option value="{{ $vendor->id }}" {{ old('vendor_id') == $vendor->id ? 'selected' : '' }}>
                            {{ $vendor->name }}
                        </option>
                    @endforeach
                </select>
                @error('vendor_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="mb-3">
                <label for="warehouse_id" class="form-label">Receiving Warehouse <span class="text-danger">*</span></label>
                <select name="warehouse_id" id="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror" required>
                    <option value="">Select Warehouse</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ old('warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                            {{ $warehouse->name }} (ID: {{ $warehouse->id }})
                        </option>
                    @endforeach
                </select>
                @error('warehouse_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="mb-3">
                <label for="supply_date" class="form-label">Supply Date <span class="text-danger">*</span></label>
                <input type="date" name="supply_date" id="supply_date" class="form-control @error('supply_date') is-invalid @enderror" 
                    value="{{ old('supply_date', date('Y-m-d')) }}" required>
                @error('supply_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="mb-3">
                <label for="notes" class="form-label">Notes</label>
                <textarea name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror" rows="3">{{ old('notes') }}</textarea>
                @error('notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            
            <h3 class="mt-4 mb-3">Supply Items <span class="text-danger">*</span></h3>
            
            <!-- Cleaner type-ahead add-product field -->
            <div class="mb-3">
                <label class="form-label" for="product-search-input">Add Product</label>
                <div class="position-relative">
                    <input type="text" id="product-search-input" class="form-control" placeholder="Start typing product name…" autocomplete="off">
                    <div id="product-suggestions" class="list-group position-absolute w-100 shadow-sm" style="max-height: 220px; overflow-y: auto; display: none; z-index: 1050;"></div>
                </div>
                <small class="form-text text-muted">Click a suggestion or press Enter to add the highlighted one.</small>
            </div>
            
            <div id="supply-items">
                <div class="supply-item card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-3 col-md-3 mb-3">
                                <label class="form-label">Category</label>
                                <select name="products[0][category_id]" class="form-select category-select">
                                    <option value="">All Categories</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3 col-md-3 mb-3">
                                <label class="form-label">Subcategory</label>
                                <select name="products[0][subcategory_id]" class="form-select subcategory-select" disabled>
                                    <option value="">All Subcategories</option>
                                </select>
                            </div>
                            <div class="col-lg-4 col-md-4 mb-3">
                                <label class="form-label">Product <span class="text-danger">*</span></label>
                                <select name="products[0][product_id]" class="form-select product-select" required>
                                    <option value="">Select Product</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" 
                                            data-stock="{{ $product->available_stocks }}"
                                            data-category="{{ $product->category_id }}"
                                            data-parent-category="{{ $product->category->parent_id ?? $product->category_id }}">
                                            {{ $product->name }} ({{ $product->available_stocks }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="stock-info form-text mt-1"></div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-6 mb-3">
                                <label class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" name="products[0][quantity]" class="form-control item-quantity" min="1" value="1" required>
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-6 mb-3">
                                <label class="form-label">Unit Cost <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="products[0][unit_cost]" class="form-control item-unit-cost" min="0" step="0.01" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-2 col-sm-8 mb-3">
                                <label class="form-label">Subtotal</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="text" class="form-control item-subtotal subtotal-highlight" placeholder="0.00" readonly>
                                </div>
                            </div>
                            <div class="col-lg-1 col-md-1 col-sm-4 d-flex align-items-end mb-3">
                                <button type="button" class="btn btn-danger remove-item w-100">×</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <button type="button" id="add-item" class="btn btn-secondary mb-4">Add Item</button>
            
            <div class="d-flex justify-content-between align-items-center mt-4">
                <h4>Total Cost: $<span id="supply-total">0.00</span></h4>
                <button type="submit" class="btn btn-primary">Record Supply</button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        let itemIndex = 0;
        let calculateTimeout;
        let locationStockData = {};
        
        // Pre-load products for fast filtering (id, name, initial stock, category)
        const allProducts = {!! $products->map(function ($p) {
            return [
                'id'              => $p->id,
                'name'            => $p->name,
                'stock'           => $p->available_stocks,
                'category_id'     => $p->category_id,
                'parent_category' => $p->category->parent_id ?? $p->category_id,
            ];
        })->values()->toJson() !!};

        // DOM refs for the typeahead
        const searchInput      = document.getElementById('product-search-input');
        const suggestionBox    = document.getElementById('product-suggestions');

        function renderSuggestions(term = '', categoryId = '', subcategoryId = '') {
            const needle = term.toLowerCase();
            let matches = !needle ? [] : allProducts.filter(p => p.name.toLowerCase().includes(needle));

            // Filter by category if selected
            if (categoryId) {
                matches = matches.filter(p => {
                    if (subcategoryId) {
                        // If subcategory is selected, show only products from that subcategory
                        return p.category_id === parseInt(subcategoryId);
                    } else {
                        // If only category is selected, show products from category and its subcategories
                        return p.parent_category === parseInt(categoryId) || p.category_id === parseInt(categoryId);
                    }
                });
            }

            matches = matches.slice(0, 10);

            if (!matches.length) {
                suggestionBox.style.display = 'none';
                return;
            }

            suggestionBox.innerHTML = matches.map(p => {
                const stock = locationStockData[p.id] ?? p.stock ?? 0;
                return `<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-id="${p.id}">
                            <span>${p.name}</span>
                            <small class="text-muted ms-2">(${stock})</small>
                        </button>`;
            }).join('');
            suggestionBox.style.display = 'block';
        }

        // Filter as the user types
        searchInput.addEventListener('input', () => {
            const item = searchInput.closest('.supply-item') || document.querySelector('.supply-item');
            const categoryId = item.querySelector('.category-select').value;
            const subcategoryId = item.querySelector('.subcategory-select').value;
            renderSuggestions(searchInput.value, categoryId, subcategoryId);
        });

        // Handle click on a suggestion
        suggestionBox.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-id]');
            if (!btn) return;
            const id = btn.getAttribute('data-id');
            addSupplyItem(id);
            suggestionBox.style.display = 'none';
            searchInput.value = '';
        });

        // Add first suggestion on Enter
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const first = suggestionBox.querySelector('[data-id]');
                if (first) {
                    addSupplyItem(first.getAttribute('data-id'));
                    suggestionBox.style.display = 'none';
                    searchInput.value = '';
                }
            }
        });

        // Hide suggestions when clicking outside
        document.addEventListener('click', (evt) => {
            if (!searchInput.contains(evt.target) && !suggestionBox.contains(evt.target)) {
                suggestionBox.style.display = 'none';
            }
        });
        
        // Function to fetch stock data for a location
        async function fetchLocationStock(locationId) {
            try {
                const response = await fetch(`/api/stock-info/location/${locationId}`);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                const data = await response.json();
                console.log('Stock data received:', data); // Debug log
                locationStockData = data;
                updateAllProductStockInfo();
                updateProductDropdowns();
            } catch (error) {
                console.error('Error fetching stock data:', error);
                locationStockData = {};
                updateAllProductStockInfo();
                updateProductDropdowns();
            }
        }
        
        // Function to update stock info in product dropdowns
        function updateProductDropdowns() {
            // Update each per-row product dropdown
            document.querySelectorAll('.product-select').forEach(select => {
                select.querySelectorAll('option').forEach(option => {
                    if (option.value) {
                        const productId = option.value;
                        const availableStock = locationStockData[productId] || 0;
                        const productName = option.textContent.split(' (')[0];
                        option.textContent = `${productName} (${availableStock})`;
                    }
                });
            });

            // Refresh any visible suggestions with correct stock numbers
            if (suggestionBox.style.display !== 'none') {
                renderSuggestions(searchInput.value);
            }
        }
        
        // Function to update stock info for all products
        function updateAllProductStockInfo() {
            document.querySelectorAll('.product-select').forEach(select => {
                const productId = select.value;
                if (productId) {
                    updateProductStockInfo(select);
                }
            });
        }
        
        // Function to update stock info for a single product
        function updateProductStockInfo(productSelect) {
            const locationId = document.getElementById('warehouse_id').value;
            const productId = productSelect.value;
            const item = productSelect.closest('.supply-item');
            const stockInfo = item.querySelector('.stock-info');
            
            if (!locationId || !productId) {
                stockInfo.innerHTML = '';
                return;
            }
            
            const availableStock = locationStockData[productId] || 0;
            
            // Update stock info display
            if (availableStock > 0) {
                stockInfo.innerHTML = `
                    <div class="d-flex align-items-center mt-1">
                        <i class="bi bi-box me-1"></i>
                        <span class="text-${availableStock < 5 ? 'warning' : 'success'}">
                            ${availableStock} units available in selected warehouse
                        </span>
                    </div>`;
            } else {
                stockInfo.innerHTML = `
                    <div class="d-flex align-items-center mt-1">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <span class="text-danger">No stock available in selected warehouse</span>
                    </div>`;
            }
        }
        
        // Warehouse selection change
        document.getElementById('warehouse_id').addEventListener('change', function() {
            const locationId = this.value;
            if (locationId) {
                fetchLocationStock(locationId);
            } else {
                locationStockData = {};
                updateAllProductStockInfo();
                updateProductDropdowns();
            }
        });
        
        // Initial load if warehouse is selected
        const initialWarehouse = document.getElementById('warehouse_id').value;
        if (initialWarehouse) {
            fetchLocationStock(initialWarehouse);
        }
        
        // Add item button
        document.getElementById('add-item').addEventListener('click', function() {
            itemIndex++;
            const template = document.querySelector('.supply-item').cloneNode(true);
            
            // Update name attributes with new index
            template.querySelectorAll('[name]').forEach(input => {
                input.name = input.name.replace(/\[\d+\]/, `[${itemIndex}]`);
                if (input.classList.contains('item-quantity')) {
                    input.value = 1;
                }
            });
            
            // Clear any selected values
            template.querySelector('.product-select').selectedIndex = 0;
            template.querySelector('.item-subtotal').value = '';
            template.querySelector('.item-unit-cost').value = '';
            template.querySelector('.stock-info').innerHTML = '';
            
            // Ensure required field indicators are preserved in cloned template
            // The template already contains the required field indicators from the original HTML
            
            document.getElementById('supply-items').appendChild(template);
            setupEventListeners();
            
            // Scroll to new item
            template.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
        
        // Load subcategories for a category
        async function loadSubcategories(categoryId, subcategorySelect) {
            if (!categoryId) {
                subcategorySelect.innerHTML = '<option value="">All Subcategories</option>';
                subcategorySelect.disabled = true;
                return;
            }

            try {
                const response = await fetch(`/products/ajax/subcategories?category_id=${categoryId}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                });

                if (!response.ok) throw new Error('Network response was not ok');
                
                const data = await response.json();
                const options = data.options || {};
                
                subcategorySelect.innerHTML = Object.entries(options)
                    .map(([value, text]) => `<option value="${value}">${text}</option>`)
                    .join('');
                
                subcategorySelect.disabled = false;
            } catch (error) {
                console.error('Error loading subcategories:', error);
                subcategorySelect.innerHTML = '<option value="">Error loading subcategories</option>';
                subcategorySelect.disabled = true;
            }
        }

        // Filter products based on category/subcategory
        function filterProducts(item) {
            const categoryId = item.querySelector('.category-select').value;
            const subcategoryId = item.querySelector('.subcategory-select').value;
            const productSelect = item.querySelector('.product-select');
            
            // Show/hide options based on category selection
            Array.from(productSelect.options).forEach(option => {
                if (!option.value) return; // Skip placeholder option
                
                const productCategoryId = option.getAttribute('data-category');
                const productParentCategory = option.getAttribute('data-parent-category');
                
                let shouldShow = true;
                if (categoryId) {
                    if (subcategoryId) {
                        shouldShow = productCategoryId === subcategoryId;
                    } else {
                        shouldShow = productParentCategory === categoryId || productCategoryId === categoryId;
                    }
                }
                
                option.style.display = shouldShow ? '' : 'none';
            });
            
            // If current selection is hidden, clear it
            if (productSelect.selectedOptions[0]?.style.display === 'none') {
                productSelect.value = '';
                updateProductStockInfo(productSelect);
            }
        }

        // Setup event listeners for all items
        function setupEventListeners() {
            // Remove item button
            document.querySelectorAll('.remove-item').forEach(button => {
                button.onclick = function() {
                    if (document.querySelectorAll('.supply-item').length > 1) {
                        const item = this.closest('.supply-item');
                        item.style.opacity = '0.5';
                        setTimeout(() => {
                            item.remove();
                            updateTotal();
                        }, 150);
                    }
                };
            });

            // Category selection change
            document.querySelectorAll('.category-select').forEach(select => {
                select.addEventListener('change', function() {
                    const item = this.closest('.supply-item');
                    const subcategorySelect = item.querySelector('.subcategory-select');
                    loadSubcategories(this.value, subcategorySelect);
                    filterProducts(item);
                });
            });

            // Subcategory selection change
            document.querySelectorAll('.subcategory-select').forEach(select => {
                select.addEventListener('change', function() {
                    const item = this.closest('.supply-item');
                    filterProducts(item);
                });
            });
            
            // Product selection change
            document.querySelectorAll('.product-select').forEach(select => {
                select.addEventListener('change', async function() {
                    const item = this.closest('.supply-item');
                    const categorySelect = item.querySelector('.category-select');
                    const subcategorySelect = item.querySelector('.subcategory-select');
                    
                    if (this.value) {
                        const option = this.selectedOptions[0];
                        const productCategoryId = option.getAttribute('data-category');
                        const productParentCategory = option.getAttribute('data-parent-category');
                        
                        // Set the main category
                        categorySelect.value = productParentCategory;
                        
                        // Load and set subcategory if applicable
                        if (productParentCategory !== productCategoryId) {
                            await loadSubcategories(productParentCategory, subcategorySelect);
                            subcategorySelect.value = productCategoryId;
                        } else {
                            subcategorySelect.innerHTML = '<option value="">All Subcategories</option>';
                            subcategorySelect.disabled = true;
                        }
                    }
                    
                    updateProductStockInfo(this);
                });
            });
            
            // Quantity and unit cost change
            document.querySelectorAll('.item-quantity, .item-unit-cost').forEach(input => {
                ['input', 'change'].forEach(eventType => {
                    input.addEventListener(eventType, function() {
                        const item = this.closest('.supply-item');
                        updateSubtotal(item);
                    });
                });
            });
        }
        
        // Update subtotal for an item
        function updateSubtotal(item) {
            const quantityInput = item.querySelector('.item-quantity');
            const unitCostInput = item.querySelector('.item-unit-cost');
            const subtotalInput = item.querySelector('.item-subtotal');
            
            const quantity = parseFloat(quantityInput.value) || 0;
            const unitCost = parseFloat(unitCostInput.value) || 0;
            
            subtotalInput.value = (quantity * unitCost).toFixed(2);
            
            clearTimeout(calculateTimeout);
            calculateTimeout = setTimeout(updateTotal, 100);
        }
        
        // Update total supply amount
        function updateTotal() {
            const total = Array.from(document.querySelectorAll('.item-subtotal'))
                .reduce((sum, input) => sum + (parseFloat(input.value) || 0), 0);
            document.getElementById('supply-total').textContent = total.toFixed(2);
        }
        
        // Helper: returns array of productIds already present in supply items
        function currentProductIds() {
            return Array.from(document.querySelectorAll('.product-select'))
                .map(sel => sel.value)
                .filter(Boolean);
        }

        // Helper: create a new supply-item row for a given product id (if it does not exist)
        function addSupplyItem(productId) {
            if (!productId) return;
            if (currentProductIds().includes(productId)) return; // avoid duplicates

            // Try to reuse the very first blank row if it hasn't been filled yet
            const firstSelect = document.querySelector('.supply-item .product-select');
            if (firstSelect && !firstSelect.value && currentProductIds().length === 0) {
                firstSelect.value = productId;
                updateProductStockInfo(firstSelect);
                updateTotal();
                return;
            }

            itemIndex++;
            const template = document.querySelector('.supply-item').cloneNode(true);

            // Update name attributes and reset field values
            template.querySelectorAll('[name]').forEach(input => {
                input.name = input.name.replace(/\[\d+\]/, `[${itemIndex}]`);
                if (input.classList.contains('item-quantity')) {
                    input.value = 1;
                }
                if (input.classList.contains('item-unit-cost')) {
                    input.value = '';
                }
            });

            // Set selected product
            const select = template.querySelector('.product-select');
            select.value = productId;

            // Clear calculated fields & stock info
            template.querySelector('.item-subtotal').value = '';
            template.querySelector('.stock-info').innerHTML = '';

            // Required field indicators are preserved in the cloned template

            document.getElementById('supply-items').appendChild(template);

            setupEventListeners();
            updateProductStockInfo(select);
            updateTotal();
        }

        // Bind events for the initial (first) row
        setupEventListeners();

        // Previous datalist-driven handlers removed in favour of custom type-ahead
    });
</script>
<style>
    .subtotal-highlight {
        font-weight: 600;
        background-color: #f8f9fa;
    }
    
    .focus-ring {
        box-shadow: 0 0 0 0.2rem rgba(44, 110, 73, 0.25);
        border-color: #2c6e49;
    }
    
    .item-subtotal {
        transition: all 0.3s ease;
    }
    
    #supply-total {
        transition: all 0.3s ease;
        font-weight: 700;
        font-size: 1.1em;
    }
    
    .supply-item {
        transition: opacity 0.3s ease;
    }
    
    /* Required field indicator styling */
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
    h3 .text-danger {
        font-size: 0.8em;
        vertical-align: super;
        margin-left: 4px;
    }
    
    @media (max-width: 768px) {
        .col-lg-3.col-md-2.col-sm-8 {
            order: -1;
            margin-bottom: 1rem;
        }
        
        .subtotal-highlight {
            font-size: 1.1em;
            text-align: center;
        }
        
        /* Ensure required indicators are visible on mobile */
        .form-label .text-danger {
            font-size: 0.9em;
        }
    }
</style>
@endsection 
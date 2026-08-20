@extends('layouts.app')
@section('page-header')
<div class="mb-4">
    <h1>{{ ($purchaseOrder ?? null) ? 'Record Supply for '.$purchaseOrder->code : 'Record New Supply' }}</h1>
</div>
@endsection

@section('content')

<div class="supply-form">

    @if($purchaseOrder ?? null)
        <div class="supply-notice">
            <i class="bi bi-clipboard-check"></i>
            <div>
                This delivery is against
                <a href="{{ route('purchase-orders.show', $purchaseOrder->id) }}">{{ $purchaseOrder->code }}</a>.
                The vendor, the receiving warehouse and the items below come from the order — change the quantities
                to match what actually turned up, and remove any line that did not arrive.
            </div>
        </div>
    @endif

    <div class="supply-notice">
        <i class="bi bi-info-circle"></i>
        <div>
            Supplies are created as <strong>pending</strong>. Stock reaches the warehouse only once the goods are
            received — recording a supply does not change inventory.
        </div>
    </div>

    <form action="{{ route('supplies.store') }}" method="POST">
        @csrf
        @if($purchaseOrder ?? null)
            <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->id }}">
        @endif

        {{-- Section 1: Supply details --}}
        <div class="card supply-card mb-4">
            <div class="card-header supply-card__header">
                <span class="supply-card__step">1</span>
                <div>
                    <h2 class="supply-card__title">Supply Details</h2>
                    <p class="supply-card__subtitle">Who you are buying from, and where the goods are headed.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="supply-panel">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="vendor_id" class="form-label">Vendor <span class="text-danger">*</span></label>
                        @if($purchaseOrder ?? null)
                            {{-- Pinned to the order: a delivery cannot arrive from a different vendor. --}}
                            <select id="vendor_id" class="form-select" disabled>
                                <option value="{{ $purchaseOrder->vendor_id }}">{{ $purchaseOrder->vendor->name ?? 'Unknown vendor' }}</option>
                            </select>
                            <input type="hidden" name="vendor_id" value="{{ $purchaseOrder->vendor_id }}">
                        @else
                            <select name="vendor_id" id="vendor_id" class="form-select @error('vendor_id') is-invalid @enderror" required>
                                <option value="">Select Vendor</option>
                                @foreach($vendors as $vendor)
                                    <option value="{{ $vendor->id }}" {{ old('vendor_id') == $vendor->id ? 'selected' : '' }}>
                                        {{ $vendor->name }} (ID: {{ $vendor->id }})
                                    </option>
                                @endforeach
                            </select>
                        @endif
                        @error('vendor_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="warehouse_id" class="form-label">Receiving Warehouse <span class="text-danger">*</span></label>
                        @if($purchaseOrder ?? null)
                            {{-- Pinned to the order: the goods land where they were ordered to. --}}
                            <select id="warehouse_id" class="form-select" disabled>
                                <option value="{{ $purchaseOrder->warehouse_id }}">{{ $purchaseOrder->warehouse->name ?? 'Unknown warehouse' }}</option>
                            </select>
                            <input type="hidden" name="warehouse_id" value="{{ $purchaseOrder->warehouse_id }}">
                        @else
                            <select name="warehouse_id" id="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror" required>
                                <option value="">Select Warehouse</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ old('warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                                        {{ $warehouse->name }} (ID: {{ $warehouse->id }})
                                    </option>
                                @endforeach
                            </select>
                        @endif
                        @error('warehouse_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="supply_date" class="form-label">Supply Date <span class="text-danger">*</span></label>
                        <input type="date" name="supply_date" id="supply_date" class="form-control @error('supply_date') is-invalid @enderror"
                            value="{{ old('supply_date', date('Y-m-d')) }}" required>
                        @error('supply_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror" rows="3"
                            placeholder="Anything worth remembering about this purchase (optional)">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                </div>
            </div>
        </div>

        {{-- Section 2: Products --}}
        <div class="card supply-card mb-4">
            <div class="card-header supply-card__header">
                <span class="supply-card__step">2</span>
                <div>
                    <h2 class="supply-card__title">Supply Items <span class="text-danger">*</span></h2>
                    <p class="supply-card__subtitle">Add each product being bought, with its quantity and unit cost.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="supply-panel">

                <div class="supply-search mb-4">
                    <label class="form-label" for="product-search-input">Quick add a product</label>
                    <div class="position-relative">
                        <span class="supply-search__icon"><i class="bi bi-search"></i></span>
                        <input type="text" id="product-search-input" class="form-control supply-search__input" placeholder="Start typing a product name…" autocomplete="off">
                        <div id="product-suggestions" class="list-group position-absolute w-100 shadow-sm" style="max-height: 220px; overflow-y: auto; display: none; z-index: 1050;"></div>
                    </div>
                    <small class="form-text text-muted">Click a suggestion, or press Enter to add the first match.</small>
                </div>

                <div id="supply-items">
                    <div class="supply-item">
                        <div class="supply-item__bar">
                            <span class="supply-item__label">Item</span>
                            <button type="button" class="remove-item" title="Remove this item" aria-label="Remove this item">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div class="row g-3">
                            <div class="col-lg-3 col-md-6">
                                <label class="form-label">Category</label>
                                <select name="products[0][category_id]" class="form-select category-select">
                                    <option value="">All Categories</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <label class="form-label">Subcategory</label>
                                <select name="products[0][subcategory_id]" class="form-select subcategory-select" disabled>
                                    <option value="">All Subcategories</option>
                                </select>
                            </div>
                            <div class="col-lg-6">
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
                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <label class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" name="products[0][quantity]" class="form-control item-quantity" min="1" value="1" required>
                            </div>
                            <div class="col-lg-4 col-md-4 col-sm-6">
                                <label class="form-label">Unit Cost <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="products[0][unit_cost]" class="form-control item-unit-cost" min="0" step="0.01" placeholder="0.00" required>
                                </div>
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

                <button type="button" id="add-item" class="btn btn-outline-primary supply-add-btn">
                    <i class="bi bi-plus-lg"></i> Add Another Item
                </button>
                </div>
            </div>
        </div>

        {{-- Section 3: Review & submit --}}
        <div class="card supply-card supply-summary mb-4">
            <div class="card-body">
                <div class="supply-summary__inner">
                    <div>
                        <span class="supply-summary__label">Total Cost</span>
                        <span class="supply-summary__value">$<span id="supply-total">0.00</span></span>
                    </div>
                    <div class="supply-summary__actions">
                        <a href="{{ route('supplies.index') }}" class="btn btn-danger">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle"></i> Record Supply
                        </button>
                    </div>
                </div>
                <p class="supply-summary__hint">
                    Fields marked <span class="text-danger">*</span> are required. The supply is saved as
                    <strong>pending</strong> until the goods are received.
                </p>
            </div>
        </div>
    </form>
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

        // Set when the form was opened from a purchase order: one entry per line
        // still outstanding, at the cost agreed on the order.
        const prefillLines = @json($prefillLines ?? []);

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

        // Helper: create a new supply-item row for a given product id (if it does
        // not exist). Returns the row it created or reused, null if it declined.
        function addSupplyItem(productId) {
            if (!productId) return null;
            if (currentProductIds().includes(productId)) return null; // avoid duplicates

            // Try to reuse the very first blank row if it hasn't been filled yet
            const firstSelect = document.querySelector('.supply-item .product-select');
            if (firstSelect && !firstSelect.value && currentProductIds().length === 0) {
                firstSelect.value = productId;
                updateProductStockInfo(firstSelect);
                updateTotal();
                return firstSelect.closest('.supply-item');
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

            return template;
        }

        // Bind events for the initial (first) row
        setupEventListeners();

        // Lines carried over from a purchase order. Dispatching `change` on the
        // product select reuses the handler above, so Category and Subcategory
        // fill themselves in from the product that was ordered.
        prefillLines.forEach(function (line) {
            const row = addSupplyItem(String(line.product_id));
            if (!row) return;

            row.querySelector('.item-quantity').value = line.quantity;
            row.querySelector('.item-unit-cost').value = line.unit_cost;
            row.querySelector('.product-select').dispatchEvent(new Event('change'));
            updateSubtotal(row);
        });

        // Previous datalist-driven handlers removed in favour of custom type-ahead
    });
</script>
<style>
    /* ---- Page shell ------------------------------------------------- */
    .supply-form {
        width: 100%;
    }

    .supply-notice {
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

    .supply-notice .bi {
        color: #6c757d;
        font-size: 1.05rem;
        line-height: 1.4;
    }

    /* ---- Section cards ---------------------------------------------- */
    .supply-card:hover {
        /* keep sections calm; no lift on a form page */
        box-shadow: var(--card-shadow);
    }

    .supply-card__header {
        display: flex;
        align-items: flex-start;
        gap: 0.85rem;
        border-bottom: 0;
        padding-bottom: 0.35rem;
    }

    /* Light inner surface for a section's main content */
    .supply-panel {
        padding: 1.1rem;
        border-radius: 6px;
        background-color: #f5f8f6;
    }

    .supply-card__step {
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

    .supply-card__title {
        margin: 0;
        font-size: 1.02rem;
        font-weight: 600;
        color: var(--dark-text);
        letter-spacing: 0.01em;
    }

    .supply-card__subtitle {
        margin: 0.15rem 0 0;
        font-size: 0.825rem;
        font-weight: 400;
        color: #6c757d;
    }

    /* ---- Quick-add search ------------------------------------------- */
    .supply-search__icon {
        position: absolute;
        top: 50%;
        left: 0.85rem;
        transform: translateY(-50%);
        color: #9aa0a6;
        pointer-events: none;
        z-index: 3;
    }

    .supply-search__input {
        padding-left: 2.35rem;
    }

    #product-suggestions .list-group-item {
        border-left: 0;
        border-right: 0;
        padding: 0.55rem 0.85rem;
        font-size: 0.9rem;
    }

    /* ---- Item rows --------------------------------------------------- */
    #supply-items {
        counter-reset: supply-item;
    }

    .supply-item {
        position: relative;
        padding: 1rem 1.1rem 1.1rem;
        margin-bottom: 1rem;
        border: 1px solid #e3e8e4;
        border-radius: 6px;
        background-color: #fff;
        transition: opacity 0.3s ease, border-color 0.2s ease;
    }

    .supply-item:focus-within {
        border-color: var(--primary-light);
    }

    .supply-item__bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.85rem;
    }

    .supply-item__label {
        counter-increment: supply-item;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        color: var(--primary);
    }

    .supply-item__label::after {
        content: " " counter(supply-item);
    }

    .supply-item .remove-item {
        border: 0;
        background: transparent;
        color: #9aa0a6;
        line-height: 1;
        padding: 0.3rem 0.4rem;
        border-radius: 4px;
        font-size: 0.8rem;
        transition: color 0.2s ease, background-color 0.2s ease;
    }

    .supply-item .remove-item:hover {
        color: var(--danger);
        background-color: rgba(231, 111, 81, 0.1);
    }

    .supply-item .form-label {
        font-size: 0.82rem;
        font-weight: 500;
        margin-bottom: 0.3rem;
    }

    .subtotal-highlight {
        font-weight: 600;
        background-color: #f1f5f2;
    }

    .item-subtotal {
        transition: all 0.3s ease;
    }

    .supply-add-btn {
        border-style: dashed;
        width: 100%;
        padding: 0.6rem 1rem;
        font-weight: 500;
        background-color: #fff;
    }

    .supply-add-btn:hover {
        background-color: var(--primary);
    }

    /* ---- Summary card ------------------------------------------------ */
    .supply-summary__inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .supply-summary__label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        color: #6c757d;
    }

    .supply-summary__value {
        display: block;
        margin-top: 0.15rem;
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--dark-text);
    }

    #supply-total {
        transition: all 0.3s ease;
    }

    .supply-summary__actions {
        display: flex;
        gap: 0.6rem;
    }

    .supply-summary__hint {
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

    .supply-card__title .text-danger {
        font-size: 0.8em;
        vertical-align: super;
        margin-left: 2px;
    }

    @media (max-width: 768px) {
        .supply-item {
            padding: 0.9rem;
        }

        .supply-summary__inner {
            align-items: flex-start;
            flex-direction: column;
        }

        .supply-summary__actions {
            width: 100%;
        }

        .supply-summary__actions .btn {
            flex: 1;
        }

        .form-label .text-danger {
            font-size: 0.9em;
        }
    }
</style>
@endsection 
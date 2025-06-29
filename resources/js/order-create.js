// Order creation page functionality
const orderForm = document.getElementById('orderForm');
const locationSelect = document.getElementById('fulfillment_location_id');
const productSelects = document.querySelectorAll('.product-select');
const stockInfoContainer = document.getElementById('stock-info');

// Track selected products
let selectedProducts = new Set();

// Update available locations when products are selected
async function updateAvailableLocations() {
    try {
        const productIds = Array.from(selectedProducts);
        
        if (productIds.length === 0) {
            locationSelect.innerHTML = '<option value="">Select products first...</option>';
            return;
        }
        
        // Show loading state
        locationSelect.disabled = true;
        locationSelect.innerHTML = '<option value="">Loading available locations...</option>';
        
        const response = await fetch(`/api/orders/available-fulfillment-locations?product_ids=${JSON.stringify(productIds)}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) {
            throw new Error('Failed to fetch available locations');
        }
        
        const data = await response.json();
        
        // Reset and populate location select
        locationSelect.innerHTML = '<option value="">Choose fulfillment location...</option>';
        
        if (data.available_locations && data.available_locations.length > 0) {
            data.available_locations.forEach(location => {
                const option = document.createElement('option');
                option.value = location.id;
                option.textContent = `${location.name} (${location.type}) - ${location.available_quantity} available`;
                option.dataset.locationType = location.type;
                option.dataset.availableQuantity = location.available_quantity;
                locationSelect.appendChild(option);
            });
        } else {
            locationSelect.innerHTML += '<option value="" disabled>No locations have sufficient stock for all selected products</option>';
        }
        
        locationSelect.disabled = false;
        
    } catch (error) {
        console.error('Error updating locations:', error);
        locationSelect.innerHTML = '<option value="">Error loading locations. Please try again.</option>';
        locationSelect.disabled = true;
        
        // Show error message to user
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger mt-3';
        errorDiv.innerHTML = `<i class="bi bi-exclamation-triangle me-2"></i>${error.message}`;
        stockInfoContainer.innerHTML = '';
        stockInfoContainer.appendChild(errorDiv);
    }
}

// Update stock info display when location is selected
function updateStockInfo() {
    const selectedOption = locationSelect.options[locationSelect.selectedIndex];
    stockInfoContainer.innerHTML = '';
    
    if (!selectedOption || !selectedOption.value) {
        return;
    }
    
    try {
        const stockInfo = JSON.parse(selectedOption.dataset.stockInfo);
        
        const stockTable = document.createElement('div');
        stockTable.className = 'table-responsive mt-3';
        stockTable.innerHTML = `
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Product</th>
                        <th class="text-center">Available Stock</th>
                        <th class="text-center">Total Stock</th>
                        <th class="text-center">Reserved</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        `;
        
        const tbody = stockTable.querySelector('tbody');
        
        Object.entries(stockInfo).forEach(([productId, info]) => {
            const product = document.querySelector(`.product-select option[value="${productId}"]`);
            const productName = product ? product.textContent : `Product #${productId}`;
            
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${productName}</td>
                <td class="text-center">
                    <span class="badge ${info.available_quantity > 10 ? 'bg-success' : 'bg-warning text-dark'}">
                        ${info.available_quantity}
                    </span>
                </td>
                <td class="text-center">${info.total_quantity}</td>
                <td class="text-center">${info.reserved_quantity}</td>
            `;
            tbody.appendChild(row);
        });
        
        stockInfoContainer.appendChild(stockTable);
        
    } catch (error) {
        console.error('Error updating stock info:', error);
        stockInfoContainer.innerHTML = `
            <div class="alert alert-danger mt-3">
                <i class="bi bi-exclamation-triangle me-2"></i>Error displaying stock information
            </div>
        `;
    }
}

// Event listeners
productSelects.forEach(select => {
    select.addEventListener('change', function() {
        if (this.value) {
            selectedProducts.add(this.value);
        } else {
            selectedProducts.delete(this.value);
        }
        updateAvailableLocations();
    });
});

locationSelect.addEventListener('change', updateStockInfo);

// Form validation
function validateForm() {
    const fromLocationId = document.getElementById('from_location_id').value;
    const toLocationId = document.getElementById('to_location_id').value;
    const items = document.querySelectorAll('.item-row');
    const errors = [];
    
    // Check if locations are selected
    if (!fromLocationId) {
        errors.push('Please select a source warehouse');
    }
    
    if (!toLocationId) {
        errors.push('Please select a destination retailer');
    }
    
    // Check if at least one item is added
    if (items.length === 0) {
        errors.push('Please add at least one product to transfer');
    }
    
    // Validate each item
    let isValid = true;
    items.forEach((item, index) => {
        const productSelect = item.querySelector('.product-select');
        const quantityInput = item.querySelector('.quantity-input');
        
        if (!productSelect.value) {
            errors.push(`Please select a product for item ${index + 1}`);
            isValid = false;
        }
        
        if (!validateQuantity(quantityInput, index)) {
            isValid = false;
        }
    });
    
    if (errors.length > 0) {
        // Show errors in a nice format
        const errorContainer = document.createElement('div');
        errorContainer.className = 'alert alert-danger mb-4';
        errorContainer.innerHTML = `
            <h6 class="alert-heading mb-2">Please fix the following errors:</h6>
            <ul class="mb-0">
                ${errors.map(error => `<li>${error}</li>`).join('')}
            </ul>
        `;
        
        const form = document.getElementById('pickingForm');
        form.insertBefore(errorContainer, form.firstChild);
        
        // Scroll to top to show errors
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return false;
    }
    
    return isValid;
}

// Form submission handler
document.getElementById('pickingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Clear previous error messages
    const previousErrors = this.querySelectorAll('.alert-danger');
    previousErrors.forEach(error => error.remove());
    
    if (validateForm()) {
        // Show loading state
        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
        submitBtn.disabled = true;
        
        // Submit the form
        this.submit();
    }
});

// Add initial item
addItem();

// Add warehouse selection handler
document.getElementById('from_location_id').addEventListener('change', function() {
    // Clear stock cache when warehouse changes
    stockCache = {};
    
    // Reset all product selects
    document.querySelectorAll('.product-select').forEach(select => {
        select.selectedIndex = 0;
        updateStockInfo(select, select.name.match(/\[(\d+)\]/)[1]);
    });
    
    // Update warehouse warning
    updateWarehouseWarning();
}); 
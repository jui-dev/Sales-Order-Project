/**
 * Products Filter Enhancement
 * Handles dynamic subcategory loading and filter interactions
 */

// Prevent duplicate class declaration
if (typeof ProductsFilter === 'undefined') {
    class ProductsFilter {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeSubcategoryFilter();
    }

    bindEvents() {
        // Handle category change in filter modal
        const categorySelect = document.querySelector('#filter-form select[name="category_id"]');
        if (categorySelect) {
            categorySelect.addEventListener('change', (e) => {
                this.handleCategoryChange(e.target.value);
            });
        }

        // Handle subcategory change in filter modal
        const subcategorySelect = document.querySelector('#filter-form select[name="subcategory_id"]');
        if (subcategorySelect) {
            subcategorySelect.addEventListener('change', (e) => {
                this.handleSubcategoryChange(e.target.value);
            });
        }
    }

    initializeSubcategoryFilter() {
        // Initialize subcategory dropdown with current category if any
        const categorySelect = document.querySelector('#filter-form select[name="category_id"]');
        const subcategorySelect = document.querySelector('#filter-form select[name="subcategory_id"]');
        
        if (categorySelect && subcategorySelect) {
            const currentCategoryId = categorySelect.value;
            if (currentCategoryId) {
                this.loadSubcategories(currentCategoryId);
            }
        }
    }

    handleCategoryChange(categoryId) {
        const subcategorySelect = document.querySelector('#filter-form select[name="subcategory_id"]');
        if (!subcategorySelect) return;

        if (categoryId) {
            this.loadSubcategories(categoryId);
        } else {
            // Clear subcategory options when no category is selected
            this.clearSubcategoryOptions();
        }
    }

    handleSubcategoryChange(subcategoryId) {
        // Additional logic for subcategory changes can be added here
        console.log('Subcategory changed to:', subcategoryId);
    }

    async loadSubcategories(categoryId) {
        const subcategorySelect = document.querySelector('#filter-form select[name="subcategory_id"]');
        if (!subcategorySelect) return;

        console.log('Loading subcategories for category ID:', categoryId);

        try {
            // Show loading state
            subcategorySelect.disabled = true;
            subcategorySelect.innerHTML = '<option value="">Loading...</option>';

            // Make AJAX request to get subcategories
            const url = `/products/ajax/subcategories?category_id=${categoryId}`;
            console.log('Making request to:', url);
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                }
            });

            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            console.log('Content-Type:', response.headers.get('content-type'));
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // Try to get the response as text first for debugging
            const responseText = await response.text();
            console.log('Raw response text:', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse response as JSON:', parseError);
                throw new Error('Invalid JSON response from server');
            }
            
            console.log('Subcategories response:', data);
            console.log('Response data type:', typeof data);
            console.log('Response data keys:', Object.keys(data));
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Handle error responses from the HasApiResponses trait
            if (data.status === 'error') {
                throw new Error(data.message || 'Server error occurred');
            }

            // Check if options exist in the response
            if (!data.options) {
                console.error('No options found in response data:', data);
                
                // Handle the case where the response uses the HasApiResponses trait format
                if (data.status === 'empty' && data.data && Array.isArray(data.data)) {
                    console.log('Received empty response from API trait format');
                    this.clearSubcategoryOptions();
                    return;
                }
                
                // Handle the case where the response uses the HasApiResponses trait format with data
                if (data.status === 'success' && data.data && Array.isArray(data.data)) {
                    console.log('Received success response from API trait format');
                    // Convert the data array to options format
                    const options = { '': 'All Subcategories' };
                    data.data.forEach(item => {
                        if (item.id && item.name) {
                            options[item.id] = item.name;
                        }
                    });
                    this.updateSubcategoryOptions(options);
                    return;
                }
                
                // Try to parse the response as a fallback
                if (typeof data === 'string') {
                    try {
                        const parsedData = JSON.parse(data);
                        if (parsedData.options) {
                            console.log('Successfully parsed response as JSON:', parsedData);
                            this.updateSubcategoryOptions(parsedData.options);
                            return;
                        }
                    } catch (parseError) {
                        console.error('Failed to parse response as JSON:', parseError);
                    }
                }
                
                throw new Error('Invalid response format: missing options');
            }

            // Update subcategory options
            this.updateSubcategoryOptions(data.options);

        } catch (error) {
            console.error('Error loading subcategories:', error);
            console.error('Error details:', {
                message: error.message,
                stack: error.stack,
                name: error.name
            });
            this.clearSubcategoryOptions();
            this.showError(`Failed to load subcategories: ${error.message}`);
        } finally {
            subcategorySelect.disabled = false;
        }
    }

    updateSubcategoryOptions(options) {
        const subcategorySelect = document.querySelector('#filter-form select[name="subcategory_id"]');
        if (!subcategorySelect) {
            console.error('Subcategory select element not found');
            return;
        }

        console.log('Updating subcategory options with:', options);
        console.log('Options type:', typeof options);
        console.log('Options keys:', options ? Object.keys(options) : 'null/undefined');

        // Validate options data
        if (!options || typeof options !== 'object') {
            console.error('Invalid options data received:', options);
            this.clearSubcategoryOptions();
            return;
        }

        // Clear existing options
        subcategorySelect.innerHTML = '';

        // Add new options
        Object.entries(options).forEach(([value, label]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            subcategorySelect.appendChild(option);
        });

        console.log('Subcategory options updated successfully');
    }

    clearSubcategoryOptions() {
        const subcategorySelect = document.querySelector('#filter-form select[name="subcategory_id"]');
        if (!subcategorySelect) return;

        subcategorySelect.innerHTML = '<option value="">All Subcategories</option>';
    }

    showError(message) {
        // Create or update error message in the modal
        let errorDiv = document.querySelector('#filter-form .alert-danger');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger mt-3';
            const formBody = document.querySelector('#filter-form .modal-body');
            if (formBody) {
                formBody.appendChild(errorDiv);
            }
        }
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';

        // Auto-hide error after 5 seconds
        setTimeout(() => {
            errorDiv.style.display = 'none';
        }, 5000);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on the products page with filter modal
    const filterModal = document.querySelector('#filterModal');
    if (filterModal && !window.productsFilter) {
        window.productsFilter = new ProductsFilter();
    }
});
} 
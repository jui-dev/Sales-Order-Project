/**
 * Unified Search, Filter, and Sort System
 * Provides consistent functionality across all list-based pages
 */

class UnifiedSearchSystem {
    constructor(options = {}) {
        this.options = {
            searchInput: '#global-search',
            filterForm: '#filter-form',
            sortForm: '#sort-form',
            tableContainer: '.table-responsive',
            searchDelay: 400,
            ...options
        };
        
        this.searchTimeout = null;
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.setupDebouncedSearch();
    }
    
    bindEvents() {
        try {
            // Search input events
            const searchInput = document.querySelector(this.options.searchInput);
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    try {
                        this.handleSearchInput(e.target.value);
                    } catch (error) {
                        console.error('Error handling search input:', error);
                    }
                });
                
                searchInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        try {
                            this.performSearch();
                        } catch (error) {
                            console.error('Error performing search:', error);
                        }
                    }
                });
            }
            
            // Filter form events
            const filterForm = document.querySelector(this.options.filterForm);
            if (filterForm) {
                filterForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    try {
                        this.applyFilters();
                    } catch (error) {
                        console.error('Error applying filters:', error);
                    }
                });
            }
            
            // Sort form events
            const sortForm = document.querySelector(this.options.sortForm);
            if (sortForm) {
                const sortSelects = sortForm.querySelectorAll('select');
                sortSelects.forEach(select => {
                    select.addEventListener('change', () => {
                        try {
                            this.applySorting();
                        } catch (error) {
                            console.error('Error applying sorting:', error);
                        }
                    });
                });
            }
            
            // Clear filters button
            const clearFiltersBtn = document.querySelector('.clear-filters');
            if (clearFiltersBtn) {
                clearFiltersBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    try {
                        this.clearAllFilters();
                    } catch (error) {
                        console.error('Error clearing filters:', error);
                    }
                });
            }
        } catch (error) {
            console.error('Error binding events:', error);
        }
    }
    
    setupDebouncedSearch() {
        const searchInput = document.querySelector(this.options.searchInput);
        if (!searchInput) return;
        
        searchInput.addEventListener('input', (e) => {
            try {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    try {
                        this.performSearch();
                    } catch (error) {
                        console.error('Error in debounced search:', error);
                    }
                }, this.options.searchDelay);
            } catch (error) {
                console.error('Error setting up debounced search:', error);
            }
        });
    }
    
    handleSearchInput(value) {
        // Show/hide search icon based on input
        const searchIcon = document.querySelector('.search-icon');
        if (searchIcon) {
            if (value.length > 0) {
                searchIcon.innerHTML = '<i class="bi bi-search"></i>';
            } else {
                searchIcon.innerHTML = '<i class="bi bi-search"></i>';
            }
        }
    }
    
    performSearch() {
        const searchInput = document.querySelector(this.options.searchInput);
        if (!searchInput) return;
        
        const searchValue = searchInput.value.trim();
        this.updateURL({ search: searchValue, page: 1 });
        this.reloadPage();
    }
    
    applyFilters() {
        const filterForm = document.querySelector(this.options.filterForm);
        if (!filterForm) return;
        
        const formData = new FormData(filterForm);
        const filters = {};
        
        // Keep empty values too: updateURL drops them, which is how a filter
        // gets unset when the user picks "-- Any --" again.
        for (let [key, value] of formData.entries()) {
            filters[key] = value;
        }

        // Reset to first page when applying filters
        filters.page = 1;
        
        this.updateURL(filters);
        this.reloadPage();
    }
    
    applySorting() {
        const sortForm = document.querySelector(this.options.sortForm);
        if (!sortForm) return;
        
        const formData = new FormData(sortForm);
        const sortParams = {};
        
        for (let [key, value] of formData.entries()) {
            if (value && value !== '') {
                sortParams[key] = value;
            }
        }
        
        this.updateURL(sortParams);
        this.reloadPage();
    }
    
    clearAllFilters() {
        const currentURL = new URL(window.location);
        const basePath = currentURL.pathname;
        
        // Redirect to base page without any query parameters
        window.location.href = basePath;
    }
    
    updateURL(params) {
        const currentURL = new URL(window.location);
        
        // Update or add parameters
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                currentURL.searchParams.set(key, params[key]);
            } else {
                currentURL.searchParams.delete(key);
            }
        });
        
        // Update browser URL without reloading
        window.history.pushState({}, '', currentURL);
    }
    
    reloadPage() {
        // Show loading spinner
        this.showLoadingSpinner();
        
        // Use a small delay to ensure the spinner is shown
        setTimeout(() => {
            // Reload the page with current URL
            window.location.reload();
        }, 100);
    }
    
    showLoadingSpinner() {
        const spinner = document.querySelector('.loading-spinner');
        if (spinner) {
            spinner.style.display = 'block';
        }
        
        // Hide spinner after a short delay
        setTimeout(() => {
            if (spinner) {
                spinner.style.display = 'none';
            }
        }, 1000);
    }
    
    // Utility method to check if any filters are active
    hasActiveFilters() {
        const currentURL = new URL(window.location);
        const searchParams = currentURL.searchParams;
        
        // Check for common filter parameters
        const filterParams = ['search', 'status', 'date_from', 'date_to', 'type', 'category', 'price_min', 'price_max', 'stock_min', 'stock_max'];
        
        return filterParams.some(param => searchParams.has(param));
    }
    
    // Utility method to get current search value
    getCurrentSearch() {
        const currentURL = new URL(window.location);
        return currentURL.searchParams.get('search') || '';
    }
    
    // Utility method to get current filters
    getCurrentFilters() {
        const currentURL = new URL(window.location);
        const filters = {};
        
        for (let [key, value] of currentURL.searchParams.entries()) {
            if (key !== 'page' && key !== 'sort' && key !== 'direction') {
                filters[key] = value;
            }
        }
        
        return filters;
    }
    
    // Cleanup method to handle page unload
    cleanup() {
        try {
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = null;
            }
        } catch (error) {
            console.error('Error during cleanup:', error);
        }
    }
}

// Initialize the system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on a page that uses unified search
    const searchInput = document.querySelector('#global-search');
    const filterForm = document.querySelector('#filter-form');
    
    if (!searchInput && !filterForm) {
        // Not on a page with unified search, don't initialize
        return;
    }
    
    try {
        // Initialize unified search system
        window.unifiedSearch = new UnifiedSearchSystem();
        
        // Auto-focus search input if it exists
        if (searchInput && !searchInput.value) {
            searchInput.focus();
        }
        
        // Show active filters indicator
        if (window.unifiedSearch && window.unifiedSearch.hasActiveFilters()) {
            const activeFiltersBadge = document.querySelector('.active-filters-badge');
            if (activeFiltersBadge) {
                activeFiltersBadge.style.display = 'inline-block';
            }
        }
        
        // Add page unload event listener for cleanup
        window.addEventListener('beforeunload', function() {
            if (window.unifiedSearch && typeof window.unifiedSearch.cleanup === 'function') {
                window.unifiedSearch.cleanup();
            }
        });
        
    } catch (error) {
        console.error('Error initializing UnifiedSearchSystem:', error);
        // Don't let the error break the page
    }
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UnifiedSearchSystem;
} 
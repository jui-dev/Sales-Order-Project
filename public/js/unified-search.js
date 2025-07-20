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
        // Search input events
        const searchInput = document.querySelector(this.options.searchInput);
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.handleSearchInput(e.target.value);
            });
            
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.performSearch();
                }
            });
        }
        
        // Filter form events
        const filterForm = document.querySelector(this.options.filterForm);
        if (filterForm) {
            filterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.applyFilters();
            });
        }
        
        // Sort form events
        const sortForm = document.querySelector(this.options.sortForm);
        if (sortForm) {
            const sortSelects = sortForm.querySelectorAll('select');
            sortSelects.forEach(select => {
                select.addEventListener('change', () => {
                    this.applySorting();
                });
            });
        }
        
        // Clear filters button
        const clearFiltersBtn = document.querySelector('.clear-filters');
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.clearAllFilters();
            });
        }
    }
    
    setupDebouncedSearch() {
        const searchInput = document.querySelector(this.options.searchInput);
        if (!searchInput) return;
        
        searchInput.addEventListener('input', (e) => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.performSearch();
            }, this.options.searchDelay);
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
        
        for (let [key, value] of formData.entries()) {
            if (value && value !== '') {
                filters[key] = value;
            }
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
        
        // Reload the page with current URL
        window.location.reload();
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
}

// Initialize the system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize unified search system
    window.unifiedSearch = new UnifiedSearchSystem();
    
    // Auto-focus search input if it exists
    const searchInput = document.querySelector('#global-search');
    if (searchInput && !searchInput.value) {
        searchInput.focus();
    }
    
    // Show active filters indicator
    if (window.unifiedSearch.hasActiveFilters()) {
        const activeFiltersBadge = document.querySelector('.active-filters-badge');
        if (activeFiltersBadge) {
            activeFiltersBadge.style.display = 'inline-block';
        }
    }
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UnifiedSearchSystem;
} 
/**
 * DataTables Utility Functions
 * Provides safe initialization and error handling for DataTables
 */

class DataTablesUtils {
    /**
     * Safely initialize a DataTable with error handling
     * @param {string} tableId - The ID of the table element
     * @param {object} options - DataTables configuration options
     * @returns {object|null} - DataTable instance or null if failed
     */
    static safeInit(tableId, options = {}) {
        try {
            console.log(`Starting safeInit for table '${tableId}'`);
            
            // Check if jQuery and DataTables are available
            if (typeof $ === 'undefined') {
                console.error('jQuery is not loaded');
                return null;
            }

            if (typeof $.fn.DataTable === 'undefined') {
                console.error('DataTables is not loaded');
                return null;
            }

            // Remove hash from tableId if present
            const cleanTableId = tableId.replace('#', '');
            console.log(`Clean table ID: '${cleanTableId}'`);
            
            // Check if table exists
            const tableElement = document.getElementById(cleanTableId);
            if (!tableElement) {
                console.error(`Table with ID '${cleanTableId}' not found`);
                console.log('Available tables on page:', Array.from(document.querySelectorAll('table')).map(t => t.id || 'no-id'));
                
                // Try alternative selectors
                const alternativeSelectors = [
                    `#${cleanTableId}`,
                    `table[id="${cleanTableId}"]`,
                    `table[id*="${cleanTableId}"]`
                ];
                
                for (const selector of alternativeSelectors) {
                    const altElement = document.querySelector(selector);
                    if (altElement) {
                        console.log(`Found table using selector: ${selector}`);
                        break;
                    }
                }
                
                return null;
            }

            console.log(`Table element found for '${cleanTableId}'`);

            // Basic table structure check (simplified)
            const thead = tableElement.querySelector('thead');
            const tbody = tableElement.querySelector('tbody');
            
            if (!thead || !tbody) {
                console.error('Table must have both thead and tbody elements');
                return null;
            }

            console.log(`Table structure validated for '${cleanTableId}'`);

            // Clean up table for DataTables initialization
            this.cleanupForDataTables(cleanTableId);
            this.fixCommonIssues(cleanTableId);

            console.log(`Table cleanup completed for '${cleanTableId}'`);

            // Initialize DataTable with simplified configuration
            const table = $(`#${cleanTableId}`).DataTable({
                ...options,
                // Disable responsive to avoid conflicts
                responsive: false,
                // Add basic error handling
                createdRow: function(row, data, dataIndex) {
                    try {
                        // Basic cell validation
                        if (row && row.cells) {
                            for (let i = 0; i < row.cells.length; i++) {
                                const cell = row.cells[i];
                                if (cell && typeof cell === 'object' && cell.nodeType) {
                                    if (cell._DT_CellIndex === undefined) {
                                        cell._DT_CellIndex = i;
                                    }
                                }
                            }
                        }
                    } catch (cellError) {
                        console.warn('Error in createdRow callback:', cellError);
                    }
                }
            });
            
            console.log(`DataTable '${cleanTableId}' initialized successfully`);
            return table;

        } catch (error) {
            console.error(`Error initializing DataTable '${cleanTableId || tableId}':`, error);
            console.error('Error details:', error.message);
            return null;
        }
    }

    /**
     * Apply a fix for DataTables cell indexing issues
     * This patches the DataTables internal cell indexing mechanism
     */
    static applyCellIndexingFix() {
        if (typeof $.fn.DataTable === 'undefined') {
            return;
        }

        // Store original DataTables methods
        const originalApi = $.fn.DataTable.Api;
        const originalExt = $.fn.DataTable.ext;

        // Patch the cell indexing mechanism
        if (originalExt && originalExt.internal) {
            const originalCellIndex = originalExt.internal._fnCellIndex;
            
            if (originalCellIndex) {
                originalExt.internal._fnCellIndex = function(cell, row, column) {
                    try {
                        // Ensure cell is valid before setting index
                        if (cell && typeof cell === 'object' && cell.nodeType) {
                            return originalCellIndex.call(this, cell, row, column);
                        } else {
                            console.warn('Invalid cell detected in _fnCellIndex, skipping');
                            return -1;
                        }
                    } catch (error) {
                        console.warn('Error in _fnCellIndex:', error);
                        return -1;
                    }
                };
            }
        }

        // Patch the row processing mechanism
        if (originalExt && originalExt.internal && originalExt.internal._fnBuildSearchArray) {
            const originalBuildSearchArray = originalExt.internal._fnBuildSearchArray;
            
            originalExt.internal._fnBuildSearchArray = function(settings, row, index) {
                try {
                    // Ensure row and cells are valid
                    if (row && row.cells) {
                        for (let i = 0; i < row.cells.length; i++) {
                            const cell = row.cells[i];
                            if (cell && typeof cell === 'object' && cell.nodeType) {
                                // Ensure cell has proper indexing
                                if (cell._DT_CellIndex === undefined) {
                                    cell._DT_CellIndex = i;
                                }
                            }
                        }
                    }
                    return originalBuildSearchArray.call(this, settings, row, index);
                } catch (error) {
                    console.warn('Error in _fnBuildSearchArray:', error);
                    return [];
                }
            };
        }

        // Patch the cell creation mechanism
        if (originalExt && originalExt.internal && originalExt.internal._fnCreateTr) {
            const originalCreateTr = originalExt.internal._fnCreateTr;
            
            originalExt.internal._fnCreateTr = function(settings, rowIdx, data, rowData) {
                try {
                    const tr = originalCreateTr.call(this, settings, rowIdx, data, rowData);
                    
                    // Ensure all cells in the created row are properly indexed
                    if (tr && tr.cells) {
                        for (let i = 0; i < tr.cells.length; i++) {
                            const cell = tr.cells[i];
                            if (cell && typeof cell === 'object' && cell.nodeType) {
                                if (cell._DT_CellIndex === undefined) {
                                    cell._DT_CellIndex = i;
                                }
                            }
                        }
                    }
                    
                    return tr;
                } catch (error) {
                    console.warn('Error in _fnCreateTr:', error);
                    return null;
                }
            };
        }
    }

    /**
     * Ensure cell integrity before DataTables initialization
     * @param {string} tableId - The ID of the table element
     */
    static ensureCellIntegrity(tableId) {
        const tableElement = document.getElementById(tableId);
        if (!tableElement) {
            return;
        }

        // Ensure all cells have proper content and structure
        const allCells = tableElement.querySelectorAll('td, th');
        allCells.forEach((cell, index) => {
            // Ensure cell has content
            if (!cell.textContent.trim() && !cell.innerHTML.trim()) {
                cell.innerHTML = '&nbsp;';
            }
            
            // Remove any problematic attributes that might interfere
            cell.removeAttribute('data-label');
            
            // Clean up any existing DataTables properties
            if (cell._DT_CellIndex !== undefined) {
                delete cell._DT_CellIndex;
            }
            if (cell._DT_RowIndex !== undefined) {
                delete cell._DT_RowIndex;
            }
        });

        // Ensure all rows have the same number of cells as headers
        const headerCells = tableElement.querySelectorAll('thead th');
        const headerCount = headerCells.length;
        
        const bodyRows = tableElement.querySelectorAll('tbody tr');
        bodyRows.forEach((row, rowIndex) => {
            const rowCells = row.querySelectorAll('td');
            const cellCount = rowCells.length;
            
            // Skip rows with colspan (like empty state rows)
            if (row.querySelector('td[colspan]')) {
                return;
            }
            
            // If row has fewer cells than headers, add empty cells
            if (cellCount < headerCount) {
                for (let i = cellCount; i < headerCount; i++) {
                    const newCell = document.createElement('td');
                    newCell.innerHTML = '&nbsp;';
                    row.appendChild(newCell);
                }
            }
            // If row has more cells than headers, remove extra cells
            else if (cellCount > headerCount) {
                for (let i = cellCount - 1; i >= headerCount; i--) {
                    if (rowCells[i]) {
                        rowCells[i].remove();
                    }
                }
            }
        });
    }

    /**
     * Pre-process table to prevent DataTables cell indexing issues
     * @param {string} tableId - The ID of the table element
     */
    static preprocessTableForDataTables(tableId) {
        const tableElement = document.getElementById(tableId);
        if (!tableElement) {
            return;
        }

        // Ensure all cells are properly structured
        const allCells = tableElement.querySelectorAll('td, th');
        allCells.forEach((cell, index) => {
            // Ensure cell is a valid DOM element
            if (!cell || typeof cell !== 'object' || !cell.nodeType) {
                console.warn('Invalid cell found, skipping');
                return;
            }

            // Ensure cell has proper content
            if (!cell.textContent.trim() && !cell.innerHTML.trim()) {
                cell.innerHTML = '&nbsp;';
            }

            // Remove any problematic attributes
            cell.removeAttribute('data-label');
            
            // Clean up any existing DataTables properties
            if (cell._DT_CellIndex !== undefined) {
                delete cell._DT_CellIndex;
            }
            if (cell._DT_RowIndex !== undefined) {
                delete cell._DT_RowIndex;
            }

            // Ensure cell has proper node properties
            if (!cell.nodeType) {
                cell.nodeType = 1; // Element node
            }
        });

        // Ensure all rows have consistent cell counts
        const headerCells = tableElement.querySelectorAll('thead th');
        const headerCount = headerCells.length;
        
        const bodyRows = tableElement.querySelectorAll('tbody tr');
        bodyRows.forEach((row, rowIndex) => {
            // Skip rows with colspan (like empty state rows)
            if (row.querySelector('td[colspan]')) {
                return;
            }

            const rowCells = row.querySelectorAll('td');
            const cellCount = rowCells.length;
            
            // If row has fewer cells than headers, add empty cells
            if (cellCount < headerCount) {
                for (let i = cellCount; i < headerCount; i++) {
                    const newCell = document.createElement('td');
                    newCell.innerHTML = '&nbsp;';
                    newCell.nodeType = 1; // Ensure proper node type
                    row.appendChild(newCell);
                }
            }
            // If row has more cells than headers, remove extra cells
            else if (cellCount > headerCount) {
                for (let i = cellCount - 1; i >= headerCount; i--) {
                    if (rowCells[i]) {
                        rowCells[i].remove();
                    }
                }
            }
        });
    }

    /**
     * Validate table structure for DataTables compatibility
     * @param {HTMLElement} tableElement - The table element to validate
     * @returns {object} - Validation result with isValid and error properties
     */
    static validateTableStructure(tableElement) {
        try {
            // Check for thead
            const thead = tableElement.querySelector('thead');
            if (!thead) {
                return { isValid: false, error: 'Table must have a thead element' };
            }

            // Check for tbody
            const tbody = tableElement.querySelector('tbody');
            if (!tbody) {
                return { isValid: false, error: 'Table must have a tbody element' };
            }

            // Get header row
            const headerRow = thead.querySelector('tr');
            if (!headerRow) {
                return { isValid: false, error: 'Table must have a header row' };
            }

            // Count header columns
            const headerColumns = headerRow.querySelectorAll('th').length;
            if (headerColumns === 0) {
                return { isValid: false, error: 'Table must have at least one header column' };
            }

            // Check body rows
            const bodyRows = tbody.querySelectorAll('tr');
            if (bodyRows.length === 0) {
                // Empty table is valid
                return { isValid: true, error: null };
            }

            // Check first data row (skip empty state rows)
            let firstDataRow = null;
            for (let row of bodyRows) {
                const cells = row.querySelectorAll('td');
                if (cells.length > 0 && !row.querySelector('[colspan]')) {
                    firstDataRow = row;
                    break;
                }
            }

            if (firstDataRow) {
                const bodyColumns = firstDataRow.querySelectorAll('td').length;
                if (headerColumns !== bodyColumns) {
                    return { 
                        isValid: false, 
                        error: `Column count mismatch: ${headerColumns} headers vs ${bodyColumns} body columns` 
                    };
                }
            }

            return { isValid: true, error: null };

        } catch (error) {
            return { isValid: false, error: `Validation error: ${error.message}` };
        }
    }

    /**
     * Get table statistics for debugging
     * @param {string} tableId - The ID of the table element
     * @returns {object} - Table statistics
     */
    static getTableStats(tableId) {
        const tableElement = document.getElementById(tableId);
        if (!tableElement) {
            return { error: 'Table not found' };
        }

        const thead = tableElement.querySelector('thead');
        const tbody = tableElement.querySelector('tbody');
        
        const headerColumns = thead ? thead.querySelectorAll('th').length : 0;
        const bodyRows = tbody ? tbody.querySelectorAll('tr').length : 0;
        
        const bodyColumns = [];
        if (tbody) {
            tbody.querySelectorAll('tr').forEach((row, index) => {
                const cells = row.querySelectorAll('td');
                bodyColumns.push({
                    row: index + 1,
                    columns: cells.length,
                    hasColspan: row.querySelector('[colspan]') !== null
                });
            });
        }

        return {
            tableId,
            headerColumns,
            bodyRows,
            bodyColumns,
            hasThead: !!thead,
            hasTbody: !!tbody
        };
    }

    /**
     * Fix common DataTables issues
     * @param {string} tableId - The ID of the table element
     * @returns {boolean} - Whether fixes were applied
     */
    static fixCommonIssues(tableId) {
        const tableElement = document.getElementById(tableId);
        if (!tableElement) {
            return false;
        }

        let fixesApplied = false;

        // Fix 1: Ensure all cells have content
        const emptyCells = tableElement.querySelectorAll('td:empty, th:empty');
        emptyCells.forEach(cell => {
            if (cell.innerHTML.trim() === '') {
                cell.innerHTML = '&nbsp;';
                fixesApplied = true;
            }
        });

        // Fix 2: Ensure proper colspan values in empty state rows
        const emptyStateRows = tableElement.querySelectorAll('tr:has(td[colspan])');
        emptyStateRows.forEach(row => {
            const colspanCell = row.querySelector('td[colspan]');
            const headerColumns = tableElement.querySelectorAll('thead th').length;
            const currentColspan = parseInt(colspanCell.getAttribute('colspan'));
            
            if (currentColspan !== headerColumns) {
                colspanCell.setAttribute('colspan', headerColumns);
                fixesApplied = true;
            }
        });

        // Fix 3: Remove data-label attributes that can interfere with DataTables
        const dataLabelCells = tableElement.querySelectorAll('td[data-label], th[data-label]');
        dataLabelCells.forEach(cell => {
            cell.removeAttribute('data-label');
            fixesApplied = true;
        });

        // Fix 4: Clean up any existing DataTables properties
        const allCells = tableElement.querySelectorAll('td, th');
        allCells.forEach(cell => {
            if (cell._DT_CellIndex !== undefined) {
                delete cell._DT_CellIndex;
                fixesApplied = true;
            }
            if (cell._DT_RowIndex !== undefined) {
                delete cell._DT_RowIndex;
                fixesApplied = true;
            }
        });

        // Fix 5: Ensure all cells are valid DOM elements
        allCells.forEach(cell => {
            if (!cell || typeof cell !== 'object' || !cell.nodeType) {
                console.warn('Invalid cell found, attempting to fix');
                fixesApplied = true;
            }
        });

        return fixesApplied;
    }

    /**
     * Clean up table for DataTables initialization
     * @param {string} tableId - The ID of the table element
     * @returns {boolean} - Whether cleanup was performed
     */
    static cleanupForDataTables(tableId) {
        const tableElement = document.getElementById(tableId);
        if (!tableElement) {
            return false;
        }

        let cleanupPerformed = false;

        // Remove data-label attributes
        const dataLabelCells = tableElement.querySelectorAll('td[data-label], th[data-label]');
        dataLabelCells.forEach(cell => {
            cell.removeAttribute('data-label');
            cleanupPerformed = true;
        });

        // Clean up any existing DataTables properties
        const allCells = tableElement.querySelectorAll('td, th');
        allCells.forEach(cell => {
            if (cell._DT_CellIndex !== undefined) {
                delete cell._DT_CellIndex;
                cleanupPerformed = true;
            }
            if (cell._DT_RowIndex !== undefined) {
                delete cell._DT_RowIndex;
                cleanupPerformed = true;
            }
        });

        // Remove any existing DataTables classes
        const dataTableClasses = tableElement.querySelectorAll('.dataTables_empty, .dataTables_processing, .dataTables_wrapper');
        dataTableClasses.forEach(element => {
            element.remove();
            cleanupPerformed = true;
        });

        return cleanupPerformed;
    }
}

// Make it available globally
window.DataTablesUtils = DataTablesUtils;

// Add a ready check function
DataTablesUtils.isReady = function() {
    return typeof $ !== 'undefined' && typeof $.fn.DataTable !== 'undefined';
};

// Add a wait for ready function
DataTablesUtils.waitForReady = function(callback, maxWaitTime = 5000) {
    const startTime = Date.now();
    
    function checkReady() {
        if (DataTablesUtils.isReady()) {
            callback();
            return;
        }
        
        if (Date.now() - startTime > maxWaitTime) {
            console.warn('DataTablesUtils.waitForReady: Timeout waiting for dependencies');
            return;
        }
        
        setTimeout(checkReady, 100);
    }
    
    checkReady();
};

// Add async operation utilities
DataTablesUtils.safeAsync = {
    /**
     * Safely execute an async operation with timeout and error handling
     * @param {Function} asyncFn - The async function to execute
     * @param {number} timeout - Timeout in milliseconds (default: 10000)
     * @returns {Promise} - Promise that resolves with the result or rejects with error
     */
    execute: function(asyncFn, timeout = 10000) {
        return new Promise((resolve, reject) => {
            const timeoutId = setTimeout(() => {
                reject(new Error('Operation timed out'));
            }, timeout);
            
            try {
                asyncFn()
                    .then(result => {
                        clearTimeout(timeoutId);
                        resolve(result);
                    })
                    .catch(error => {
                        clearTimeout(timeoutId);
                        reject(error);
                    });
            } catch (error) {
                clearTimeout(timeoutId);
                reject(error);
            }
        });
    },
    
    /**
     * Safely execute an async operation with retry logic
     * @param {Function} asyncFn - The async function to execute
     * @param {number} maxRetries - Maximum number of retries (default: 3)
     * @param {number} delay - Delay between retries in milliseconds (default: 1000)
     * @returns {Promise} - Promise that resolves with the result or rejects with error
     */
    executeWithRetry: function(asyncFn, maxRetries = 3, delay = 1000) {
        return new Promise((resolve, reject) => {
            let attempts = 0;
            
            function attempt() {
                attempts++;
                
                asyncFn()
                    .then(resolve)
                    .catch(error => {
                        if (attempts < maxRetries) {
                            console.log(`Attempt ${attempts} failed, retrying in ${delay}ms...`);
                            setTimeout(attempt, delay);
                        } else {
                            reject(error);
                        }
                    });
            }
            
            attempt();
        });
    }
};

// Add cleanup utilities
DataTablesUtils.cleanup = {
    /**
     * Clean up all DataTables instances and related resources
     */
    all: function() {
        try {
            if (typeof $ !== 'undefined' && $.fn.DataTable) {
                // Destroy all DataTables instances
                $('.dataTable').each(function() {
                    if ($.fn.DataTable.isDataTable(this)) {
                        $(this).DataTable().destroy();
                    }
                });
                
                // Remove DataTables related elements
                $('.dataTables_wrapper, .dataTables_processing, .dataTables_empty').remove();
                
                // Clean up any remaining DataTables properties
                $('td, th').each(function() {
                    if (this._DT_CellIndex !== undefined) delete this._DT_CellIndex;
                    if (this._DT_RowIndex !== undefined) delete this._DT_RowIndex;
                });
            }
        } catch (error) {
            console.warn('Error during DataTables cleanup:', error);
        }
    },
    
    /**
     * Clean up specific table
     * @param {string} tableId - The ID of the table to clean up
     */
    table: function(tableId) {
        try {
            if (typeof $ !== 'undefined' && $.fn.DataTable) {
                const cleanTableId = tableId.replace('#', '');
                const table = $(`#${cleanTableId}`);
                
                if (table.length && $.fn.DataTable.isDataTable(table)) {
                    table.DataTable().destroy();
                }
                
                // Clean up table cells
                table.find('td, th').each(function() {
                    if (this._DT_CellIndex !== undefined) delete this._DT_CellIndex;
                    if (this._DT_RowIndex !== undefined) delete this._DT_RowIndex;
                });
            }
        } catch (error) {
            console.warn(`Error cleaning up table ${tableId}:`, error);
        }
    }
};

// Custom DataTables plugin to fix cell indexing issues
(function($) {
    'use strict';
    
    // Store original DataTables methods
    let originalMethods = {};
    
    // Apply the fix when DataTables is loaded
    function applyDataTablesFix() {
        if (typeof $.fn.DataTable === 'undefined') {
            return;
        }
        
        // Only apply fix once
        if (originalMethods._fnCellIndex) {
            return;
        }
        
        const ext = $.fn.DataTable.ext;
        if (!ext || !ext.internal) {
            return;
        }
        
        // Store original methods
        originalMethods._fnCellIndex = ext.internal._fnCellIndex;
        originalMethods._fnBuildSearchArray = ext.internal._fnBuildSearchArray;
        originalMethods._fnCreateTr = ext.internal._fnCreateTr;
        
        // Patch _fnCellIndex method
        if (originalMethods._fnCellIndex) {
            ext.internal._fnCellIndex = function(cell, row, column) {
                try {
                    // Validate cell before processing
                    if (!cell || typeof cell !== 'object' || !cell.nodeType) {
                        console.warn('Invalid cell in _fnCellIndex, skipping');
                        return -1;
                    }
                    
                    // Ensure cell has proper properties
                    if (cell._DT_CellIndex === undefined) {
                        cell._DT_CellIndex = column || 0;
                    }
                    
                    return originalMethods._fnCellIndex.call(this, cell, row, column);
                } catch (error) {
                    console.warn('Error in patched _fnCellIndex:', error);
                    return -1;
                }
            };
        }
        
        // Patch _fnBuildSearchArray method
        if (originalMethods._fnBuildSearchArray) {
            ext.internal._fnBuildSearchArray = function(settings, row, index) {
                try {
                    // Validate row and cells
                    if (row && row.cells) {
                        for (let i = 0; i < row.cells.length; i++) {
                            const cell = row.cells[i];
                            if (cell && typeof cell === 'object' && cell.nodeType) {
                                if (cell._DT_CellIndex === undefined) {
                                    cell._DT_CellIndex = i;
                                }
                            }
                        }
                    }
                    
                    return originalMethods._fnBuildSearchArray.call(this, settings, row, index);
                } catch (error) {
                    console.warn('Error in patched _fnBuildSearchArray:', error);
                    return [];
                }
            };
        }
        
        // Patch _fnCreateTr method
        if (originalMethods._fnCreateTr) {
            ext.internal._fnCreateTr = function(settings, rowIdx, data, rowData) {
                try {
                    const tr = originalMethods._fnCreateTr.call(this, settings, rowIdx, data, rowData);
                    
                    // Ensure all cells in the created row are properly indexed
                    if (tr && tr.cells) {
                        for (let i = 0; i < tr.cells.length; i++) {
                            const cell = tr.cells[i];
                            if (cell && typeof cell === 'object' && cell.nodeType) {
                                if (cell._DT_CellIndex === undefined) {
                                    cell._DT_CellIndex = i;
                                }
                            }
                        }
                    }
                    
                    return tr;
                } catch (error) {
                    console.warn('Error in patched _fnCreateTr:', error);
                    return null;
                }
            };
        }
        
        console.log('DataTables cell indexing fix applied successfully');
    }
    
    // Apply fix when DOM is ready
    $(document).ready(function() {
        // Try to apply fix immediately
        applyDataTablesFix();
        
        // Also try after a short delay in case DataTables loads later
        setTimeout(applyDataTablesFix, 100);
        setTimeout(applyDataTablesFix, 500);
    });
    
    // Apply fix when window loads
    $(window).on('load', function() {
        setTimeout(applyDataTablesFix, 100);
    });
    
})(jQuery); 
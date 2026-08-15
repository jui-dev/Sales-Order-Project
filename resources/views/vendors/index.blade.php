@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Vendors</h1>
        <a href="{{ route('vendors.create') }}" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i>Add New Vendor
        </a>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    
    

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

<!-- Table Controls: DataTables' length + search land here, alongside Sort By.
     Hidden when there are no vendors, because DataTables is not initialised in
     that case and the card would otherwise render empty. -->
@if($vendors->isNotEmpty())
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div id="dt-length"></div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div id="dt-filter"></div>
                <div class="input-group input-group-sm w-auto" style="max-width: 260px;">
                    <label class="input-group-text bg-light" for="sort-by">Sort&nbsp;By</label>
                    <select id="sort-by" class="form-select">
                        <option value="0">ID</option>
                        <option value="1">Name</option>
                        <option value="2">Contact</option>
                    </select>
                    <button class="btn btn-outline-secondary" id="sort-direction" data-dir="asc"><i class="bi bi-sort-alpha-down"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="data-table" class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vendors as $vendor)
                    <tr>
                        <td>{{ $vendor->id }}</td>
                        <td>{{ $vendor->name }}</td>
                        <td>{{ $vendor->contact_person }}</td>
                        <td>{{ $vendor->email }}</td>
                        <td>{{ $vendor->phone }}</td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                <a href="{{ route('vendors.show', $vendor) }}" class="btn btn-sm btn-info d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('vendors.edit', $vendor) }}" class="btn btn-sm btn-warning d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="Edit Vendor">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1"
                                    data-bs-toggle="tooltip" title="Delete Vendor"
                                    onclick="if(confirm('Are you sure you want to delete this vendor?')) { 
                                        document.getElementById('delete-vendor-{{ $vendor->id }}').submit(); 
                                    }">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            <form id="delete-vendor-{{ $vendor->id }}" 
                                action="{{ route('vendors.destroy', $vendor) }}" 
                                method="POST" 
                                style="display: none;">
                                @csrf
                                @method('DELETE')
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-building display-1 d-block mb-3"></i>
                                <h5>No Vendors Found</h5>
                                <p class="mb-0">No vendors have been added to the system yet.</p>
                                <a href="{{ route('vendors.create') }}" class="btn btn-primary mt-3">
                                    <i class="bi bi-plus-circle me-1"></i>Add First Vendor
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="{{ asset('js/datatables-utils.js') }}"></script>
<script src="{{ asset('js/table-controls.js') }}"></script>

<script>
    // The empty-state row is a single <td colspan="6">, which DataTables reads
    // as a one-cell data row against a six-column header and then rejects with
    // a "Requested unknown parameter" warning. Skip initialisation entirely when
    // there is nothing to enhance.
    const hasVendors = @json($vendors->isNotEmpty());

    // Enhanced DataTables initialization using DataTablesUtils
    function initializeDataTables() {
        if (!hasVendors) {
            return;
        }

        try {
            console.log('Starting DataTables initialization...');

            // Check if required dependencies are available
            if (typeof jQuery === 'undefined') {
                console.warn('jQuery not available, retrying in 100ms');
                setTimeout(initializeDataTables, 100);
                return;
            }

            if (typeof jQuery.fn.DataTable === 'undefined') {
                console.warn('DataTables plugin not available, retrying in 100ms');
                setTimeout(initializeDataTables, 100);
                return;
            }

            // Check if table element exists
            const tableElement = document.getElementById('data-table');
            if (!tableElement) {
                console.warn('Table element with ID "data-table" not found, retrying in 100ms');
                setTimeout(initializeDataTables, 100);
                return;
            }

            console.log('Table element found, checking if already initialized...');

            // Check if table is already initialized
            if (jQuery.fn.DataTable.isDataTable('#data-table')) {
                console.log('DataTable already initialized, destroying and reinitializing');
                jQuery('#data-table').DataTable().destroy();
            }

            // Use DataTablesUtils for safe initialization
            if (typeof DataTablesUtils !== 'undefined' && DataTablesUtils.isReady()) {
                console.log('DataTablesUtils available and ready, using safe initialization...');
                
                // Clean up any existing DataTables artifacts
                DataTablesUtils.cleanupForDataTables('data-table');
                
                // Get table stats for debugging
                const stats = DataTablesUtils.getTableStats('data-table');
                console.log('Table stats:', stats);
                
                // Use a simpler configuration to avoid cell indexing issues
                const table = DataTablesUtils.safeInit('#data-table', {
                    responsive: false, // Disable responsive to avoid cell issues
                    pageLength: 25,
                    order: [[0, 'desc']],
                    language: {
                        emptyTable: "No vendors found",
                        zeroRecords: "No vendors match your search criteria"
                    },
                    columnDefs: [
                        {
                            targets: -1, // Actions column
                            orderable: false,
                            searchable: false
                        }
                    ],
                    // Disable problematic features that might cause cell indexing issues
                    deferRender: true,
                    processing: false,
                    // Simple initialization without complex callbacks
                    initComplete: function() {
                        console.log('DataTable initialized successfully');
                        if (typeof relocateTableControls === 'function') {
                            relocateTableControls('data-table');
                        }
                    }
                });

                if (table) {
                    console.log('DataTable initialized successfully using DataTablesUtils');
                } else {
                    console.warn('DataTable initialization failed, retrying in 100ms');
                    setTimeout(initializeDataTables, 100);
                }
            } else if (typeof DataTablesUtils !== 'undefined') {
                // DataTablesUtils exists but dependencies not ready, wait for them
                console.log('DataTablesUtils available but dependencies not ready, waiting...');
                DataTablesUtils.waitForReady(function() {
                    console.log('Dependencies ready, retrying initialization...');
                    setTimeout(initializeDataTables, 50);
                });
            } else {
                console.log('DataTablesUtils not available, using fallback initialization...');
                
                // Clean up table manually for fallback initialization
                if (tableElement) {
                    // Remove data-label attributes
                    const dataLabelCells = tableElement.querySelectorAll('td[data-label], th[data-label]');
                    dataLabelCells.forEach(cell => cell.removeAttribute('data-label'));
                    
                    // Clean up any existing DataTables properties
                    const allCells = tableElement.querySelectorAll('td, th');
                    allCells.forEach(cell => {
                        if (cell._DT_CellIndex !== undefined) delete cell._DT_CellIndex;
                        if (cell._DT_RowIndex !== undefined) delete cell._DT_RowIndex;
                    });
                    
                    // Ensure all cells have content
                    allCells.forEach(cell => {
                        if (!cell.textContent.trim() && !cell.innerHTML.trim()) {
                            cell.innerHTML = '&nbsp;';
                        }
                    });
                }

                // Use a simpler configuration for fallback
                const table = jQuery('#data-table').DataTable({
                    responsive: false, // Disable responsive to avoid cell issues
                    pageLength: 25,
                    order: [[0, 'desc']],
                    language: {
                        emptyTable: "No vendors found",
                        zeroRecords: "No vendors match your search criteria"
                    },
                    columnDefs: [
                        {
                            targets: -1, // Actions column
                            orderable: false,
                            searchable: false
                        }
                    ],
                    // Disable problematic features
                    deferRender: true,
                    processing: false,
                    // Simple initialization without complex callbacks
                    initComplete: function() {
                        console.log('DataTable initialized successfully');
                        if (typeof relocateTableControls === 'function') {
                            relocateTableControls('data-table');
                        }
                    }
                });

                console.log('DataTable initialized successfully (fallback method)');
            }

        } catch (error) {
            console.error('Error initializing DataTable:', error);
            // Don't retry on error to avoid infinite loops
        }
    }

    // Enhanced initialization with multiple fallback strategies
    function startInitialization() {
        console.log('Starting DataTables initialization sequence...');
        
        // Strategy 1: Try immediately if everything is ready
        if (document.readyState === 'complete' && typeof jQuery !== 'undefined' && typeof jQuery.fn.DataTable !== 'undefined') {
            console.log('All dependencies ready, initializing immediately...');
            initializeDataTables();
            return;
        }
        
        // Strategy 2: Wait for DOM content loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOM content loaded, waiting for dependencies...');
                setTimeout(initializeDataTables, 100);
            });
        } else {
            // DOM is already loaded, wait for dependencies
            console.log('DOM already loaded, waiting for dependencies...');
            setTimeout(initializeDataTables, 100);
        }
        
        // Strategy 3: Wait for window load
        window.addEventListener('load', function() {
            console.log('Window loaded, attempting initialization...');
            setTimeout(initializeDataTables, 200);
        });
        
        // Strategy 4: Final fallback with longer delay
        setTimeout(function() {
            console.log('Final fallback initialization attempt...');
            initializeDataTables();
        }, 1000);
    }
    
    // Start the initialization sequence
    startInitialization();
</script>
@endsection 
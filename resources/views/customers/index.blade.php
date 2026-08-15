@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Customers</h1>
        <a href="{{ route('customers.create') }}" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i>Add New Customer
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

<!-- Table Controls: DataTables' length + search land here. Hidden when there
     are no customers, because DataTables is not initialised in that case and
     the card would otherwise render empty. -->
@if($customers->isNotEmpty())
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div id="dt-length"></div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div id="dt-filter"></div>
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
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                    <tr>
                        <td>{{ $customer->id }}</td>
                        <td>{{ $customer->name }}</td>
                        <td>{{ $customer->email ?? 'N/A' }}</td>
                        <td>{{ $customer->phone ?? 'N/A' }}</td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-info d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm btn-warning d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="Edit Customer">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1"
                                    data-bs-toggle="tooltip" title="Delete Customer"
                                    onclick="if(confirm('Are you sure you want to delete this customer?')) { 
                                        document.getElementById('delete-customer-{{ $customer->id }}').submit(); 
                                    }">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            <form id="delete-customer-{{ $customer->id }}" 
                                action="{{ route('customers.destroy', $customer) }}" 
                                method="POST" 
                                style="display: none;">
                                @csrf
                                @method('DELETE')
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-people display-1 d-block mb-3"></i>
                                <h5>No Customers Found</h5>
                                <p class="mb-0">No customers have been added to the system yet.</p>
                                <a href="{{ route('customers.create') }}" class="btn btn-primary mt-3">
                                    <i class="bi bi-plus-circle me-1"></i>Add First Customer
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
    // The empty-state row is a single <td colspan="5">, which DataTables reads
    // as a one-cell data row against a five-column header and then rejects with
    // a "Requested unknown parameter" warning. Skip initialisation entirely when
    // there is nothing to enhance.
    const hasCustomers = @json($customers->isNotEmpty());

    // Wait for both DOM and all resources to be loaded
    window.addEventListener('load', function() {
        if (!hasCustomers) {
            return;
        }
        // Additional delay to ensure everything is ready
        setTimeout(function() {
            initializeDataTables();
        }, 100);
    });

    function initializeDataTables() {
        try {
            // Clean up any existing DataTables instances
            DataTablesUtils.cleanupForDataTables();
            
            // Check if jQuery and DataTables are available
            if (typeof jQuery === 'undefined' || !jQuery.fn.DataTable) {
                console.warn('jQuery or DataTables not available');
                return;
            }

            // Check if table exists
            const table = jQuery('#data-table');
            if (table.length === 0) {
                console.warn('Data table not found');
                return;
            }

            // Initialize DataTables with safe initialization
            const result = DataTablesUtils.safeInit('#data-table', {
                pageLength: 25,
                order: [[0, 'desc']],
                language: {
                    emptyTable: "No customers found",
                    zeroRecords: "No customers match your search criteria"
                },
                columnDefs: [
                    {
                        targets: -1, // Actions column
                        orderable: false,
                        searchable: false
                    }
                ],
                initComplete: function() {
                    if (typeof relocateTableControls === 'function') {
                        relocateTableControls('data-table');
                    }
                }
            });

            if (result) {
                console.log('DataTables initialized successfully for customers');
            } else {
                console.warn('DataTables initialization returned null, trying direct initialization...');
                
                // Fallback to direct DataTables initialization
                try {
                    jQuery('#data-table').DataTable({
                        pageLength: 25,
                        order: [[0, 'desc']],
                        language: {
                            emptyTable: "No customers found",
                            zeroRecords: "No customers match your search criteria"
                        },
                        columnDefs: [
                            {
                                targets: -1, // Actions column
                                orderable: false,
                                searchable: false
                            }
                        ],
                        initComplete: function() {
                            if (typeof relocateTableControls === 'function') {
                                relocateTableControls('data-table');
                            }
                        }
                    });
                    console.log('DataTables initialized successfully with direct initialization');
                } catch (directError) {
                    console.error('Direct DataTables initialization error:', directError);
                }
            }
        } catch (error) {
            console.error('Error initializing DataTables:', error);
            // Try to fix common issues and retry
            try {
                DataTablesUtils.fixCommonIssues();
                setTimeout(function() {
                    initializeDataTables();
                }, 500);
            } catch (retryError) {
                console.error('Failed to retry DataTables initialization:', retryError);
            }
        }
    }

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        try {
            DataTablesUtils.cleanupForDataTables();
        } catch (error) {
            console.warn('Error during cleanup:', error);
        }
    });
</script>
@endsection 
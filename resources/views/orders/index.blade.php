@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Orders</h1>
        <a href="{{ route('orders.create') }}" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i>Create New Order
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
     are no orders, because DataTables is not initialised in that case and the
     card would otherwise render empty. -->
@if($orders->isNotEmpty())
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
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Total Amount</th>
                        <th>Order Date</th>
                        <th>Invoice</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                    <tr>
                        <td data-label="ID">{{ $order->id }}</td>
                        <td data-label="Customer">
                            <strong>{{ $order->customer->name ?? 'N/A' }}</strong>
                            @if($order->customer)
                                <br><small class="text-muted">{{ $order->customer->email ?? '' }}</small>
                            @endif
                        </td>
                        <td data-label="Status">
                            @php
                                $statusColors = [
                                    'pending' => 'warning',
                                    'confirmed' => 'info',
                                    'processing' => 'primary',
                                    'completed' => 'success',
                                    'cancelled' => 'danger'
                                ];
                                $statusColor = $statusColors[$order->status] ?? 'secondary';
                            @endphp
                            <span class="badge bg-{{ $statusColor }}">{{ ucfirst($order->status) }}</span>
                        </td>
                        <td data-label="Total Amount">
                            <strong class="text-success">${{ number_format($order->total_amount ?? 0, 2) }}</strong>
                        </td>
                        <td data-label="Order Date">{{ $order->order_date ? $order->order_date->format('M d, Y') : 'N/A' }}</td>
                        <td data-label="Invoice">
                            @if($order->invoice)
                                <a href="{{ route('invoices.show', $order->invoice) }}" class="btn btn-sm btn-outline-info">
                                    <i class="bi bi-receipt me-1"></i>View Invoice
                                </a>
                            @else
                                <span class="text-muted">No Invoice</span>
                            @endif
                        </td>
                        <td data-label="Actions">
                            <div class="d-flex flex-wrap gap-1">
                                <a href="{{ route('orders.show', $order) }}" class="btn btn-sm btn-info d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('orders.edit', $order) }}" class="btn btn-sm btn-warning d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="Edit Order">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1"
                                    data-bs-toggle="tooltip" title="Delete Order"
                                    onclick="if(confirm('Are you sure you want to delete this order?')) { 
                                        document.getElementById('delete-order-{{ $order->id }}').submit(); 
                                    }">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            <form id="delete-order-{{ $order->id }}" 
                                action="{{ route('orders.destroy', $order) }}" 
                                method="POST" 
                                style="display: none;">
                                @csrf
                                @method('DELETE')
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-cart display-1 d-block mb-3"></i>
                                <h5>No Orders Found</h5>
                                <p class="mb-0">No orders have been created yet.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$orders" />
    </div>
</div>
</div>
@endsection

@section('styles')
<style>
    .table > :not(caption) > * > * {
        padding: 1rem 0.75rem;
    }
    .btn-light {
        background-color: #f8f9fa;
        border-color: #f0f0f0;
    }
    .btn-light:hover {
        background-color: #e9ecef;
        border-color: #e9ecef;
    }
    .badge {
        font-weight: 500;
        padding: 0.5em 0.75em;
    }
    .list-unstyled li {
        margin-bottom: 0.25rem;
    }
    .list-unstyled li:last-child {
        margin-bottom: 0;
    }
</style>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="{{ asset('js/table-controls.js') }}"></script>

<script>
    // The empty-state row is a single <td colspan="7">, which DataTables reads
    // as a one-cell data row against a seven-column header and then rejects with
    // a "Requested unknown parameter" warning — shown as a blocking alert().
    // Skip initialisation entirely when there is nothing to enhance.
    const hasOrders = @json($orders->isNotEmpty());

    // Wait for both DOM and all resources to be loaded
    window.addEventListener('load', function() {
        if (!hasOrders) {
            return;
        }
        // Additional delay to ensure everything is ready
        setTimeout(function() {
            initializeDataTables();
        }, 100);
    });

    function initializeDataTables() {
        if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable) {
            jQuery('#data-table').DataTable({
                responsive: true,
                // This list is paginated server-side and already renders the
                // shared pagination component. Leaving DataTables' own paging
                // and count on would report only the rows in the current
                // server page and stack a second, contradictory footer.
                paging: false,
                info: false,
                lengthChange: false,
                order: [[0, 'desc']],
                language: {
                    emptyTable: "No orders found",
                    zeroRecords: "No orders match your search criteria"
                },
                initComplete: function() {
                    if (typeof relocateTableControls === 'function') {
                        relocateTableControls('data-table');
                    }
                }
            });
        }
    }
</script>
@endsection 
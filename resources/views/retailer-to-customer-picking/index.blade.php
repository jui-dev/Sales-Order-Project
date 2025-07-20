@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <x-breadcrumb :items="[
        ['label' => 'Picking & Transfers', 'url' => '#'],
        ['label' => 'Retailer to Customer', 'url' => '#']
    ]" />
    
    <div class="retailer-customer-picking">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-shop me-2"></i>Retailer to Customer Picking</h1>
            <div>
                <a href="{{ route('orders.index') }}" class="btn btn-outline-primary me-2">
                    <i class="bi bi-cart me-1"></i> View Orders
                </a>
                <button class="btn btn-info" onclick="loadStatistics()">
                    <i class="bi bi-graph-up me-1"></i> Statistics
                </button>
            </div>
        </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <!-- Info Banner -->
    <div class="alert alert-info d-flex align-items-center" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        <div>
            <strong>Retailer to Customer Picking:</strong> This section shows all picking records created when customers place orders with retailer fulfillment. Orders are automatically processed when completed.
        </div>
    </div>

    <!-- System Overview Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Pickings</h6>
                            <h3 id="total-pickings">-</h3>
                        </div>
                        <i class="bi bi-list-check" style="font-size: 2rem; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Completed</h6>
                            <h3 id="completed-pickings">-</h3>
                        </div>
                        <i class="bi bi-check-circle" style="font-size: 2rem; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Pending</h6>
                            <h3 id="pending-pickings">-</h3>
                        </div>
                        <i class="bi bi-clock" style="font-size: 2rem; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Items Shipped</h6>
                            <h3 id="total-items">-</h3>
                        </div>
                        <i class="bi bi-box" style="font-size: 2rem; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($pickingLists->count() > 0)
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-list-check me-2"></i>Retailer to Customer Picking Records
                </h5>
            </div>
            <div class="card-body" style="padding: 0 !important; background: white !important;">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Picking #</th>
                                <th>Order #</th>
                                <th>From Location</th>
                                <th>Items</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pickingLists as $pickingList)
                            <tr>
                                <td data-label="Picking #">
                                    <strong>{{ $pickingList->picking_number }}</strong>
                                </td>
                                <td data-label="Order #">
                                    @if($pickingList->order)
                                        <a href="{{ route('orders.show', $pickingList->order) }}" class="text-primary">
                                            #{{ $pickingList->order->id }}
                                        </a>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td data-label="From Location">
                                    @if($pickingList->fromLocation)
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-shop me-2 text-success"></i>
                                            <div>
                                                <strong>{{ $pickingList->fromLocation->name }}</strong>
                                                @if($pickingList->fromLocation->address)
                                                    <br><small class="text-muted">{{ Str::limit($pickingList->fromLocation->address, 40) }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td data-label="Items">
                                    <strong>{{ $pickingList->pickingItems->count() }}</strong> items
                                    <br>
                                    <small class="text-muted">
                                        {{ $pickingList->pickingItems->sum('quantity_picked') }} / {{ $pickingList->pickingItems->sum('quantity_requested') }} picked
                                    </small>
                                </td>
                                <td data-label="Progress">
                                    <div class="progress" style="height: 20px;">
                                        @php
                                            $totalRequested = $pickingList->pickingItems->sum('quantity_requested');
                                            $totalPicked = $pickingList->pickingItems->sum('quantity_picked');
                                            $progressPercentage = $totalRequested > 0 ? ($totalPicked / $totalRequested) * 100 : 0;
                                        @endphp
                                        <div class="progress-bar bg-{{ $progressPercentage == 100 ? 'success' : ($progressPercentage > 0 ? 'warning' : 'secondary') }}" 
                                             role="progressbar" 
                                             style="width: {{ $progressPercentage }}%"
                                             aria-valuenow="{{ $progressPercentage }}" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            {{ number_format($progressPercentage, 0) }}%
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Status">
                                    <span class="badge bg-{{ $pickingList->status === 'completed' ? 'success' : ($pickingList->status === 'processing' ? 'primary' : 'warning') }}">
                                        {{ ucfirst($pickingList->status) }}
                                    </span>
                                    @if($pickingList->picked_by)
                                        <br><small class="text-muted">by {{ $pickingList->picked_by }}</small>
                                    @endif
                                </td>
                                <td data-label="Created At">
                                    {{ $pickingList->created_at->format('M d, Y') }}
                                    <br><small class="text-muted">{{ $pickingList->created_at->format('H:i') }}</small>
                                    @if($pickingList->completed_at)
                                        <br><small class="text-success">Completed: {{ $pickingList->completed_at->format('M d, H:i') }}</small>
                                    @endif
                                </td>
                                <td data-label="Actions">
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('retailer-to-customer-picking.show', $pickingList) }}" 
                                           class="btn btn-sm btn-info" 
                                           title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if($pickingList->status !== 'completed')
                                        <form action="{{ route('retailer-to-customer-picking.complete', $pickingList) }}" 
                                              method="POST" 
                                              class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" 
                                                    class="btn btn-sm btn-success"
                                                    title="Complete Picking"
                                                    onclick="return confirm('Are you sure you want to mark this picking list as completed?')">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center">No picking lists found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <!-- Enhanced Empty State -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-shop" style="font-size: 4rem; color: #6c757d;"></i>
                        <h4 class="mt-3 text-muted">No Retailer to Customer Pickings Yet</h4>
                        <p class="text-muted mb-4">
                            Picking records will appear here when customers place orders with retailer fulfillment.<br>
                            When an order is completed, the system automatically creates picking records.
                        </p>
                        
                        @if($retailers->count() > 0 && $customers->count() > 0)
                            <div class="alert alert-light border">
                                <h6 class="text-success">
                                    <i class="bi bi-check-circle me-1"></i>System Ready
                                </h6>
                                <p class="mb-2">You have <strong>{{ $retailers->count() }}</strong> retailer(s) and <strong>{{ $customers->count() }}</strong> customer(s) configured.</p>
                                <p class="mb-0">Create an order with retailer fulfillment to see picking records here.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Retailers List -->
                @if($retailers->count() > 0)
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-shop me-1"></i>Available Retailers
                            </h6>
                        </div>
                        <div class="card-body">
                            @foreach($retailers->take(3) as $retailer)
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-shop me-2 text-primary"></i>
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <strong>{{ $retailer->name }}</strong>
                                            <span class="badge bg-primary-subtle text-primary-emphasis ms-2">
                                                <i class="bi bi-box me-1"></i>Active
                                            </span>
                                        </div>
                                        @if($retailer->address)
                                            <br><small class="text-muted">{{ Str::limit($retailer->address, 30) }}</small>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
function loadStatistics() {
    fetch('/api/retailer-to-customer-picking/statistics')
        .then(response => response.json())
        .then(data => {
            // Format numbers with commas for better readability
            document.getElementById('total-pickings').textContent = data.total.toLocaleString();
            document.getElementById('completed-pickings').textContent = data.completed.toLocaleString();
            document.getElementById('pending-pickings').textContent = (data.pending + data.in_progress).toLocaleString();
            document.getElementById('total-items').textContent = data.total_items ? data.total_items.toLocaleString() : '0';
        })
        .catch(error => {
            console.error('Error loading statistics:', error);
            // Set default values in case of error
            document.getElementById('total-pickings').textContent = '0';
            document.getElementById('completed-pickings').textContent = '0';
            document.getElementById('pending-pickings').textContent = '0';
            document.getElementById('total-items').textContent = '0';
        });
}

// Load statistics on page load
document.addEventListener('DOMContentLoaded', loadStatistics);

// Refresh statistics every 30 seconds
setInterval(loadStatistics, 30000);
</script>
@endpush
@endsection 
@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-list-check me-2"></i>All Picking Lists</h1>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">Total Picking Lists</h5>
                <h2>{{ $statistics['total_picking_lists'] }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Completed</h5>
                <h2>{{ $statistics['completed_picking_lists'] }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h5 class="card-title">Pending</h5>
                <h2>{{ $statistics['pending_picking_lists'] }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">In Progress</h5>
                <h2>{{ $statistics['in_progress_picking_lists'] }}</h2>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form action="{{ route('picking.index') }}" method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Picking Type</label>
                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    <option value="vendor_to_warehouse" {{ request('type') === 'vendor_to_warehouse' ? 'selected' : '' }}>
                        Vendor to Warehouse
                    </option>
                    <option value="warehouse_to_retailer" {{ request('type') === 'warehouse_to_retailer' ? 'selected' : '' }}>
                        Warehouse to Retailer
                    </option>
                    <option value="warehouse_to_customer" {{ request('type') === 'warehouse_to_customer' ? 'selected' : '' }}>
                        Warehouse to Customer
                    </option>
                    <option value="retailer_to_customer" {{ request('type') === 'retailer_to_customer' ? 'selected' : '' }}>
                        Retailer to Customer
                    </option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-filter me-1"></i> Apply Filters
                </button>
                <a href="{{ route('picking.index') }}" class="btn btn-secondary">
                    <i class="bi bi-x-circle me-1"></i> Clear
                </a>
            </div>
        </form>
    </div>
</div>

@if($pickingLists->count() > 0)
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Picking Number</th>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Reference</th>
                            <th>Items</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pickingLists as $pickingList)
                        <tr>
                            <td>
                                <strong>{{ $pickingList->picking_number }}</strong>
                                @if($pickingList->reference_type === 'order')
                                    <br><small class="text-muted">Order #{{ $pickingList->reference_id }}</small>
                                @endif
                            </td>
                            <td>
                                @php
                                    $typeClass = match($pickingList->picking_type) {
                                        'vendor_to_warehouse' => 'info',
                                        'warehouse_to_retailer' => 'primary',
                                        'warehouse_to_customer' => 'success',
                                        'retailer_to_customer' => 'warning',
                                        default => 'secondary'
                                    };
                                @endphp
                                <span class="badge bg-{{ $typeClass }}">
                                    {{ ucwords(str_replace('_', ' ', $pickingList->picking_type)) }}
                                </span>
                            </td>
                            <td>
                                @if($pickingList->fromLocation)
                                    <i class="bi bi-geo-alt me-1"></i>{{ $pickingList->fromLocation->name }}
                                    <br><small class="text-muted">{{ ucfirst($pickingList->fromLocation->location_type) }}</small>
                                @elseif($pickingList->supply)
                                    <i class="bi bi-truck me-1"></i>{{ $pickingList->supply->vendor->name }}
                                    <br><small class="text-muted">Vendor</small>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td>
                                @if($pickingList->toLocation)
                                    <i class="bi bi-geo-alt me-1"></i>{{ $pickingList->toLocation->name }}
                                    <br><small class="text-muted">{{ ucfirst($pickingList->toLocation->location_type) }}</small>
                                @else
                                    <span class="text-muted">Direct to Customer</span>
                                @endif
                            </td>
                            <td>
                                @if($pickingList->order)
                                    <a href="{{ route('orders.show', $pickingList->order) }}" class="text-decoration-none">
                                        <i class="bi bi-cart me-1"></i>Order #{{ $pickingList->order->id }}
                                        <br><small class="text-muted">{{ $pickingList->order->customer->name }}</small>
                                    </a>
                                @elseif($pickingList->supply)
                                    <i class="bi bi-box me-1"></i>Supply #{{ $pickingList->supply->id }}
                                    <br><small class="text-muted">{{ $pickingList->supply->vendor->name }}</small>
                                @else
                                    <span class="text-muted">Manual Pick</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $pickingList->pickingItems ? $pickingList->pickingItems->sum('quantity_requested') : 0 }}</strong> requested
                                <br><small class="text-muted">{{ $pickingList->pickingItems ? $pickingList->pickingItems->sum('quantity_picked') : 0 }} picked</small>
                            </td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-{{ $pickingList->progress_percentage == 100 ? 'success' : ($pickingList->progress_percentage > 0 ? 'warning' : 'secondary') }}" 
                                         role="progressbar" 
                                         style="width: {{ $pickingList->progress_percentage }}%"
                                         aria-valuenow="{{ $pickingList->progress_percentage }}" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        {{ number_format($pickingList->progress_percentage, 0) }}%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $pickingList->status === 'pending' ? 'warning' : ($pickingList->status === 'in_progress' ? 'primary' : ($pickingList->status === 'completed' ? 'success' : 'danger')) }}">
                                    {{ ucfirst(str_replace('_', ' ', $pickingList->status)) }}
                                </span>
                                @if($pickingList->picked_by)
                                    <br><small class="text-muted">by {{ $pickingList->picked_by }}</small>
                                @endif
                            </td>
                            <td>
                                {{ $pickingList->picking_date->format('M d, Y') }}
                                <br><small class="text-muted">{{ $pickingList->picking_date->format('H:i') }}</small>
                                @if($pickingList->completed_at)
                                    <br><small class="text-success">Completed: {{ $pickingList->completed_at->format('M d, H:i') }}</small>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('picking.show', $pickingList) }}" class="btn btn-sm btn-info">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center mt-4">
        {{ $pickingLists->links() }}
    </div>
@else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-list-check display-1 text-muted mb-3"></i>
            <h3>No Picking Lists Found</h3>
            <p class="text-muted">No picking lists match your current filters.</p>
        </div>
    </div>
@endif
@endsection 
@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-building-arrow-right me-2"></i>Warehouse to Retailer Picking</h1>
        <div>
            <a href="{{ route('stock-transfers.warehouse-to-retailer.pending') }}" class="btn btn-warning me-2">
                <i class="bi bi-clock me-1"></i> Pending Transfers
            </a>
            <a href="{{ route('stock-transfers.warehouse-to-retailer.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Create Stock Transfer
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    
    

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
        <strong>Warehouse to Retailer Picking:</strong> This section manages stock transfers from warehouses to retail locations. Manual transfers are completed immediately upon creation, while automatic transfers are generated from retailer orders and processed automatically.
    </div>
</div>

<!-- System Overview Cards -->
<div class="summary-panel mb-4">
    <div class="row g-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm summary-card summary-card--blue">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title text-primary">Warehouses</h6>
                        <h3>{{ $totalWarehouses }}</h3>
                    </div>
                    <i class="bi bi-house-fill text-primary summary-card__icon" style="font-size: 2rem;"></i>
                </div>
                <small>
                    <a href="{{ route('stock-locations.index') }}" class="text-primary text-decoration-none">
                        <i class="bi bi-arrow-right me-1"></i>Manage Warehouses
                    </a>
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm summary-card summary-card--green">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title text-success">Retailers</h6>
                        <h3>{{ $totalRetailers }}</h3>
                    </div>
                    <i class="bi bi-shop text-success summary-card__icon" style="font-size: 2rem;"></i>
                </div>
                <small>
                    <a href="{{ route('stock-locations.index') }}" class="text-success text-decoration-none">
                        <i class="bi bi-arrow-right me-1"></i>Manage Retailers
                    </a>
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm summary-card summary-card--cyan">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title text-info-emphasis">Total Transfers</h6>
                        <h3>{{ $totalTransfers }}</h3>
                    </div>
                    <i class="bi bi-arrow-left-right text-info-emphasis summary-card__icon" style="font-size: 2rem;"></i>
                </div>
                <small class="text-muted">
                    <i class="bi bi-cart me-1"></i>{{ $orderGeneratedTransfers }} from orders, {{ $manualTransfers }} manual
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm summary-card summary-card--amber">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title text-warning-emphasis">Products in Stock</h6>
                        <h3>{{ $productsWithStock }}</h3>
                    </div>
                    <i class="bi bi-box text-warning-emphasis summary-card__icon" style="font-size: 2rem;"></i>
                </div>
                <small>
                    <a href="{{ route('products.index') }}" class="text-warning-emphasis text-decoration-none">
                        <i class="bi bi-arrow-right me-1"></i>View Products
                    </a>
                </small>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- Recent Order-Generated Transfers -->
@php
    $recentOrderTransfers = $transfers->where('reference_type', 'retailer_order')->take(3);
@endphp

@if($recentOrderTransfers->count() > 0)
<div class="card mb-4 border-info">
    <div class="card-header bg-info-subtle">
        <h6 class="mb-0 text-info">
            <i class="bi bi-cart-check me-2"></i>Recent Order-Generated Transfers
            <span class="badge bg-info ms-2">{{ $recentOrderTransfers->count() }}</span>
        </h6>
    </div>
    <div class="card-body">
        <div class="row">
            @foreach($recentOrderTransfers as $transfer)
            <div class="col-md-4 mb-3">
                <div class="card h-100 border-info-subtle">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-cart-fill me-2 text-info mt-1"></i>
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-1">{{ $transfer->picking_number }}</h6>
                                <p class="card-text small mb-2">
                                    <strong>Order #{{ $transfer->order->id }}</strong><br>
                                    Customer: {{ $transfer->order->customer->name }}
                                </p>
                                <div class="d-flex justify-content-between small text-muted">
                                    <span>{{ $transfer->fromLocation->name }}</span>
                                    <i class="bi bi-arrow-right"></i>
                                    <span>{{ $transfer->toLocation->name }}</span>
                                </div>
                                <div class="mt-2">
                                    <span class="badge bg-success">{{ $transfer->status }}</span>
                                    <span class="badge bg-light text-dark ms-1">{{ $transfer->pickingItems ? $transfer->pickingItems->sum('quantity_picked') : 0 }} items</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <a href="{{ route('stock-transfers.warehouse-to-retailer.show', $transfer) }}" class="btn btn-sm btn-outline-primary me-1">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                            <a href="{{ route('orders.show', $transfer->order) }}" class="btn btn-sm btn-outline-info">
                                <i class="bi bi-cart me-1"></i>Order
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @if($transfers->where('reference_type', 'retailer_order')->count() > 3)
        <div class="text-center mt-3">
            <small class="text-muted">
                Showing {{ $recentOrderTransfers->count() }} of {{ $transfers->where('reference_type', 'retailer_order')->count() }} order-generated transfers. 
                <strong>See complete list below.</strong>
            </small>
        </div>
        @endif
    </div>
</div>
@endif

@if($transfers->count() > 0)
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-arrow-left-right me-2"></i>Warehouse to Retailer Transfer Records
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Transfer Number</th>
                            <th>Transfer Type / Order</th>
                            <th>From Warehouse</th>
                            <th>To Retailer</th>
                            <th>Items</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Transfer Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transfers as $transfer)
                        <tr>
                            <td>
                                <strong>{{ $transfer->picking_number }}</strong>
                                @if($transfer->reference_type === 'retailer_order' && $transfer->order)
                                    <br><small class="text-info">
                                        <i class="bi bi-cart-check me-1"></i>Order Generated
                                    </small>
                                @else
                                    <br><small class="text-muted">
                                        <i class="bi bi-arrow-right me-1"></i>Manual Transfer
                                    </small>
                                @endif
                            </td>
                            <td>
                                @if($transfer->reference_type === 'retailer_order' && $transfer->order)
                                    <!-- Order-generated transfer -->
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-cart-fill me-2 text-info"></i>
                                        <div>
                                            <strong>Order #{{ $transfer->order->id }}</strong>
                                            <br><small class="text-muted">Customer: {{ $transfer->order->customer->name }}</small>
                                            <br><small class="text-info">
                                                <i class="bi bi-clock me-1"></i>Order Date: {{ $transfer->order->order_date->format('M d, Y') }}
                                            </small>
                                        </div>
                                    </div>
                                @else
                                    <!-- Manual transfer -->
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-hand-index me-2 text-primary"></i>
                                        <div>
                                            <strong>Manual Transfer</strong>
                                            <br><small class="text-muted">Stock Distribution</small>
                                            <br><small class="text-primary">
                                                <i class="bi bi-person me-1"></i>Inventory Management
                                            </small>
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-house-fill me-2 text-primary"></i>
                                    <div>
                                        <strong>{{ $transfer->fromLocation->name }}</strong>
                                        @if($transfer->fromLocation->address)
                                            <br><small class="text-muted">{{ Str::limit($transfer->fromLocation->address, 40) }}</small>
                                        @endif
                                        <br><small class="text-primary">
                                            <i class="bi bi-box me-1"></i>Stock: {{ $transfer->fromLocation->total_stock }} items
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-shop me-2 text-success"></i>
                                    <div>
                                        <strong>{{ $transfer->toLocation->name }}</strong>
                                        @if($transfer->toLocation->address)
                                            <br><small class="text-muted">{{ Str::limit($transfer->toLocation->address, 40) }}</small>
                                        @endif
                                        <br><small class="text-success">
                                            <i class="bi bi-box me-1"></i>Stock: {{ $transfer->toLocation->total_stock }} items
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong>{{ $transfer->pickingItems ? $transfer->pickingItems->sum('quantity_requested') : 0 }}</strong> items requested
                                <br><small class="text-muted">{{ $transfer->pickingItems ? $transfer->pickingItems->sum('quantity_picked') : 0 }} items picked</small>
                                @if($transfer->reference_type === 'retailer_order')
                                    <br><small class="text-info">
                                        <i class="bi bi-box-seam me-1"></i>For customer order
                                    </small>
                                @endif
                            </td>
                            <td>
                                @php
                                                                    $totalRequested = $transfer->pickingItems ? $transfer->pickingItems->sum('quantity_requested') : 0;
                                $totalPicked = $transfer->pickingItems ? $transfer->pickingItems->sum('quantity_picked') : 0;
                                    $progressPercentage = $totalRequested > 0 ? ($totalPicked / $totalRequested) * 100 : 0;
                                @endphp
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-{{ $progressPercentage == 100 ? 'success' : ($progressPercentage > 0 ? 'warning' : 'secondary') }}" 
                                         role="progressbar" 
                                         style="width: {{ $progressPercentage }}%"
                                         aria-valuenow="{{ $progressPercentage }}" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        {{ number_format($progressPercentage, 0) }}%
                                    </div>
                                </div>
                                @if($transfer->reference_type === 'retailer_order')
                                    <small class="text-info">Auto-completed for order</small>
                                @elseif($transfer->reference_type === 'stock_transfer' && $transfer->status === 'completed')
                                    <small class="text-success">Completed immediately</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $transfer->status === 'pending' ? 'warning' : ($transfer->status === 'in_progress' ? 'primary' : ($transfer->status === 'completed' ? 'success' : 'danger')) }}">
                                    {{ ucfirst(str_replace('_', ' ', $transfer->status)) }}
                                </span>
                                @if($transfer->reference_type === 'retailer_order')
                                    <br><small class="text-info">
                                        <i class="bi bi-lightning me-1"></i>Auto-processed
                                    </small>
                                @elseif($transfer->reference_type === 'stock_transfer' && $transfer->status === 'completed')
                                    <br><small class="text-success">
                                        <i class="bi bi-zap me-1"></i>Immediate transfer
                                    </small>
                                @endif
                                @if($transfer->picked_by)
                                    <br><small class="text-muted">by {{ $transfer->picked_by }}</small>
                                @endif
                            </td>
                            <td>
                                <div class="text-dark">{{ $transfer->picking_date->format('M d, Y H:i') }}</div>
                                @if($transfer->completed_at)
                                    <div class="text-success small">
                                        <i class="bi bi-check-circle me-1"></i>Completed: {{ $transfer->completed_at->format('M d, Y H:i') }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <!-- Primary Actions Row -->
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('stock-transfers.warehouse-to-retailer.show', $transfer) }}" 
                                           class="btn btn-sm btn-primary" 
                                           title="View Details">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                        
                                        @if($transfer->reference_type === 'retailer_order' && $transfer->order)
                                            <a href="{{ route('orders.show', $transfer->order) }}" 
                                               class="btn btn-sm btn-info" 
                                               title="View Order">
                                                <i class="bi bi-cart me-1"></i>Order
                                            </a>
                                        @endif
                                    </div>
                                    
                                    <!-- Status-based Actions Row -->
                                    @if($transfer->status === 'pending')
                                        @if($transfer->reference_type === 'retailer_order')
                                            <div class="d-flex gap-1">
                                                <form action="{{ route('stock-transfers.warehouse-to-retailer.quick-complete', $transfer) }}" 
                                                      method="POST" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" 
                                                            class="btn btn-sm btn-success" 
                                                            onclick="return confirm('Complete transfer with all requested quantities?')"
                                                            title="Quick Complete">
                                                        <i class="bi bi-check-double me-1"></i>Complete
                                                    </button>
                                                </form>
                                                
                                                <span class="btn btn-sm btn-secondary disabled" 
                                                      title="Order-generated transfers cannot be cancelled">
                                                    <i class="bi bi-shield-check me-1"></i>Protected
                                                </span>
                                            </div>
                                        @else
                                            <div class="d-flex gap-1">
                                                <form action="{{ route('stock-transfers.warehouse-to-retailer.quick-complete', $transfer) }}" 
                                                      method="POST" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" 
                                                            class="btn btn-sm btn-success" 
                                                            onclick="return confirm('Complete transfer with all requested quantities?')"
                                                            title="Quick Complete">
                                                        <i class="bi bi-check-double me-1"></i>Complete
                                                    </button>
                                                </form>
                                                
                                                <form action="{{ route('stock-transfers.warehouse-to-retailer.cancel', $transfer) }}" 
                                                      method="POST" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" 
                                                            class="btn btn-sm btn-danger" 
                                                            title="Cancel Transfer">
                                                        <i class="bi bi-times me-1"></i>Cancel
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    @elseif($transfer->status === 'completed')
                                        <div class="d-flex gap-1">
                                            @if($transfer->reference_type === 'retailer_order')
                                                <span class="btn btn-sm btn-success disabled" 
                                                      title="Auto-completed from order">
                                                    <i class="bi bi-lightning me-1"></i>Auto-Complete
                                                </span>
                                            @else
                                                <span class="btn btn-sm btn-success disabled" 
                                                      title="Manual transfer completed">
                                                    <i class="bi bi-check-circle me-1"></i>Completed
                                                </span>
                                            @endif
                                        </div>
                                    @elseif($transfer->status === 'cancelled')
                                        <div class="d-flex gap-1">
                                            <span class="btn btn-sm btn-danger disabled" 
                                                  title="Transfer cancelled">
                                                <i class="bi bi-x-circle me-1"></i>Cancelled
                                            </span>
                                        </div>
                                    @else
                                        <div class="d-flex gap-1">
                                            <span class="btn btn-sm btn-secondary disabled" 
                                                  title="Transfer status: {{ ucfirst($transfer->status) }}">
                                                <i class="bi bi-info-circle me-1"></i>{{ ucfirst($transfer->status) }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@else
    <!-- Enhanced Empty State -->
    <div class="card mb-4">
        <div class="card-body text-center py-5">
            <i class="bi bi-building-arrow-right" style="font-size: 4rem; color: #6c757d;"></i>
            <h4 class="mt-3 text-muted">No Warehouse to Retailer Transfers Yet</h4>
            <p class="text-muted mb-4">
                Stock transfers will appear here when you move inventory from warehouses to retail locations.<br>
                Create transfers to distribute products to your retail partners.
            </p>

            @if($totalWarehouses > 0 && $totalRetailers > 0)
                <div class="alert alert-light border">
                    <h6 class="text-success">
                        <i class="bi bi-check-circle me-1"></i>System Ready
                    </h6>
                    <p class="mb-2">You have <strong>{{ $totalWarehouses }}</strong> warehouse(s) and <strong>{{ $totalRetailers }}</strong> retailer(s) configured.</p>
                    @if($productsWithStock > 0)
                        <p class="mb-0"><strong>{{ $productsWithStock }}</strong> products are available in warehouse stock for transfer.</p>
                    @else
                        <p class="mb-0">No products currently in warehouse stock. Add inventory to warehouses first.</p>
                    @endif
                </div>
            @else
                <div class="alert alert-warning border">
                    <h6 class="text-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>Setup Required
                    </h6>
                    @if($totalWarehouses == 0)
                        <p class="mb-2">No warehouses configured. <a href="{{ route('stock-locations.create') }}">Create your first warehouse</a></p>
                    @endif
                    @if($totalRetailers == 0)
                        <p class="mb-0">No retailers configured. <a href="{{ route('stock-locations.create') }}">Create your first retailer</a></p>
                    @endif
                </div>
            @endif

            @if($totalWarehouses == 0 || $totalRetailers == 0)
                <div class="d-flex justify-content-center gap-2 mt-4">
                    <a href="{{ route('stock-locations.create') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-house-plus me-1"></i> Add Location
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Recent Orders -->
    @if($recentOrders->count() > 0)
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-cart me-1"></i>Recent Pending Orders
                </h6>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">These orders might need stock transfers to fulfill:</p>
                @foreach($recentOrders as $order)
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-cart me-2 text-info"></i>
                        <div class="flex-grow-1">
                            <strong>Order #{{ $order->id }}</strong>
                            <br><small class="text-muted">{{ $order->customer->name ?? 'Unknown Customer' }}</small>
                            <br><small class="text-muted">{{ $order->created_at->diffForHumans() }}</small>
                        </div>
                        <a href="{{ route('orders.show', $order) }}" class="btn btn-sm btn-outline-info">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endif
</div>

@endsection

@push('styles')
<style>
/* White container holding the summary cards */
.summary-panel {
    background-color: #ffffff;
    border: 1px solid var(--border-color, #e9ecef);
    border-radius: 8px;
    padding: 1.25rem;
    box-shadow: var(--card-shadow, 0 2px 15px rgba(0, 0, 0, 0.04));
}

/* Summary cards - soft gradient treatment */
.summary-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.summary-card:hover {
    transform: translateY(-2px);
}

.summary-card--blue  { background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%); }
.summary-card--green { background: linear-gradient(135deg, #e8f5e9 0%, #ffffff 100%); }
.summary-card--amber { background: linear-gradient(135deg, #fff8e1 0%, #ffffff 100%); }
.summary-card--cyan  { background: linear-gradient(135deg, #e0f7fa 0%, #ffffff 100%); }

.summary-card__icon {
    opacity: 0.45;
}
</style>
@endpush 
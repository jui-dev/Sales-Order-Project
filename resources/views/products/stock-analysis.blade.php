@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Stock Analysis: {{ $stockData['product']->name }}</h1>
    <div>
        <a href="{{ route('picking.transaction-flow') }}" class="btn btn-info">
            <i class="bi bi-diagram-3 me-1"></i> Transaction Flow
        </a>
        <a href="{{ route('picking.product-transaction-history', $stockData['product']) }}" class="btn btn-primary">
            <i class="bi bi-clock-history me-1"></i> Transaction History
        </a>
        <a href="{{ route('products.index') }}" class="btn btn-secondary">Back to Products</a>
    </div>
</div>

<!-- Stock Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-primary">Current Stock</h5>
                <h2 class="text-{{ $stockData['current_stock'] > 0 ? 'success' : 'danger' }}">{{ $stockData['current_stock'] }}</h2>
                <small class="text-muted">Legacy calculation</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-success">Total Supplied</h5>
                <h2>{{ $stockData['total_supplied'] }}</h2>
                <small class="text-muted">Completed supplies</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-warning">Total Ordered</h5>
                <h2>{{ $stockData['total_ordered'] }}</h2>
                <small class="text-muted">Completed orders</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-info">Projected Stock</h5>
                <h2 class="text-{{ $stockData['projected_stock'] > 0 ? 'success' : 'danger' }}">{{ $stockData['projected_stock'] }}</h2>
                <small class="text-muted">Including pending</small>
            </div>
        </div>
    </div>
</div>

<!-- Stock by Location -->
@if($stockData['stock_by_location']->count() > 0)
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Stock by Location</h5>
    </div>
    <div class="card-body">
        <div class="row">
            @foreach($stockData['stock_by_location'] as $stockBalance)
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="card-title mb-0">{{ $stockBalance->stockLocation->name }}</h6>
                                                            <span class="badge bg-{{ $stockBalance->stockLocation->location_type === 'warehouse' ? 'success' : 'info' }}">
                                    {{ ucfirst($stockBalance->stockLocation->location_type) }}
                            </span>
                        </div>
                        
                        <div class="row text-center mt-3">
                            <div class="col-4">
                                <div class="border-end">
                                    <h5 class="text-primary mb-0">{{ $stockBalance->quantity }}</h5>
                                    <small class="text-muted">Total</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border-end">
                                    <h5 class="text-warning mb-0">{{ $stockBalance->reserved_quantity }}</h5>
                                    <small class="text-muted">Reserved</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <h5 class="text-success mb-0">{{ $stockBalance->available_quantity }}</h5>
                                <small class="text-muted">Available</small>
                            </div>
                        </div>
                        
                        @if($stockBalance->last_movement_date)
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>Last updated: {{ $stockBalance->last_movement_date->format('M d, Y H:i') }}
                                </small>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

<!-- Stock Calculation Explanation -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Stock Calculation</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Current Stock Formula:</h6>
                <p class="mb-1">
                    <strong>{{ $stockData['current_stock'] }}</strong> = 
                    <span class="text-success">{{ $stockData['total_supplied'] }} (supplied)</span> - 
                    <span class="text-warning">{{ $stockData['total_ordered'] }} (ordered)</span>
                </p>
                @if($stockData['current_stock'] <= 0)
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Issue:</strong> More items have been ordered ({{ $stockData['total_ordered'] }}) than supplied ({{ $stockData['total_supplied'] }}).
                        You need to add {{ $stockData['total_ordered'] - $stockData['total_supplied'] + 5 }} more units to supply.
                    </div>
                @endif
            </div>
            <div class="col-md-6">
                <h6>Pending Items:</h6>
                <p class="mb-1">Pending Supplies: <span class="text-info">{{ $stockData['pending_supplies'] }}</span></p>
                <p class="mb-1">Pending Orders: <span class="text-info">{{ $stockData['pending_orders'] }}</span></p>
                @if($stockData['pending_supplies'] > 0)
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Note:</strong> {{ $stockData['pending_supplies'] }} units are pending completion. 
                        <a href="{{ route('supplies.index') }}" class="alert-link">Mark supplies as completed</a> to update stock.
                        <div class="mt-2">
                            <form action="{{ route('products.complete-supplies', $stockData['product']) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="bi bi-check-circle"></i> Complete All Pending Supplies
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Supplies History -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Supply History</h5>
        <a href="{{ route('supplies.create') }}" class="btn btn-success btn-sm">Add New Supply</a>
    </div>
    <div class="card-body">
        @if($stockData['supplies']->count() > 0)
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Supply ID</th>
                            <th>Date</th>
                            <th>Vendor</th>
                            <th>Quantity</th>
                            <th>Unit Cost</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stockData['supplies'] as $supply)
                        <tr>
                            <td>#{{ $supply->supply_id }}</td>
                            <td>{{ \Carbon\Carbon::parse($supply->supply_date)->format('M d, Y') }}</td>
                            <td>{{ $supply->vendor_name }}</td>
                            <td>{{ $supply->quantity }}</td>
                            <td>${{ number_format($supply->unit_cost, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ $supply->status == 'pending' ? 'warning' : ($supply->status == 'processing' ? 'primary' : 'success') }}">
                                    {{ ucfirst($supply->status) }}
                                </span>
                            </td>
                            <td>
                                @if($supply->status != 'completed')
                                    <form action="{{ route('supplies.completed', $supply->supply_id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-success">Mark Completed</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted">No supplies found for this product.</p>
        @endif
    </div>
</div>

<!-- Orders History -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Order History</h5>
        <a href="{{ route('orders.create') }}" class="btn btn-primary btn-sm">Create New Order</a>
    </div>
    <div class="card-body">
        @if($stockData['orders']->count() > 0)
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stockData['orders'] as $order)
                        <tr>
                            <td>#{{ $order->order_id }}</td>
                            <td>{{ \Carbon\Carbon::parse($order->order_date)->format('M d, Y') }}</td>
                            <td>{{ $order->customer_name }}</td>
                            <td>{{ $order->quantity }}</td>
                            <td>${{ number_format($order->unit_price, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ $order->status === 'completed' ? 'success' : ($order->status === 'processing' ? 'primary' : ($order->status === 'cancelled' ? 'danger' : 'warning')) }}">
                                    {{ ucfirst($order->status) }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted">No orders found for this product.</p>
        @endif
    </div>
</div>

@endsection

@section('scripts')
<script>
    // Auto-refresh stock data every 30 seconds
    setTimeout(function() {
        location.reload();
    }, 30000);
</script>
@endsection 
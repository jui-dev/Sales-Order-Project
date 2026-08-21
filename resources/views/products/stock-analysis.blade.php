@extends('layouts.app')
@section('page-header')
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
@endsection

@section('content')


<!-- Stock Summary Cards -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-primary">Current Stock</h5>
                <h2 class="text-{{ $stockData['current_stock'] > 0 ? 'success' : 'danger' }}">{{ $stockData['current_stock'] }}</h2>
                <small class="text-muted">Legacy calculation</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-success">Total Supplied</h5>
                <h2>{{ $stockData['total_supplied'] }}</h2>
                <small class="text-muted">Completed supplies</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-warning">Total Ordered</h5>
                <h2>{{ $stockData['total_ordered'] }}</h2>
                <small class="text-muted">Completed orders</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-info">Projected Stock</h5>
                <h2 class="text-{{ $stockData['projected_stock'] > 0 ? 'success' : 'danger' }}">{{ $stockData['projected_stock'] }}</h2>
                <small class="text-muted">Including pending</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-danger">
                    <i class="bi bi-arrow-return-left me-1"></i>Customer Returns
                </h5>
                <h2 class="text-success">{{ $stockData['total_customer_returns'] }}</h2>
                <small class="text-muted">Items returned by customers</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-info">
                    <i class="bi bi-arrow-return-right me-1"></i>Vendor Returns
                </h5>
                <h2 class="text-warning">{{ $stockData['total_vendor_returns'] }}</h2>
                <small class="text-muted">Items returned to vendors</small>
            </div>
        </div>
    </div>
</div>

<!-- Return Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-warning">
                    <i class="bi bi-arrow-return-left me-1"></i>Retailer Returns
                </h5>
                <h2 class="text-success">{{ $stockData['total_retailer_returns'] }}</h2>
                <small class="text-muted">Items returned by retailers</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-secondary">
                    <i class="bi bi-clock me-1"></i>Pending Returns
                </h5>
                <h2 class="text-warning">{{ $stockData['pending_returns'] }}</h2>
                <small class="text-muted">Awaiting approval</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-success">
                    <i class="bi bi-plus-circle me-1"></i>Net Returns
                </h5>
                <h2 class="text-{{ ($stockData['total_customer_returns'] + $stockData['total_retailer_returns'] - $stockData['total_vendor_returns']) >= 0 ? 'success' : 'danger' }}">
                    {{ $stockData['total_customer_returns'] + $stockData['total_retailer_returns'] - $stockData['total_vendor_returns'] }}
                </h2>
                <small class="text-muted">Net inbound returns</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-primary">
                    <i class="bi bi-calculator me-1"></i>Return Rate
                </h5>
                <h2 class="text-{{ ($stockData['total_ordered'] > 0 ? round((($stockData['total_customer_returns'] / $stockData['total_ordered']) * 100), 1) : 0) <= 5 ? 'success' : 'warning' }}">
                    {{ $stockData['total_ordered'] > 0 ? round((($stockData['total_customer_returns'] / $stockData['total_ordered']) * 100), 1) : 0 }}%
                </h2>
                <small class="text-muted">Customer return rate</small>
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
                            <h6 class="card-title mb-0">{{ $stockBalance->location_name ?? 'Unknown Location' }}</h6>
                            <span class="badge bg-{{ $stockBalance->location_type === 'warehouse' ? 'success' : 'info' }}">
                                {{ ucfirst($stockBalance->location_type ?? 'unknown') }}
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
                                    <i class="bi bi-clock me-1"></i>Last updated: {{ \Carbon\Carbon::parse($stockBalance->last_movement_date)->format('M d, Y H:i') }}
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
                    <span class="text-danger">+ {{ $stockData['total_customer_returns'] + $stockData['total_retailer_returns'] }} (returns in)</span>
                    <span class="text-info">- {{ $stockData['total_vendor_returns'] }} (returns out)</span>
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
                <p class="mb-1">Pending Returns: <span class="text-warning">{{ $stockData['pending_returns'] }}</span></p>
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
                @if($stockData['pending_returns'] > 0)
                    <div class="alert alert-warning">
                        <i class="bi bi-arrow-return-left"></i>
                        <strong>Note:</strong> {{ $stockData['pending_returns'] }} return units are pending approval. 
                        <a href="{{ route('returns.index') }}" class="alert-link">Review pending returns</a> to process them.
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
        @can('purchase-orders.manage')
            {{-- Stock arrives by ordering it; there is no order-less supply form. --}}
            <a href="{{ route('purchase-orders.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-circle me-1"></i>Create Purchase Order
            </a>
        @endcan
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
                                <span class="badge bg-{{ $supply->status == 'pending' ? 'warning' : ($supply->status == 'confirmed' ? 'info' : 'success') }}">
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
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Order History</h5>
        <a href="{{ route('orders.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Create New Order
        </a>
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

<!-- Return History -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-arrow-return-left me-2"></i>Return History
        </h5>
        <a href="{{ route('returns.create') }}" class="btn btn-warning btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Create New Return
        </a>
    </div>
    <div class="card-body">
        @if($stockData['returns']->count() > 0)
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Return ID</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Direction</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stockData['returns'] as $return)
                        <tr>
                            <td>
                                <a href="{{ route('returns.show', $return->id) }}" class="text-decoration-none">
                                    #{{ $return->id }}
                                </a>
                            </td>
                            <td>{{ \Carbon\Carbon::parse($return->transaction_date)->format('M d, Y') }}</td>
                            <td>
                                @if($return->transaction_type === 'customer_return')
                                    <span class="badge bg-danger">
                                        <i class="bi bi-arrow-return-left me-1"></i>Customer Return
                                    </span>
                                @elseif($return->transaction_type === 'vendor_return')
                                    <span class="badge bg-info">
                                        <i class="bi bi-arrow-return-right me-1"></i>Vendor Return
                                    </span>
                                @elseif($return->transaction_type === 'retailer_return')
                                    <span class="badge bg-warning">
                                        <i class="bi bi-arrow-return-left me-1"></i>Retailer Return
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $return->direction === 'inbound' ? 'success' : 'danger' }}">
                                    <i class="bi bi-arrow-{{ $return->direction === 'inbound' ? 'down' : 'up' }} me-1"></i>
                                    {{ ucfirst($return->direction) }}
                                </span>
                            </td>
                            <td>{{ $return->quantity }}</td>
                            <td>
                                <span class="badge bg-{{ $return->status === 'pending' ? 'warning' : ($return->status === 'approved' ? 'info' : ($return->status === 'completed' ? 'success' : ($return->status === 'rejected' ? 'danger' : 'secondary'))) }}">
                                    {{ ucfirst($return->status) }}
                                </span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('returns.show', $return->id) }}" class="btn btn-sm btn-outline-primary" title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if($return->status === 'pending')
                                                                                    <form action="{{ route('returns.approve', $return->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Approve Return">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                            </form>
                                        <form action="{{ route('returns.reject', $return->id) }}" method="POST" class="d-inline" onsubmit="return promptRejectionReason(this)">
                                            @csrf
                                            <input type="hidden" name="rejection_reason" value="">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Reject Return">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if($return->status === 'approved')
                                        <form action="{{ route('returns.complete', $return->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Complete Return" onclick="return confirm('Are you sure you want to complete this return?')">
                                                <i class="bi bi-check2-all"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted">No return transactions found for this product.</p>
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

    // Rejecting a return requires a reason, which is recorded against the return.
    function promptRejectionReason(form) {
        var reason = window.prompt('Why is this return being rejected?');

        if (reason === null || reason.trim() === '') {
            return false;
        }

        form.querySelector('input[name="rejection_reason"]').value = reason.trim();
        return true;
    }
</script>
@endsection 
@extends('layouts.app')

@section('styles')
<style>
    /* Force all text to be dark - override Bootstrap defaults */
    .card, .card-body, .card-header, .table, .table td, .table th, 
    .btn, .badge, .text-white, .bg-primary, .bg-success, .bg-info, .bg-warning {
        color: #212529 !important;
    }
    
    .main-warehouse-highlight {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-left: 3px solid #6c757d;
    }
    .info-card {
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: transform 0.2s ease-in-out;
        background-color: #ffffff !important;
    }
    .info-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    .info-card .card-body {
        background-color: #ffffff !important;
        color: #212529 !important;
    }
    .info-card .card-header {
        background-color: #f8f9fa !important;
        color: #212529 !important;
    }
    .subtle-badge {
        background-color: #e9ecef;
        color: #495057 !important;
        font-size: 0.75rem;
        font-weight: normal;
    }
    .status-completed { background-color: #d4edda; color: #155724 !important; }
    .status-pending { background-color: #fff3cd; color: #856404 !important; }
    .status-cancelled { background-color: #f8d7da; color: #721c24 !important; }
    .status-progress { background-color: #d1ecf1; color: #0c5460 !important; }
    .table-clean {
        border: none;
    }
    .table-clean th {
        border-top: none;
        border-bottom: 1px solid #dee2e6;
        background-color: #f8f9fa;
        font-weight: 500;
        color: #495057 !important;
        font-size: 0.875rem;
    }
    .table-clean td {
        border-top: 1px solid #f1f3f4;
        vertical-align: middle;
        color: #212529 !important;
    }
    .text-subtle { color: #6c757d !important; font-size: 0.875rem; }
    .amount-highlight { color: #28a745 !important; font-weight: 500; }
    .text-primary { color: #0d6efd !important; }
    .text-success { color: #198754 !important; }
    .text-info { color: #0dcaf0 !important; }
    .text-warning { color: #ffc107 !important; }
    .text-dark { color: #212529 !important; }
    .text-muted { color: #6c757d !important; }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Header -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light border-bottom">
                    <div class="row align-items-center">
                        <div class="col">
                            <h4 class="mb-1 text-dark">Picking Details: {{ $pickingList->picking_number }}</h4>
                            <small class="text-muted">Warehouse to Customer Fulfillment</small>
                        </div>
                        <div class="col-auto">
                            <span class="badge status-{{ $pickingList->status }} fs-6">
                                {{ ucfirst($pickingList->status) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Left Column -->
                <div class="col-md-8">
                    <!-- Order Information -->
                    <div class="card info-card mb-4">
                        <div class="card-header bg-light border-bottom">
                            <h5 class="mb-0 text-dark">
                                <i class="bi bi-receipt me-2"></i>Order Information
                            </h5>
                        </div>
                        <div class="card-body">
                            @if($pickingList->order)
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <strong>Order Number:</strong>
                                            <span class="text-primary">#{{ $pickingList->order->id }}</span>
                                        </p>
                                        <p class="mb-2">
                                            <strong>Order Date:</strong>
                                            <span>{{ $pickingList->order->order_date->format('M d, Y') }}</span>
                                        </p>
                                        <p class="mb-2">
                                            <strong>Order Status:</strong>
                                            <span class="badge bg-{{ $pickingList->order->status === 'completed' ? 'success' : 'warning' }}">
                                                {{ ucfirst($pickingList->order->status) }}
                                            </span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <strong>Total Items:</strong>
                                            <span>{{ $pickingList->order->orderItems->sum('quantity') }}</span>
                                        </p>
                                        <p class="mb-2">
                                            <strong>Total Amount:</strong>
                                            <span class="text-success">${{ number_format($pickingList->order->total_amount, 2) }}</span>
                                        </p>
                                    </div>
                                </div>
                            @else
                                <p class="text-muted">No order information available</p>
                            @endif
                        </div>
                    </div>

                    <!-- Picking Items -->
                    <div class="card info-card mb-4">
                        <div class="card-header bg-light border-bottom">
                            <h5 class="mb-0 text-dark">
                                <i class="bi bi-list-check me-2"></i>Picking Items
                            </h5>
                            <small class="text-muted">{{ $pickingList->pickingItems->count() }} items total</small>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-clean mb-0">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Requested</th>
                                            <th>Picked</th>
                                            <th>Status</th>
                                            <th>Stock Available</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($pickingList->pickingItems as $item)
                                        <tr>
                                            <td>
                                                <div class="text-dark">{{ $item->product->name }}</div>
                                                <div class="text-subtle">SKU: {{ $item->product->sku }}</div>
                                            </td>
                                            <td>
                                                <span class="text-dark">{{ $item->quantity }}</span>
                                            </td>
                                            <td>
                                                <span class="text-dark">{{ $item->picked_quantity ?? 0 }}</span>
                                                @if($item->picked_quantity !== $item->quantity)
                                                    <span class="badge subtle-badge ms-1">Partial</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($item->picked_quantity >= $item->quantity)
                                                    <span class="badge status-completed">Complete</span>
                                                @elseif($item->picked_quantity > 0)
                                                    <span class="badge status-progress">Partial</span>
                                                @else
                                                    <span class="badge status-pending">Pending</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $stockBalance = $item->product->getStockAtLocation($pickingList->from_location_id);
                                                    $availableStock = $stockBalance ? $stockBalance->available_quantity : 0;
                                                @endphp
                                                <span class="{{ $availableStock >= $item->quantity ? 'text-success' : 'text-danger' }}">
                                                    {{ $availableStock }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($availableStock < $item->quantity)
                                                    <span class="text-danger">Insufficient stock</span>
                                                @else
                                                    <span class="text-success">Stock available</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center">No items found</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-md-4">
                    <!-- Warehouse Information -->
                    <div class="card info-card mb-4">
                        <div class="card-header bg-light border-bottom">
                            <h5 class="mb-0 text-dark">
                                <i class="bi bi-building me-2"></i>Warehouse
                            </h5>
                        </div>
                        <div class="card-body">
                            @if($pickingList->fromLocation)
                                <h6 class="text-dark">{{ $pickingList->fromLocation->name }}</h6>
                                @if($pickingList->fromLocation->address)
                                    <p class="text-subtle mb-2">
                                        <i class="bi bi-geo-alt me-1"></i>{{ $pickingList->fromLocation->address }}
                                    </p>
                                @endif
                                <div class="mt-3">
                                    <h6 class="text-dark mb-2">Stock Summary</h6>
                                    @php
                                        $totalStock = $pickingList->fromLocation->stockBalances->sum('current_stock');
                                        $reservedStock = $pickingList->fromLocation->stockBalances->sum('reserved_quantity');
                                        $availableStock = $pickingList->fromLocation->stockBalances->sum('available_quantity');
                                    @endphp
                                    <p class="mb-1">
                                        <span class="text-muted">Total Stock:</span>
                                        <span class="text-dark">{{ $totalStock }}</span>
                                    </p>
                                    <p class="mb-1">
                                        <span class="text-muted">Reserved:</span>
                                        <span class="text-warning">{{ $reservedStock }}</span>
                                    </p>
                                    <p class="mb-0">
                                        <span class="text-muted">Available:</span>
                                        <span class="text-success">{{ $availableStock }}</span>
                                    </p>
                                </div>
                            @else
                                <p class="text-muted">No warehouse information available</p>
                            @endif
                        </div>
                    </div>

                    <!-- Customer Information -->
                    <div class="card info-card mb-4">
                        <div class="card-header bg-light border-bottom">
                            <h5 class="mb-0 text-dark">
                                <i class="bi bi-person me-2"></i>Customer
                            </h5>
                        </div>
                        <div class="card-body">
                            @if($pickingList->order && $pickingList->order->customer)
                                <h6 class="text-dark">{{ $pickingList->order->customer->name }}</h6>
                                @if($pickingList->order->customer->address)
                                    <p class="text-subtle mb-2">
                                        <i class="bi bi-geo-alt me-1"></i>{{ $pickingList->order->customer->address }}
                                    </p>
                                @endif
                                @if($pickingList->order->customer->phone)
                                    <p class="text-subtle mb-2">
                                        <i class="bi bi-telephone me-1"></i>{{ $pickingList->order->customer->phone }}
                                    </p>
                                @endif
                                @if($pickingList->order->customer->email)
                                    <p class="text-subtle mb-0">
                                        <i class="bi bi-envelope me-1"></i>{{ $pickingList->order->customer->email }}
                                    </p>
                                @endif
                            @else
                                <p class="text-muted">No customer information available</p>
                            @endif
                        </div>
                    </div>

                    <!-- Picking Information -->
                    <div class="card info-card">
                        <div class="card-header bg-light border-bottom">
                            <h5 class="mb-0 text-dark">
                                <i class="bi bi-info-circle me-2"></i>Picking Info
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-sm-6">
                                    <strong class="text-dark">Created:</strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-subtle">{{ $pickingList->created_at->format('M d, Y H:i') }}</span>
                                </div>
                            </div>
                            @if($pickingList->completed_at)
                            <div class="row mb-2">
                                <div class="col-sm-6">
                                    <strong class="text-dark">Completed:</strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-subtle">{{ $pickingList->completed_at->format('M d, Y H:i') }}</span>
                                </div>
                            </div>
                            @endif
                            @if($pickingList->picked_by)
                            <div class="row mb-2">
                                <div class="col-sm-6">
                                    <strong class="text-dark">Picked By:</strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-subtle">{{ $pickingList->picked_by }}</span>
                                </div>
                            </div>
                            @endif
                            @if($pickingList->notes)
                            <div class="row">
                                <div class="col-12">
                                    <strong class="text-dark">Notes:</strong>
                                    <p class="text-subtle mt-1 mb-0">{{ $pickingList->notes }}</p>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('warehouse-to-customer-picking.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Back to List
                        </a>
                        @if($pickingList->order)
                            <a href="{{ route('orders.show', $pickingList->order->id) }}" class="btn btn-outline-primary">
                                <i class="bi bi-receipt me-1"></i>View Order
                            </a>
                        @endif
                        @if($pickingList->status !== 'completed')
                            <form action="{{ route('warehouse-to-customer-picking.update-status', $pickingList) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="completed">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle me-1"></i>Mark as Completed
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
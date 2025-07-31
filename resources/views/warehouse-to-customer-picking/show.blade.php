@extends('layouts.app')

@section('styles')
<style>
    /* Enhanced styling for warehouse to customer picking show page */
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
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        background-color: #ffffff !important;
        border-radius: 12px;
        overflow: hidden;
    }
    
    .info-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    
    .info-card .card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
        color: #212529 !important;
        border-bottom: 1px solid #dee2e6;
        padding: 1rem 1.5rem;
    }
    
    .info-card .card-body {
        background-color: #ffffff !important;
        color: #212529 !important;
        padding: 1.5rem;
    }
    
    .status-badge {
        font-size: 0.875rem;
        font-weight: 500;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-pending { 
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        color: #856404 !important;
        border: 1px solid #ffeaa7;
    }
    
    .status-completed { 
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724 !important;
        border: 1px solid #c3e6cb;
    }
    
    .status-cancelled { 
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24 !important;
        border: 1px solid #f5c6cb;
    }
    
    .status-in_progress { 
        background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
        color: #0c5460 !important;
        border: 1px solid #bee5eb;
    }
    
    .table-modern {
        border: none;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .table-modern th {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: none;
        font-weight: 600;
        color: #495057 !important;
        font-size: 0.875rem;
        padding: 1rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .table-modern td {
        border: none;
        border-bottom: 1px solid #f1f3f4;
        vertical-align: middle;
        color: #212529 !important;
        padding: 1rem;
    }
    
    .table-modern tbody tr:hover {
        background-color: #f8f9fa;
        transition: background-color 0.2s ease;
    }
    
    .text-subtle { 
        color: #6c757d !important; 
        font-size: 0.875rem; 
    }
    
    .text-primary { color: #0d6efd !important; }
    .text-success { color: #198754 !important; }
    .text-info { color: #0dcaf0 !important; }
    .text-warning { color: #ffc107 !important; }
    .text-dark { color: #212529 !important; }
    .text-muted { color: #6c757d !important; }
    
    .progress-modern {
        height: 8px;
        border-radius: 10px;
        background-color: #e9ecef;
        overflow: hidden;
    }
    
    .progress-modern .progress-bar {
        border-radius: 10px;
        transition: width 0.6s ease;
    }
    
    .action-buttons {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    
    .btn-modern {
        border-radius: 8px;
        font-weight: 500;
        padding: 0.75rem 1.5rem;
        transition: all 0.3s ease;
        border: none;
    }
    
    .btn-modern:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .stats-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border: 1px solid #dee2e6;
        border-radius: 12px;
        padding: 1.5rem;
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    
    .stats-number {
        font-size: 2rem;
        font-weight: 700;
        color: #0d6efd;
        margin-bottom: 0.5rem;
    }
    
    .stats-label {
        color: #6c757d;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
</style>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="container-fluid">
    <!-- Enhanced Header -->
    <div class="card info-card mb-4">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-house-arrow-right" style="font-size: 2.5rem; color: #0d6efd;"></i>
                        </div>
                        <div>
                            <h3 class="mb-1 text-dark">Picking Details</h3>
                            <h5 class="text-primary mb-0">{{ $pickingList->picking_number }}</h5>
                            <small class="text-muted">Warehouse to Customer Fulfillment</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex flex-column align-items-end">
                        <span class="status-badge status-{{ $pickingList->status ?? 'pending' }} mb-2">
                            <i class="bi bi-{{ $pickingList->status === 'completed' ? 'check-circle' : ($pickingList->status === 'cancelled' ? 'x-circle' : 'clock') }} me-1"></i>
                            {{ ucfirst($pickingList->status ?? 'Pending') }}
                        </span>
                        <small class="text-muted">
                            Created: {{ $pickingList->created_at->format('M d, Y H:i') }}
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Order Information Card -->
            <div class="card info-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0 text-dark">
                        <i class="bi bi-receipt me-2"></i>Order Information
                    </h5>
                </div>
                <div class="card-body">
                    @if($pickingList->order)
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="text-muted small text-uppercase">Order Number</label>
                                    <div class="text-dark fw-bold">#{{ $pickingList->order->id }}</div>
                                </div>
                                <div class="mb-3">
                                    <label class="text-muted small text-uppercase">Order Date</label>
                                    <div class="text-dark">{{ $pickingList->order->order_date->format('M d, Y H:i') }}</div>
                                </div>
                                <div class="mb-3">
                                    <label class="text-muted small text-uppercase">Order Status</label>
                                    <div>
                                        <span class="status-badge status-{{ $pickingList->order->status }}">
                                            {{ ucfirst($pickingList->order->status) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="text-muted small text-uppercase">Total Items</label>
                                    <div class="text-dark fw-bold">{{ $pickingList->order && $pickingList->order->orderItems ? $pickingList->order->orderItems->sum('quantity') : 0 }}</div>
                                </div>
                                <div class="mb-3">
                                    <label class="text-muted small text-uppercase">Total Amount</label>
                                    <div class="text-success fw-bold fs-5">${{ number_format($pickingList->order->total_amount, 2) }}</div>
                                </div>
                                <div class="mb-3">
                                    <label class="text-muted small text-uppercase">Progress</label>
                                    <div class="progress progress-modern mb-2">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $pickingList->progress_percentage ?? 0 }}%;"></div>
                                    </div>
                                    <small class="text-muted">{{ number_format($pickingList->progress_percentage ?? 0, 0) }}% Complete</small>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                            <h6 class="text-muted mt-3">No Order Information</h6>
                            <p class="text-muted">This picking list is not associated with an order.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Picking Items Card -->
            <div class="card info-card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-dark">
                            <i class="bi bi-list-check me-2"></i>Picking Items
                        </h5>
                        <span class="badge bg-primary">{{ $pickingList->pickingItems->count() }} items</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-modern mb-0">
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
                                        <div class="text-dark fw-bold">{{ $item->product->name }}</div>
                                        <div class="text-subtle">SKU: {{ $item->product->sku }}</div>
                                    </td>
                                    <td>
                                        <span class="text-dark fw-bold">{{ $item->quantity }}</span>
                                    </td>
                                    <td>
                                        <span class="text-dark fw-bold">{{ $item->picked_quantity ?? 0 }}</span>
                                        @if($item->picked_quantity !== $item->quantity)
                                            <span class="badge bg-warning ms-1">Partial</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item->picked_quantity >= $item->quantity)
                                            <span class="status-badge status-completed">Complete</span>
                                        @elseif($item->picked_quantity > 0)
                                            <span class="status-badge status-in_progress">Partial</span>
                                        @else
                                            <span class="status-badge status-pending">Pending</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $stockBalance = $item->product->getStockAtLocation($pickingList->from_location_id);
                                            $availableStock = $stockBalance ? $stockBalance->available_quantity : 0;
                                        @endphp
                                        <span class="fw-bold {{ $availableStock >= $item->quantity ? 'text-success' : 'text-danger' }}">
                                            {{ $availableStock }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($availableStock < $item->quantity)
                                            <span class="text-danger small">
                                                <i class="bi bi-exclamation-triangle me-1"></i>Insufficient
                                            </span>
                                        @else
                                            <span class="text-success small">
                                                <i class="bi bi-check-circle me-1"></i>Available
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="bi bi-box text-muted" style="font-size: 2rem;"></i>
                                        <div class="text-muted mt-2">No items found</div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Warehouse Information Card -->
            <div class="card info-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0 text-dark">
                        <i class="bi bi-building me-2"></i>Warehouse
                    </h5>
                </div>
                <div class="card-body">
                    @if($pickingList->fromLocation)
                        <div class="text-center mb-3">
                            <i class="bi bi-building" style="font-size: 3rem; color: #0d6efd;"></i>
                        </div>
                        <h6 class="text-dark text-center mb-3">{{ $pickingList->fromLocation->name }}</h6>
                        @if($pickingList->fromLocation->address)
                            <p class="text-subtle text-center mb-3">
                                <i class="bi bi-geo-alt me-1"></i>{{ $pickingList->fromLocation->address }}
                            </p>
                        @endif
                        
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="stats-card">
                                    <div class="stats-number">
                                        @php
                                            $totalStock = $pickingList->fromLocation && $pickingList->fromLocation->productStocks ? $pickingList->fromLocation->productStocks->sum('quantity') : 0;
                                        @endphp
                                        {{ $totalStock }}
                                    </div>
                                    <div class="stats-label">Total Stock</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stats-card">
                                    <div class="stats-number text-warning">
                                        @php
                                            $reservedStock = $pickingList->fromLocation && $pickingList->fromLocation->productStocks ? $pickingList->fromLocation->productStocks->sum('reserved_quantity') : 0;
                                        @endphp
                                        {{ $reservedStock }}
                                    </div>
                                    <div class="stats-label">Reserved</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stats-card">
                                    <div class="stats-number text-success">
                                        @php
                                            $availableStock = $pickingList->fromLocation && $pickingList->fromLocation->productStocks ? $pickingList->fromLocation->productStocks->sum('available_quantity') : 0;
                                        @endphp
                                        {{ $availableStock }}
                                    </div>
                                    <div class="stats-label">Available</div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                            <h6 class="text-muted mt-3">No Warehouse Information</h6>
                            <p class="text-muted">Warehouse details not available.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Customer Information Card -->
            <div class="card info-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0 text-dark">
                        <i class="bi bi-person me-2"></i>Customer
                    </h5>
                </div>
                <div class="card-body">
                    @if($pickingList->order && $pickingList->order->customer)
                        <div class="text-center mb-3">
                            <i class="bi bi-person-circle" style="font-size: 3rem; color: #0d6efd;"></i>
                        </div>
                        <h6 class="text-dark text-center mb-3">{{ $pickingList->order->customer->name }}</h6>
                        
                        <div class="mb-3">
                            @if($pickingList->order->customer->address)
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-geo-alt text-muted me-2"></i>
                                    <span class="text-subtle">{{ $pickingList->order->customer->address }}</span>
                                </div>
                            @endif
                            @if($pickingList->order->customer->phone)
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-telephone text-muted me-2"></i>
                                    <span class="text-subtle">{{ $pickingList->order->customer->phone }}</span>
                                </div>
                            @endif
                            @if($pickingList->order->customer->email)
                                <div class="d-flex align-items-center mb-0">
                                    <i class="bi bi-envelope text-muted me-2"></i>
                                    <span class="text-subtle">{{ $pickingList->order->customer->email }}</span>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                            <h6 class="text-muted mt-3">No Customer Information</h6>
                            <p class="text-muted">Customer details not available.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Picking Information Card -->
            <div class="card info-card">
                <div class="card-header">
                    <h5 class="mb-0 text-dark">
                        <i class="bi bi-info-circle me-2"></i>Picking Info
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small text-uppercase">Created</label>
                        <div class="text-dark">{{ $pickingList->created_at->format('M d, Y H:i') }}</div>
                    </div>
                    @if($pickingList->completed_at)
                    <div class="mb-3">
                        <label class="text-muted small text-uppercase">Completed</label>
                        <div class="text-success">{{ $pickingList->completed_at->format('M d, Y H:i') }}</div>
                    </div>
                    @endif
                    @if($pickingList->picked_by)
                    <div class="mb-3">
                        <label class="text-muted small text-uppercase">Picked By</label>
                        <div class="text-dark">{{ $pickingList->picked_by }}</div>
                    </div>
                    @endif
                    @if($pickingList->notes)
                    <div class="mb-0">
                        <label class="text-muted small text-uppercase">Notes</label>
                        <div class="text-subtle mt-1">{{ $pickingList->notes }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Action Buttons -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card info-card">
                <div class="card-body">
                    <div class="action-buttons justify-content-between">
                        <div class="d-flex gap-2">
                            <a href="{{ route('warehouse-to-customer-picking.index') }}" class="btn btn-modern btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to List
                            </a>
                            @if($pickingList->order)
                                <a href="{{ route('orders.show', $pickingList->order->id) }}" class="btn btn-modern btn-outline-primary">
                                    <i class="bi bi-receipt me-2"></i>View Order
                                </a>
                            @endif
                        </div>
                        
                        <div class="d-flex gap-2">
                            @if($pickingList->status !== 'completed')
                                <form action="{{ route('warehouse-to-customer-picking.update-status', $pickingList) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn btn-modern btn-success">
                                        <i class="bi bi-check-circle me-2"></i>Mark as Completed
                                    </button>
                                </form>
                            @else
                                <span class="btn btn-modern btn-success disabled">
                                    <i class="bi bi-check-circle me-2"></i>Already Completed
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
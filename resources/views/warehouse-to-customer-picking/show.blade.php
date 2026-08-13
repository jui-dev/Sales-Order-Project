@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Picking Details</h1>
    <div>
        <a href="{{ route('warehouse-to-customer-picking.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
        @if($pickingList->order)
            <a href="{{ route('orders.show', $pickingList->order->id) }}" class="btn btn-primary">
                <i class="bi bi-receipt me-1"></i> View Order
            </a>
        @endif
    </div>
</div>
@endsection

@section('content')


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

<!-- Picking Status Section -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-box-seam me-3" style="font-size: 2.5rem; color: #0d6efd;"></i>
                        <div class="text-start">
                            <h4 class="mb-1">{{ $pickingList->picking_number ?? 'PL-000004' }}</h4>
                            <p class="text-muted mb-0">Warehouse to Customer Fulfillment</p>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-{{ $pickingList->status === 'completed' ? 'success' : ($pickingList->status === 'cancelled' ? 'danger' : 'warning') }} fs-6 px-3 py-2">
                            <i class="bi bi-{{ $pickingList->status === 'completed' ? 'check-circle' : ($pickingList->status === 'cancelled' ? 'x-circle' : 'clock') }} me-1"></i>
                            {{ strtoupper($pickingList->status) }}
                        </span>
                        <div class="small text-muted mt-1">
                            Created: {{ $pickingList->created_at->format('M d, Y H:i') }}
                        </div>
                    </div>
                </div>
                
                @if($pickingList->status !== 'completed')
                    <form action="{{ route('warehouse-to-customer-picking.update-status', $pickingList) }}" method="POST" class="d-inline">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-check-circle me-2"></i> Mark as Completed
                        </button>
                    </form>
                @else
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <strong>Picking Completed</strong> - This picking list has been processed and completed.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Order Information -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-receipt me-2"></i> Order Information
                </h5>
            </div>
            <div class="card-body">
                @if($pickingList->order)
                    <table class="table table-borderless">
                        <tr>
                            <th>Order Number:</th>
                            <td>#{{ $pickingList->order->id }}</td>
                        </tr>
                        <tr>
                            <th>Order Date:</th>
                            <td>{{ $pickingList->order->order_date->format('M d, Y H:i') }}</td>
                        </tr>
                        <tr>
                            <th>Order Status:</th>
                            <td>
                                <span class="badge bg-{{ $pickingList->order->status === 'confirmed' ? 'success' : ($pickingList->order->status === 'processing' ? 'primary' : 'warning') }}">
                                    {{ ucfirst($pickingList->order->status) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Total Items:</th>
                            <td>{{ $pickingList->order->orderItems ? $pickingList->order->orderItems->sum('quantity') : 0 }}</td>
                        </tr>
                        <tr>
                            <th>Total Amount:</th>
                            <td><strong>${{ number_format($pickingList->order->total_amount, 2) }}</strong></td>
                        </tr>
                    </table>
                    
                    <div class="mt-3">
                        <h6>Progress</h6>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $pickingList->progress_percentage ?? 0 }}%;" aria-valuenow="{{ $pickingList->progress_percentage ?? 0 }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small class="text-muted">{{ number_format($pickingList->progress_percentage ?? 0, 0) }}% Complete</small>
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
    </div>
    
    <!-- Warehouse Information -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-building me-2"></i> Warehouse
                </h5>
            </div>
            <div class="card-body">
                @if($pickingList->fromLocation)
                    <div class="text-center mb-3">
                        <i class="bi bi-building" style="font-size: 3rem; color: #0d6efd;"></i>
                    </div>
                    <h6 class="text-center mb-3">{{ $pickingList->fromLocation->name }}</h6>
                    @if($pickingList->fromLocation->address)
                        <p class="text-center text-muted mb-3">
                            <i class="bi bi-geo-alt me-1"></i>{{ $pickingList->fromLocation->address }}
                        </p>
                    @endif
                    
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="h4 text-primary mb-1">
                                    @php
                                        $totalStock = 0;
                                        if($pickingList->fromLocation && $pickingList->fromLocation->productStocks) {
                                            $totalStock = $pickingList->fromLocation->productStocks->sum('quantity');
                                        }
                                    @endphp
                                    {{ $totalStock }}
                                </div>
                                <small class="text-muted">TOTAL<br/>STOCK</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="h4 text-warning mb-1">
                                    @php
                                        $reservedStock = 0;
                                        if($pickingList->fromLocation && $pickingList->fromLocation->productStocks) {
                                            $reservedStock = $pickingList->fromLocation->productStocks->sum('reserved_quantity');
                                        }
                                    @endphp
                                    {{ $reservedStock }}
                                </div>
                                <small class="text-muted">RESER<br/>VED</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="h4 text-success mb-1">
                                    @php
                                        $availableStock = 0;
                                        if($pickingList->fromLocation && $pickingList->fromLocation->productStocks) {
                                            $availableStock = $pickingList->fromLocation->productStocks->sum('available_quantity');
                                        }
                                    @endphp
                                    {{ $availableStock }}
                                </div>
                                <small class="text-muted">AVAILA<br/>BLE</small>
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
    </div>
    
    <!-- Customer Information -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-person me-2"></i> Customer
                </h5>
            </div>
            <div class="card-body">
                @if($pickingList->order && $pickingList->order->customer)
                    <div class="text-center mb-3">
                        <i class="bi bi-person-circle" style="font-size: 3rem; color: #0d6efd;"></i>
                    </div>
                    <h6 class="text-center mb-3">{{ $pickingList->order->customer->name }}</h6>
                    
                    @if($pickingList->order->customer->address)
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-geo-alt text-muted me-2"></i>
                            <span class="text-muted">{{ $pickingList->order->customer->address }}</span>
                        </div>
                    @endif
                    @if($pickingList->order->customer->phone)
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-telephone text-muted me-2"></i>
                            <span class="text-muted">{{ $pickingList->order->customer->phone }}</span>
                        </div>
                    @endif
                    @if($pickingList->order->customer->email)
                        <div class="d-flex align-items-center mb-0">
                            <i class="bi bi-envelope text-muted me-2"></i>
                            <span class="text-muted">{{ $pickingList->order->customer->email }}</span>
                        </div>
                    @endif
                @else
                    <div class="text-center py-4">
                        <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                        <h6 class="text-muted mt-3">No Customer Information</h6>
                        <p class="text-muted">Customer details not available.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Picking Items -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-list-check me-2"></i> Picking Items
                    </h5>
                    <span class="badge bg-primary">{{ $pickingList->pickingItems->count() }} items</span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>PRODUCT</th>
                                <th>REQUESTED</th>
                                <th>PICKED</th>
                                <th>STATUS</th>
                                <th>STOCK AVAILABLE</th>
                                <th>NOTES</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pickingList->pickingItems as $item)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $item->product->name }}</div>
                                    <div class="text-muted small">SKU: {{ $item->product->sku }}</div>
                                </td>
                                <td>
                                    <span class="fw-bold">{{ $item->quantity_requested }}</span>
                                </td>
                                <td>
                                    <span class="fw-bold">{{ $item->quantity_picked ?? 0 }}</span>
                                    @if(($item->quantity_picked ?? 0) !== $item->quantity_requested)
                                        <span class="badge bg-warning ms-1">Partial</span>
                                    @endif
                                </td>
                                <td>
                                    @if(($item->quantity_picked ?? 0) >= $item->quantity_requested)
                                        <span class="badge bg-success">Complete</span>
                                    @elseif(($item->quantity_picked ?? 0) > 0)
                                        <span class="badge bg-info">Partial</span>
                                    @else
                                        <span class="badge bg-warning">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        // Get available stock (total - reserved) at this location for this product
                                        $availableStock = 0;
                                        if($pickingList->fromLocation && $pickingList->fromLocation->productStocks) {
                                            $stockRecord = $pickingList->fromLocation->productStocks->where('product_id', $item->product_id)->first();
                                            if ($stockRecord) {
                                                $availableStock = max(0, $stockRecord->quantity - ($stockRecord->reserved_quantity ?? 0));
                                            }
                                        }
                                    @endphp
                                    <span class="fw-bold {{ $availableStock >= $item->quantity_requested ? 'text-success' : 'text-danger' }}">
                                        {{ $availableStock }}
                                    </span>
                                    <small class="text-muted d-block">
                                        Available (Total - Reserved)
                                    </small>
                                </td>
                                <td>
                                    @if($availableStock < $item->quantity_requested)
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
</div>
@endsection 
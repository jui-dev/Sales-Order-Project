@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Order #{{ $order->id }}</h1>
    <div>
        <a href="{{ route('orders.edit', $order) }}" class="btn btn-primary">Edit Order</a>
        @if($order->status === 'completed' && $order->invoice)
            <a href="{{ route('invoices.show', $order->invoice) }}" target="_blank" class="btn btn-success">View Invoice</a>
        @endif
        <a href="{{ route('orders.index') }}" class="btn btn-secondary">Back to Orders</a>
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

<!-- Order Actions -->
@if($order->status == 'pending')
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title">Order Confirmation</h5>
                <p class="card-text text-muted">This order is ready to be confirmed. Once confirmed, stock will be reserved and a picking list will be generated.</p>
                <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#completeOrderModal">
                    <i class="bi bi-check-circle me-2"></i> Mark as Confirmed
                </button>
            </div>
        </div>
    </div>
</div>
@elseif($order->status == 'confirmed' && !$order->invoice)
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="alert alert-success mb-3">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>Order Confirmed</strong> - Stock has been reserved and picking list generated.
                </div>
                <form action="{{ route('orders.update-status', $order) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="completed">
                    <button type="submit" class="btn btn-warning btn-lg">
                        <i class="bi bi-box-seam me-2"></i> Mark as Completed
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@elseif($order->status == 'completed')
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="alert alert-info mb-0">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>Order Completed</strong> - This order has been fulfilled and completed.
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Order Details</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th>Customer:</th>
                        <td>{{ optional($order->customer)->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Order Date:</th>
                        <td>{{ optional($order->order_date)->format('M d, Y') }}</td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge bg-{{ $order->status === 'confirmed' ? 'success' : ($order->status === 'processing' ? 'primary' : ($order->status === 'cancelled' ? 'danger' : 'warning')) }}">
                                {{ ucfirst($order->status) }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Total Amount:</th>
                        <td>${{ number_format($order->total_amount ?? 0, 2) }}</td>
                    </tr>
                    @if($order->fulfillmentLocation)
                    <tr>
                        <th>Fulfilled From:</th>
                        <td>
                            <span class="badge bg-{{ optional($order->fulfillmentLocation)->location_type === 'warehouse' ? 'success' : 'info' }}">
                                {{ optional($order->fulfillmentLocation)->name }}
                            </span>
                            <br><small class="text-muted">{{ ucfirst(optional($order->fulfillmentLocation)->location_type ?? 'unknown') }}</small>
                        </td>
                    </tr>
                    @endif
                </table>
                
                @if($order->notes)
                <div class="mt-3">
                    <h6>Notes:</h6>
                    <p>{{ $order->notes }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Order Items</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Unit Price</th>
                                <th>Quantity</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->orderItems ?? [] as $item)
                            <tr>
                                <td>{{ optional($item->product)->name ?? 'Unknown Product' }}</td>
                                <td>${{ number_format($item->unit_price ?? 0, 2) }}</td>
                                <td>{{ $item->quantity ?? 0 }}</td>
                                <td class="text-end">${{ number_format($item->subtotal ?? 0, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center">No items found</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total:</th>
                                <th class="text-end">${{ number_format($order->total_amount ?? 0, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Complete Order Modal -->
<div class="modal fade" id="completeOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Order #{{ $order->id }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('orders.update-status', $order) }}" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="confirmed">
                <div class="modal-body">
                    @if($order->fulfillmentLocation)
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            This order will be fulfilled from:
                            <strong>{{ $order->fulfillmentLocation->name }}</strong>
                                                            ({{ ucfirst($order->fulfillmentLocation->location_type) }})
                        </div>
                        <p>Are you sure you want to confirm this order? This will:</p>
                        <ul>
                            <li>Generate a picking list for order fulfillment</li>
                            <li>Update stock levels at the fulfillment location</li>
                            <li>Mark the order status as confirmed</li>
                        </ul>
                    @else
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Please assign a fulfillment location to this order first.
                        </div>
                        <div class="mb-3">
                            <label for="fulfillment_location_id" class="form-label">Fulfillment Location</label>
                            <select class="form-select" id="fulfillment_location_id" name="fulfillment_location_id" required>
                                <option value="">Select fulfillment location</option>
                                @php
                                    $warehouses = \App\Models\Warehouse::where('status', 'active')->get();
                                    $retailers = \App\Models\Retailer::where('status', 'active')->get();
                                @endphp
                                <optgroup label="Warehouses">
                                    @foreach($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}">
                                            {{ $warehouse->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Retailers">
                                    @foreach($retailers as $retailer)
                                        <option value="{{ $retailer->id }}">
                                            {{ $retailer->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            </select>
                            <div class="form-text">Select the location from which this order will be fulfilled. Stock will be reduced from this location.</div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    @if($order->fulfillmentLocation)
                        <button type="submit" class="btn btn-success">Confirm Order</button>
                    @else
                        <button type="button" class="btn btn-warning" disabled>Assign Location First</button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>

@if($order->status === 'confirmed')
<!-- Return Button -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <h6>Need to process a return?</h6>
                <a href="{{ route('returns.create', ['order_id' => $order->id]) }}" class="btn btn-warning">
                    <i class="bi bi-arrow-return-left me-1"></i> Create Return
                </a>
            </div>
        </div>
    </div>
</div>
@endif
@endsection 
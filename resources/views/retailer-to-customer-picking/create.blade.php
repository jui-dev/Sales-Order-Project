@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">
                            <i class="bi bi-box-seam me-2"></i>Create New Picking List
                        </h3>
                        <div class="card-tools">
                            <a href="{{ route('retailer-to-customer-picking.index') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Back to List
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('retailer-to-customer-picking.store') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-cart me-2"></i>Select Order
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="order_id">Order</label>
                                            <select name="order_id" id="order_id" class="form-control" required>
                                                <option value="">Select an order...</option>
                                                @foreach($orders as $order)
                                                    <option value="{{ $order->id }}" 
                                                            data-customer="{{ $order->customer->name }}"
                                                            data-items="{{ $order->orderItems->count() }}">
                                                        Order #{{ $order->id }} - {{ $order->customer->name }}
                                                        ({{ $order->orderItems->count() }} items)
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div id="order-details" class="mt-3" style="display: none;">
                                            <h6>Order Details</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Product</th>
                                                            <th>Quantity</th>
                                                            <th>Unit Price</th>
                                                            <th>Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="order-items">
                                                        <!-- Order items will be loaded here via JavaScript -->
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-shop me-2"></i>Select Retailer Location
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="from_location_id">Retailer Location</label>
                                            <select name="from_location_id" id="from_location_id" class="form-control" required>
                                                <option value="">Select a retailer location...</option>
                                                @foreach($retailers as $retailer)
                                                    <option value="{{ $retailer->id }}">
                                                        {{ $retailer->name }}
                                                        @if($retailer->address)
                                                            - {{ Str::limit($retailer->address, 40) }}
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div id="stock-availability" class="mt-3" style="display: none;">
                                            <h6>Stock Availability</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Product</th>
                                                            <th>Available Stock</th>
                                                            <th>Required</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="stock-items">
                                                        <!-- Stock availability will be loaded here via JavaScript -->
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-1"></i>Create Picking List
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const orderSelect = document.getElementById('order_id');
    const orderDetails = document.getElementById('order-details');
    const orderItems = document.getElementById('order-items');
    const retailerSelect = document.getElementById('from_location_id');
    const stockAvailability = document.getElementById('stock-availability');
    const stockItems = document.getElementById('stock-items');

    // Handle order selection
    orderSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (this.value) {
            // Show order details
            orderDetails.style.display = 'block';
            
            // Load order items via AJAX
            fetch(`/api/orders/${this.value}/items`)
                .then(response => response.json())
                .then(data => {
                    orderItems.innerHTML = data.items.map(item => `
                        <tr>
                            <td>${item.product.name}</td>
                            <td>${item.quantity}</td>
                            <td>$${item.unit_price}</td>
                            <td>$${item.total}</td>
                        </tr>
                    `).join('');
                });
        } else {
            orderDetails.style.display = 'none';
            orderItems.innerHTML = '';
        }
    });

    // Handle retailer selection
    retailerSelect.addEventListener('change', function() {
        const orderId = orderSelect.value;
        if (this.value && orderId) {
            // Show stock availability
            stockAvailability.style.display = 'block';
            
            // Load stock availability via AJAX
            fetch(`/api/stock-availability?order_id=${orderId}&location_id=${this.value}`)
                .then(response => response.json())
                .then(data => {
                    stockItems.innerHTML = data.items.map(item => `
                        <tr>
                            <td>${item.product.name}</td>
                            <td>${item.available_stock}</td>
                            <td>${item.required_quantity}</td>
                            <td>
                                <span class="badge bg-${item.has_sufficient_stock ? 'success' : 'danger'}">
                                    ${item.has_sufficient_stock ? 'Available' : 'Insufficient'}
                                </span>
                            </td>
                        </tr>
                    `).join('');
                });
        } else {
            stockAvailability.style.display = 'none';
            stockItems.innerHTML = '';
        }
    });
});
</script>
@endsection 
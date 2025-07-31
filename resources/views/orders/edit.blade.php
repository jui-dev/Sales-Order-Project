@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Edit Order #{{ $order->id }}</h1>
    <div>
        <a href="{{ route('orders.show', $order) }}" class="btn btn-info">View Order</a>
        <a href="{{ route('orders.index') }}" class="btn btn-secondary">Back to Orders</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-light mb-4">
            <i class="bi bi-exclamation-circle text-danger"></i> Fields marked with <span class="text-danger">*</span> are required and must be filled before submitting the form.
        </div>
        
        <form action="{{ route('orders.update', $order) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="mb-3">
                <label for="customer_id" class="form-label">Customer <span class="text-danger">*</span></label>
                <select name="customer_id" id="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                    <option value="">Select Customer</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" {{ (old('customer_id', $order->customer_id) == $customer->id) ? 'selected' : '' }}>
                            {{ $customer->name }}
                        </option>
                    @endforeach
                </select>
                @error('customer_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="mb-3">
                <label for="order_date" class="form-label">Order Date <span class="text-danger">*</span></label>
                <input type="date" name="order_date" id="order_date" class="form-control @error('order_date') is-invalid @enderror" 
                    value="{{ old('order_date', $order->order_date->format('Y-m-d')) }}" required>
                @error('order_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="mb-3">
                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                    <option value="pending" {{ (old('status', $order->status) == 'pending') ? 'selected' : '' }}>Pending</option>
                    <option value="processing" {{ (old('status', $order->status) == 'processing') ? 'selected' : '' }}>Processing</option>
                    <option value="confirmed" {{ (old('status', $order->status) == 'confirmed') ? 'selected' : '' }}>Confirmed</option>
                    <option value="completed" {{ (old('status', $order->status) == 'completed') ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ (old('status', $order->status) == 'cancelled') ? 'selected' : '' }}>Cancelled</option>
                </select>
                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="mb-3">
                <label for="notes" class="form-label">Notes</label>
                <textarea name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $order->notes) }}</textarea>
                @error('notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Note: To modify order items, please delete this order and create a new one.
            </div>
            
            <div class="mt-4">
                <h4>Order Items</h4>
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
                        @foreach($order->orderItems as $item)
                        <tr>
                            <td>{{ $item->product->name }}</td>
                            <td>${{ number_format($item->unit_price, 2) }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td class="text-end">${{ number_format($item->subtotal, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-end">Total:</th>
                            <th class="text-end">${{ number_format($order->total_amount, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="d-flex justify-content-end mt-4">
                <button type="submit" class="btn btn-primary">Update Order</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Required field indicator styling - consistent with create page */
    .text-danger {
        color: var(--danger) !important;
    }
    
    .form-label .text-danger {
        font-weight: 600;
        margin-left: 2px;
    }
    
    /* Enhanced focus states for required fields */
    .form-control:required:focus,
    .form-select:required:focus {
        border-color: var(--danger);
        box-shadow: 0 0 0 0.2rem rgba(231, 111, 81, 0.25);
    }
    
    @media (max-width: 768px) {
        /* Ensure required indicators are visible on mobile */
        .form-label .text-danger {
            font-size: 0.9em;
        }
    }
</style>
@endsection 
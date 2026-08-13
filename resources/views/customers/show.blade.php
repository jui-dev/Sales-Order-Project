@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Customer Details</h1>
    <div>
        <a href="{{ route('customers.edit', $customer) }}" class="btn btn-primary">Edit</a>
        <a href="{{ route('customers.index') }}" class="btn btn-secondary">Back to Customers</a>
    </div>
</div>
@endsection

@section('content')


<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h3>{{ $customer->name }}</h3>
                <p class="text-muted">Customer ID: {{ $customer->id }}</p>
                
                <div class="mb-3">
                    <h5>Email</h5>
                    <p>{{ $customer->email ?? 'N/A' }}</p>
                </div>
                
                <div class="mb-3">
                    <h5>Phone</h5>
                    <p>{{ $customer->phone ?? 'N/A' }}</p>
                </div>
                
                <div class="mb-3">
                    <h5>Address</h5>
                    <p>{{ $customer->address ?? 'N/A' }}</p>
                </div>
                
                <div class="mb-3">
                    <h5>Created At</h5>
                    <p>{{ $customer->created_at ? $customer->created_at->format('F d, Y') : 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<h2>Orders</h2>
<div class="card">
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Total Amount</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customer->orders as $order)
                <tr>
                    <td>{{ $order->id }}</td>
                    <td>{{ $order->order_date ? $order->order_date->format('M d, Y') : 'N/A' }}</td>
                    <td>
                        <span class="badge bg-{{ $order->status === 'completed' ? 'success' : ($order->status === 'processing' ? 'primary' : ($order->status === 'cancelled' ? 'danger' : 'warning')) }}">
                            {{ ucfirst($order->status) }}
                        </span>
                    </td>
                    <td>${{ number_format($order->total_amount, 2) }}</td>
                    <td>
                        <a href="{{ route('orders.show', $order) }}" class="btn btn-sm btn-info">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center">No orders found for this customer</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection 
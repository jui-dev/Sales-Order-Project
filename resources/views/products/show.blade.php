@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>{{ $product->name }}</h1>
    <div>
        <a href="{{ route('products.edit', $product) }}" class="btn btn-primary">Edit Product</a>
        <a href="{{ route('products.index') }}" class="btn btn-secondary">Back to Products</a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Product Details</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th>Name:</th>
                        <td>{{ $product->name }}</td>
                    </tr>
                    <tr>
                        <th>Selling Price:</th>
                        <td>${{ number_format($product->selling_price, 2) }}</td>
                    </tr>
                    <tr>
                        <th>Warehouse Stock:</th>
                        <td>
                            <span class="badge bg-{{ ($product->warehouse_stock ?? 0) > 0 ? 'success' : 'danger' }}">
                                {{ $product->warehouse_stock ?? 0 }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Retailer Stock:</th>
                        <td>
                            <span class="badge bg-{{ ($product->retailer_stock ?? 0) > 0 ? 'success' : 'danger' }}">
                                {{ $product->retailer_stock ?? 0 }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Total Stock:</th>
                        <td>
                            <span class="badge bg-{{ (($product->warehouse_stock ?? 0) + ($product->retailer_stock ?? 0)) > 0 ? 'success' : 'danger' }}">
                                {{ ($product->warehouse_stock ?? 0) + ($product->retailer_stock ?? 0) }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                                            <th>Purchase Price:</th>
                    <td>
                        @if($product->purchase_price)
                            ${{ number_format($product->purchase_price, 2) }}
                            @else
                                <span class="text-muted">Not set</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>GP%:</th>
                        <td>
                            @if($product->gp !== null)
                                {{ number_format($product->gp, 1) }}%
                            @else
                                <span class="text-muted">Not set</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Supply History</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Product purchase prices and GP% are updated when supplies are marked as completed.
                </div>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Vendor</th>
                            <th>Quantity</th>
                            <th>Unit Cost</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($product->supplyItems as $item)
                        <tr>
                            <td>{{ $item->supply->supply_date->format('M d, Y') }}</td>
                            <td>{{ $item->supply->vendor->name }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>${{ number_format($item->unit_cost, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ $item->supply->status == 'completed' ? 'success' : ($item->supply->status == 'processing' ? 'primary' : 'warning') }}">
                                    {{ ucfirst($item->supply->status) }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center">No supply history found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Order History</h5>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Unit Cost</th>
                    <th>Profit</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($product->orderItems as $item)
                <tr>
                    <td>{{ $item->order->order_date->format('M d, Y') }}</td>
                    <td>{{ $item->order->customer->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>${{ number_format($item->unit_price, 2) }}</td>
                    <td>${{ number_format($item->unit_cost, 2) }}</td>
                    <td>${{ number_format($item->profit, 2) }}</td>
                    <td>
                        <span class="badge bg-{{ $item->order->status == 'completed' ? 'success' : ($item->order->status == 'processing' ? 'primary' : 'warning') }}">
                            {{ ucfirst($item->order->status) }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center">No order history found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
@endsection 
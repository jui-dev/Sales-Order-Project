@extends('layouts.app')

@php
    $badge = [
        'draft' => 'secondary',
        'approved' => 'info',
        'sent' => 'primary',
        'partially_received' => 'warning',
        'received' => 'success',
        'cancelled' => 'danger',
    ];
@endphp

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Purchase Orders</h1>
        <p class="text-muted mb-0">What you have asked vendors to send.</p>
    </div>
    <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Purchase Order
    </a>
</div>
@endsection

@section('content')
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="vendor_id" class="form-label">Vendor</label>
                <select name="vendor_id" id="vendor_id" class="form-select">
                    <option value="">All vendors</option>
                    @foreach ($vendors as $vendor)
                        <option value="{{ $vendor->id }}" @selected(($filters['vendor_id'] ?? '') == $vendor->id)>{{ $vendor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">Filter</button>
                <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Vendor</th>
                    <th>Deliver To</th>
                    <th>Status</th>
                    <th>Items</th>
                    <th class="text-end">Total</th>
                    <th>Expected</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td><strong>{{ $order->code }}</strong></td>
                        <td>{{ $order->vendor->name ?? '—' }}</td>
                        <td>{{ $order->warehouse->name ?? '—' }}</td>
                        <td>
                            <span class="badge bg-{{ $badge[$order->status] ?? 'secondary' }}">
                                {{ $statuses[$order->status] ?? ucfirst($order->status) }}
                            </span>
                        </td>
                        <td>{{ $order->items->count() }}</td>
                        <td class="text-end">${{ number_format($order->total_cost, 2) }}</td>
                        <td>{{ $order->expected_date?->format('M d, Y') ?? '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-sm btn-info">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            No purchase orders yet.
                            <a href="{{ route('purchase-orders.create') }}">Create the first one</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

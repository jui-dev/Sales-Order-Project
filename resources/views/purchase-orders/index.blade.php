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
<div class="container-fluid">

    <!-- Unified Search Component -->
    <x-unified-search
        :searchPlaceholder="'Search orders by code, vendor, warehouse, or product...'"
        :filterOptions="$filterOptions"
        :sortOptions="$sortOptions"
        :defaultSort="'id'"
        :defaultDirection="'desc'"
    />

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Vendor</th>
                            <th>Deliver To</th>
                            <th>Status</th>
                            <th>Items</th>
                            <th class="text-end">Total</th>
                            <th>Expected</th>
                            <th>Actions</th>
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
                                <td>
                                    {{-- One way in. Everything an order can have done to it -
                                         approve, send, cancel, record a supply - lives on its
                                         own page, so this column only opens it. --}}
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="{{ route('purchase-orders.show', $order) }}"
                                           class="btn btn-sm btn-info d-inline-flex align-items-center gap-1"
                                           data-bs-toggle="tooltip" title="View Order">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox display-1 d-block mb-3"></i>
                                        <h5>No Purchase Orders Found</h5>
                                        <p class="mb-0">No purchase orders match your current search criteria.</p>
                                        @if(request()->hasAny(['search', 'status', 'vendor_id', 'warehouse_id', 'date_from', 'date_to']))
                                            <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-primary mt-2">
                                                <i class="bi bi-arrow-clockwise me-1"></i>Clear Filters
                                            </a>
                                        @else
                                            <a href="{{ route('purchase-orders.create') }}" class="btn btn-outline-primary mt-2">
                                                <i class="bi bi-plus-lg me-1"></i>Create the first one
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$orders" />
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
@endsection

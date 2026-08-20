@extends('layouts.app')

@php
    $statuses = \App\Models\PurchaseOrder::statuses();
    $badge = [
        'sent' => 'primary',
        'partially_received' => 'warning',
    ];
@endphp

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Requested Purchase Orders</h1>
        <p class="text-muted mb-0">Orders that have gone to a vendor and are still waiting on goods.</p>
    </div>
    <a href="{{ route('supplies.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Supplies
    </a>
</div>
@endsection

@section('content')
<div class="container-fluid">

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Vendor</th>
                        <th>Deliver To</th>
                        <th>Expected</th>
                        <th>Still Outstanding</th>
                        <th>Total Cost</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                    <tr>
                        <td>
                            <a href="{{ route('purchase-orders.show', $order->id) }}">{{ $order->code }}</a>
                        </td>
                        <td>{{ $order->vendor->name ?? 'Unknown vendor' }}</td>
                        <td>{{ $order->warehouse->name ?? 'Unknown warehouse' }}</td>
                        <td>{{ $order->expected_date?->format('M d, Y') ?? '—' }}</td>
                        <td>
                            {{ $order->items->sum(fn ($item) => $item->outstanding()) }}
                            of {{ $order->items->sum('quantity_ordered') }} items
                        </td>
                        <td>${{ number_format($order->total_cost, 2) }}</td>
                        <td>
                            <span class="badge bg-{{ $badge[$order->status] ?? 'secondary' }}">
                                {{ $statuses[$order->status] ?? $order->status }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('supplies.create', ['purchase_order' => $order->id]) }}"
                               class="btn btn-sm btn-primary">
                                <i class="bi bi-box-seam me-1"></i>Record Supply
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-inbox display-1 d-block mb-3"></i>
                                <h5>Nothing Waiting On A Delivery</h5>
                                <p class="mb-0">
                                    Every purchase order has either been received in full or has not been sent yet.
                                </p>
                                @can('purchase-orders.view')
                                    <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-primary mt-3">
                                        View all purchase orders
                                    </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
@endsection

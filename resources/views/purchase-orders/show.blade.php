@extends('layouts.app')

@php
    $statuses = \App\Models\PurchaseOrder::statuses();
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
        <h1 class="h2 mb-1">{{ $order->code }}</h1>
        <p class="text-muted mb-0">
            {{ $order->vendor->name ?? 'Unknown vendor' }} &rarr; {{ $order->warehouse->name ?? 'Unknown warehouse' }}
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if ($order->isEditable())
            <a href="{{ route('purchase-orders.edit', $order) }}" class="btn btn-outline-primary">Edit</a>
        @endif

        @switch($order->status)
            @case(\App\Models\PurchaseOrder::STATUS_DRAFT)
                <form action="{{ route('purchase-orders.approve', $order) }}" method="POST">
                    @csrf @method('PATCH')
                    <button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Approve</button>
                </form>
                @break

            @case(\App\Models\PurchaseOrder::STATUS_APPROVED)
                <form action="{{ route('purchase-orders.send', $order) }}" method="POST">
                    @csrf @method('PATCH')
                    <button class="btn btn-primary"><i class="bi bi-send me-1"></i>Send to Vendor</button>
                </form>
                @break

            @case(\App\Models\PurchaseOrder::STATUS_SENT)
            @case(\App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED)
                <a href="{{ route('purchase-orders.receive', $order) }}" class="btn btn-primary">
                    <i class="bi bi-box-seam me-1"></i>Record Supply
                </a>
                @break
        @endswitch

        @if ($order->isCancellable())
            <form action="{{ route('purchase-orders.cancel', $order) }}" method="POST"
                  onsubmit="return confirm('Cancel this purchase order?')">
                @csrf @method('PATCH')
                <button class="btn btn-outline-danger">Cancel Order</button>
            </form>
        @endif
    </div>
</div>
@endsection

@section('content')

<x-workflow-rail :stages="\App\Support\SuppliesWorkflow::forPurchaseOrder($order)" />

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-muted small">Status</div>
                <span class="badge bg-{{ $badge[$order->status] ?? 'secondary' }} fs-6">
                    {{ $statuses[$order->status] ?? ucfirst($order->status) }}
                </span>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Total Ordered</div>
                <div class="fs-5">${{ number_format($order->total_cost, 2) }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Expected</div>
                <div class="fs-5">{{ $order->expected_date?->format('M d, Y') ?? '—' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Raised By</div>
                <div>{{ $order->creator->name ?? '—' }}</div>
                @if ($order->approved_at)
                    <div class="small text-muted">
                        Approved by {{ $order->approver->name ?? '—' }} on {{ $order->approved_at->format('M d, Y') }}
                    </div>
                @endif
            </div>
            @if ($order->notes)
                <div class="col-12">
                    <div class="text-muted small">Notes</div>
                    <div>{{ $order->notes }}</div>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- What happens next --}}
<div class="card mb-4">
    <div class="card-body">
        @switch($order->status)
            @case(\App\Models\PurchaseOrder::STATUS_DRAFT)
                <span class="text-muted">Approve this order to sign it off internally.</span>
                @break

            @case(\App\Models\PurchaseOrder::STATUS_APPROVED)
                <span class="text-muted">Send the order to the vendor.</span>
                @break

            @case(\App\Models\PurchaseOrder::STATUS_SENT)
            @case(\App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED)
                <span class="text-muted">When the goods arrive, record what actually turned up.</span>
                @break

            @case(\App\Models\PurchaseOrder::STATUS_RECEIVED)
                <span class="text-success mb-0">
                    <i class="bi bi-check-circle me-1"></i>Everything ordered has been received.
                </span>
                @break

            @case(\App\Models\PurchaseOrder::STATUS_CANCELLED)
                <span class="text-muted mb-0">This order was cancelled.</span>
                @break
        @endswitch
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h2 class="h5 mb-0">Items</h2></div>
    <div class="card-body">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="text-end">Ordered</th>
                    <th class="text-end">Received</th>
                    <th class="text-end">Outstanding</th>
                    <th class="text-end">Unit Cost</th>
                    <th class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>{{ $item->product->name ?? 'Unknown product' }}</td>
                        <td class="text-end">{{ $item->quantity_ordered }}</td>
                        <td class="text-end">{{ $item->quantity_received }}</td>
                        <td class="text-end">
                            @if ($item->outstanding() > 0)
                                <span class="badge bg-warning">{{ $item->outstanding() }}</span>
                            @else
                                <span class="badge bg-success">0</span>
                            @endif
                        </td>
                        <td class="text-end">${{ number_format($item->unit_cost, 2) }}</td>
                        <td class="text-end">${{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5" class="text-end">Total</th>
                    <th class="text-end">${{ number_format($order->total_cost, 2) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="h5 mb-0">Deliveries</h2></div>
    <div class="card-body">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>Supply</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th class="text-end">Value</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($order->supplies as $supply)
                    <tr>
                        <td><strong>{{ $supply->code }}</strong></td>
                        <td>{{ $supply->supply_date?->format('M d, Y') ?? '—' }}</td>
                        <td>{{ ucfirst($supply->status) }}</td>
                        <td class="text-end">${{ number_format($supply->total_cost, 2) }}</td>
                        <td class="text-end">
                            <a href="{{ route('supplies.show', $supply) }}" class="btn btn-sm btn-info">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">
                            Nothing has been delivered against this order yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

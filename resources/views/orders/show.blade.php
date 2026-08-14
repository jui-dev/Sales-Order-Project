@extends('layouts.app')

@php
    $items    = $order->items ?? collect();
    $invoice  = $order->invoice;
    $picking  = $order->pickingList;
    $customer = $order->customer;

    // fulfillmentLocation is an accessor that runs its own query on every read,
    // so it is resolved once here. It answers withDefault(), so a row that has
    // gone missing comes back as an empty model rather than null - exists is
    // what says whether there is a real location behind it.
    $fulfilment     = $order->fulfillmentLocation;
    $hasFulfilment  = $fulfilment && $fulfilment->exists;
    $fulfilmentName = $hasFulfilment ? $fulfilment->name : 'the fulfilment location';

    $status      = $order->status;
    $isPending   = $status === 'pending';
    $isCancelled = $status === 'cancelled';
    $isCompleted = $status === 'completed';
    // 'processing' is accepted by the status endpoint and sits alongside confirmed.
    $inProgress  = in_array($status, ['confirmed', 'processing'], true);

    $statusColour = match (true) {
        $isCompleted => 'success',
        $isCancelled => 'danger',
        $inProgress  => 'info',
        default      => 'warning',
    };

    // The picking list, not the order status, says whether the goods have left.
    $isPicked = $picking && in_array($picking->status, ['completed', 'closed', 'verified'], true);

    $totalUnits = $items->sum('quantity');
    $totalValue = $items->sum(fn ($item) => $item->subtotal ?? ($item->quantity * $item->unit_price));
    $orderedOn  = optional($order->order_date)->format('M d, Y') ?: '—';

    $amountPaid = $invoice ? $invoice->payments->sum('amount') : 0;
    $balanceDue = $invoice ? max(0, $invoice->total - $amountPaid) : null;

    // Warehouse and Retailer carry no location_type column, so the role comes
    // from the morph class the order was fulfilled against.
    $fulfilmentRole = match ($order->fulfillment_location_type) {
        \App\Models\Warehouse::class => 'Warehouse',
        \App\Models\Retailer::class  => 'Retailer',
        default                      => 'Location',
    };

    // Where this order sits in Record Order -> Confirm -> Pick -> Invoice -> Payment.
    $stages = \App\Support\OrderWorkflow::forOrder($order);
@endphp

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Order {{ $order->formatted_id ?? '#' . $order->id }}</h1>
            <p class="text-muted mb-0">
                <span class="badge bg-{{ $statusColour }}">{{ ucfirst($status) }}</span>
                <span class="ms-2">Ordered {{ $orderedOn }}</span>
                <span class="ms-2">·</span>
                <span class="ms-2">{{ $customer->name ?: 'Unknown customer' }}</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            @if($isPending)
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#confirmOrderModal">
                    <i class="bi bi-check-circle me-1"></i> Confirm Order
                </button>
                <a href="{{ route('orders.edit', $order) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-pencil me-1"></i> Edit Order
                </a>
            @else
                {{-- A confirmed order is not completed from here: PickingListObserver
                     completes it, and raises the invoice, once the goods are picked. --}}
                @if($picking && ! $isPicked)
                    <a href="{{ route('customer-picking.show', $picking->id) }}" class="btn btn-primary">
                        <i class="bi bi-box-seam me-1"></i> Go to Picking
                    </a>
                @endif
                @if($invoice)
                    <a href="{{ route('invoices.show', $invoice->id) }}"
                       class="btn btn-{{ $picking && ! $isPicked ? 'outline-primary' : 'primary' }}">
                        <i class="bi bi-receipt me-1"></i> View Invoice
                    </a>
                @endif
                {{-- Goods have to have gone out before any of them can come back --}}
                @if($isCompleted)
                    <a href="{{ route('returns.create', ['order_id' => $order->id]) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-return-left me-1"></i> Create Return
                    </a>
                @endif
            @endif
        </div>
    </div>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Where this order sits in the sales workflow --}}
    <x-workflow-rail :stages="$stages" />

    {{-- Headline figures --}}
    <div class="detail-card mb-4">
        <div class="detail-card__body">
            <div class="detail-figures">
                <div class="detail-figure">
                    <span class="detail-figure__label">Order Total</span>
                    <span class="detail-figure__value detail-figure__value--lead">
                        ${{ number_format($order->total_amount ?? 0, 2) }}
                    </span>
                    <span class="detail-figure__note">At the prices ordered</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Units Ordered</span>
                    <span class="detail-figure__value">{{ number_format($totalUnits) }}</span>
                    <span class="detail-figure__note">
                        @if($isPicked)
                            Dispatched to customer
                        @elseif($inProgress || $isCompleted)
                            Reserved, not yet dispatched
                        @elseif($isCancelled)
                            Nothing reserved
                        @else
                            No stock reserved yet
                        @endif
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Products</span>
                    <span class="detail-figure__value">{{ number_format($items->count()) }}</span>
                    <span class="detail-figure__note">Distinct lines</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">{{ $invoice ? 'Balance Due' : 'Ordered' }}</span>
                    <span class="detail-figure__value">
                        @if($invoice)
                            ${{ number_format($balanceDue, 2) }}
                        @else
                            {{ optional($order->order_date)->format('M d') ?: '—' }}
                        @endif
                    </span>
                    <span class="detail-figure__note">
                        @if($invoice)
                            ${{ number_format($amountPaid, 2) }} of ${{ number_format($invoice->total, 2) }} paid
                        @else
                            {{ optional($order->order_date)->format('Y') ?: 'No date set' }}
                        @endif
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Status and what the page can do about it --}}
    <div class="detail-card mb-4">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-flag"></i></span>
            <div>
                <h2 class="detail-card__title">Status</h2>
                <p class="detail-card__subtitle">Where this order has reached, and what happens next</p>
            </div>
        </div>
        <div class="detail-card__body">
            <div class="detail-kv">
                <span class="detail-kv__label">Current Status</span>
                <span class="detail-kv__value">
                    <span class="badge bg-{{ $statusColour }}">{{ ucfirst($status) }}</span>
                </span>
            </div>

            <div class="detail-panel mt-3 mb-0">
                @if($isPending)
                    No stock is held for this order yet. Confirming it reserves the goods at
                    {{ $fulfilmentName }} and raises a picking list for the team.
                @elseif($isCancelled)
                    This order was cancelled. Nothing further will be picked, invoiced or charged against it.
                @elseif($isPicked && $invoice)
                    The goods have left {{ $fulfilmentName }} and
                    {{ $invoice->invoice_number ?: $invoice->formatted_id }} has been raised.
                    Payment is recorded on the invoice.
                @elseif($isPicked)
                    The goods have left {{ $fulfilmentName }}. The invoice
                    is raised from the picking list.
                @elseif($picking)
                    Stock is reserved at {{ $fulfilmentName }} but has not
                    left yet. It moves, and the invoice is raised, when picking {{ $picking->picking_number ?: 'list' }}
                    is completed.
                @else
                    This order is confirmed. Stock is reserved against it and leaves once the goods are picked.
                @endif
            </div>
        </div>
    </div>

    {{-- Parties and paperwork --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-person"></i></span>
                    <div>
                        <h2 class="detail-card__title">Customer</h2>
                        <p class="detail-card__subtitle">Who the goods are sold to</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Name</span>
                        <span class="detail-kv__value">{{ $customer->name ?: '—' }}</span>
                    </div>
                    @if($customer->email)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Email</span>
                            <span class="detail-kv__value">{{ $customer->email }}</span>
                        </div>
                    @endif
                    @if($customer->phone)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Phone</span>
                            <span class="detail-kv__value">{{ $customer->phone }}</span>
                        </div>
                    @endif
                    @if($customer->address)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Address</span>
                            <span class="detail-kv__value detail-kv__value--muted">{{ $customer->address }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-building"></i></span>
                    <div>
                        <h2 class="detail-card__title">Fulfilled From</h2>
                        <p class="detail-card__subtitle">Where the stock is taken from</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    @if($hasFulfilment)
                        <div class="detail-kv">
                            <span class="detail-kv__label">{{ $fulfilmentRole }}</span>
                            <span class="detail-kv__value">{{ $fulfilment->name ?: '—' }}</span>
                        </div>
                        @if($fulfilment->contact_person)
                            <div class="detail-kv">
                                <span class="detail-kv__label">Contact</span>
                                <span class="detail-kv__value">{{ $fulfilment->contact_person }}</span>
                            </div>
                        @endif
                        @if($fulfilment->address)
                            <div class="detail-kv">
                                <span class="detail-kv__label">Address</span>
                                <span class="detail-kv__value detail-kv__value--muted">{{ $fulfilment->address }}</span>
                            </div>
                        @endif
                    @else
                        <div class="detail-panel mb-0">
                            No fulfilment location was recorded for this order, so there is nowhere to
                            reserve stock from. It is set from the first product line when the order is created.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-link-45deg"></i></span>
                    <div>
                        <h2 class="detail-card__title">Related Records</h2>
                        <p class="detail-card__subtitle">Documents raised from this order</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Picking List</span>
                        <span class="detail-kv__value">
                            @if($picking)
                                <a href="{{ route('customer-picking.show', $picking->id) }}">
                                    {{ $picking->picking_number ?: $picking->formatted_id }}
                                </a>
                                <span class="text-muted small d-block">
                                    {{ ucfirst($picking->status) }} ·
                                    {{ optional($picking->picking_date)->format('M d, Y') ?: 'No date set' }}
                                </span>
                            @else
                                <span class="text-muted">Created when the order is confirmed</span>
                            @endif
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Invoice</span>
                        <span class="detail-kv__value">
                            @if($invoice)
                                <a href="{{ route('invoices.show', $invoice->id) }}">
                                    {{ $invoice->invoice_number ?: $invoice->formatted_id }}
                                </a>
                                <span class="text-muted small d-block">
                                    ${{ number_format($invoice->total, 2) }} ·
                                    {{ ucfirst(str_replace('_', ' ', $invoice->payment_status)) }}
                                </span>
                            @else
                                <span class="text-muted">Raised when picking completes</span>
                            @endif
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Order Placed</span>
                        <span class="detail-kv__value detail-kv__value--muted">
                            {{ optional($order->created_at)->format('M d, Y') ?: '—' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Line items --}}
    <div class="detail-card order-items">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-cart"></i></span>
            <div>
                <h2 class="detail-card__title">Ordered Products</h2>
                <p class="detail-card__subtitle">
                    What {{ $customer->name ?: 'the customer' }} is buying, at the prices recorded here.
                </p>
            </div>
        </div>
        <div class="detail-card__body detail-card__body--flush">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 3rem;">#</th>
                            <th>Product</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $index => $item)
                            <tr>
                                <td data-label="#">{{ $index + 1 }}</td>
                                <td data-label="Product">
                                    <strong>{{ $item->product->name ?: 'Unknown product' }}</strong>
                                    <span class="d-block text-muted small">
                                        SKU {{ $item->product->sku ?: 'N/A' }}
                                    </span>
                                </td>
                                <td data-label="Quantity" class="text-end">
                                    {{ number_format($item->quantity) }}
                                </td>
                                <td data-label="Unit Price" class="text-end">
                                    ${{ number_format($item->unit_price, 2) }}
                                </td>
                                <td data-label="Line Total" class="text-end fw-semibold">
                                    ${{ number_format($item->subtotal ?? ($item->quantity * $item->unit_price), 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No items on this order.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($items->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="2" class="text-muted">
                                    Total across {{ $items->count() }}
                                    {{ Str::plural('product', $items->count()) }}
                                </td>
                                <td data-label="Total quantity" class="text-end fw-semibold">
                                    {{ number_format($totalUnits) }}
                                </td>
                                <td></td>
                                <td data-label="Order total" class="text-end fw-bold">
                                    ${{ number_format($totalValue, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    @if($order->notes)
        <div class="detail-card mt-4">
            <div class="detail-card__header">
                <span class="detail-card__step"><i class="bi bi-sticky"></i></span>
                <div>
                    <h2 class="detail-card__title">Notes</h2>
                    <p class="detail-card__subtitle">Recorded with the order</p>
                </div>
            </div>
            <div class="detail-card__body">
                <div class="detail-panel mb-0">{{ $order->notes }}</div>
            </div>
        </div>
    @endif

    @if($isPending)
        {{-- Pending -> Confirmed: reserves stock and raises the picking list --}}
        <div class="modal fade" id="confirmOrderModal" tabindex="-1" aria-labelledby="confirmOrderModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="confirmOrderModalLabel">Change Order Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-warning">{{ ucfirst($status) }}</span>
                            <i class="bi bi-arrow-right text-muted"></i>
                            <span class="badge bg-info">Confirmed</span>
                        </div>
                        @if($hasFulfilment)
                            <p class="mb-2">
                                Confirming order {{ $order->formatted_id ?? '#' . $order->id }} for
                                {{ $customer->name ?: 'this customer' }} will:
                            </p>
                            <ul class="mb-3 ps-3">
                                <li class="mb-1">
                                    Hold <strong>{{ number_format($totalUnits) }}
                                    {{ Str::plural('unit', $totalUnits) }}</strong> across
                                    {{ $items->count() }} {{ Str::plural('product', $items->count()) }} at
                                    <strong>{{ $fulfilment->name }}</strong>, so they can no longer be sold
                                    on another order
                                </li>
                                <li class="mb-1">Raise a picking list for the team at {{ $fulfilment->name }}</li>
                                <li class="mb-1">Move the order's status from Pending to Confirmed</li>
                                <li>Take you to that picking list, where the goods are marked as picked</li>
                            </ul>
                            <div class="detail-panel mb-0">
                                <span class="d-block mb-1">
                                    <strong>What does not happen yet:</strong> the stock stays put at
                                    {{ $fulfilment->name }} and no invoice is raised. Both of those wait until
                                    the picking list is completed.
                                </span>
                                <span class="text-muted small">
                                    A confirmed order cannot be returned to Pending from this page.
                                </span>
                            </div>
                        @else
                            <p class="mb-0">
                                This order has no fulfilment location, so there is nowhere to reserve stock
                                from. The location is taken from the first product line when the order is
                                created — record the order again with a location to confirm it.
                            </p>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        @if($hasFulfilment)
                            <form action="{{ route('orders.update-status', $order) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="confirmed">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-1"></i> Confirm Order
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('styles')
<style>
    /* Totals row: keep it readable once the table collapses to cards on mobile */
    @media (max-width: 768px) {
        .order-items tfoot tr {
            display: block;
            border-top: 1px solid #dee2e6;
        }

        .order-items tfoot td {
            display: block;
            text-align: right;
            padding: 0.35rem 0.75rem;
        }

        .order-items tfoot td:empty {
            display: none;
        }

        /* Mirror the tbody label treatment custom.css applies below 768px */
        .order-items tfoot td[data-label]::before {
            content: attr(data-label);
            float: left;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.85em;
        }
    }
</style>
@endpush

@extends('layouts.app')

@php
    $items = $pickingList->pickingItems ?? $pickingList->items ?? collect();

    // PickingList::order() matches on reference_id alone, so a transfer's list
    // would otherwise pull in whichever order happens to share that id.
    $order    = $pickingList->reference_type === \App\Models\Order::class ? $pickingList->order : null;
    $customer = $order?->customer;

    // This route is not restricted to customer-bound lists the way the index is,
    // so the destination is read off the list when there is no order behind it.
    $destination     = $pickingList->toLocation;
    $hasDestination  = $destination && $destination->exists;
    $destinationName = $customer?->name ?: ($hasDestination ? $destination->name : null);
    $destinationRole = match ($pickingList->to_location_type) {
        \App\Models\Customer::class  => 'Customer',
        \App\Models\Retailer::class  => 'Retailer',
        \App\Models\Warehouse::class => 'Warehouse',
        default                      => 'Destination',
    };

    // fromLocation answers withDefault() through an accessor that re-queries on
    // every read, so it is resolved once and checked with exists.
    $warehouse     = $pickingList->fromLocation;
    $hasWarehouse  = $warehouse && $warehouse->exists;
    $warehouseName = $hasWarehouse ? $warehouse->name : 'the warehouse';

    $status      = $pickingList->status;
    $isCompleted = in_array($status, ['completed', 'closed', 'verified'], true);
    $isCancelled = $status === 'cancelled';
    $isOpen      = ! $isCompleted && ! $isCancelled;

    $statusColour = match (true) {
        $isCompleted => 'success',
        $isCancelled => 'danger',
        default      => 'warning',
    };

    $requested = $items->sum('quantity_requested');
    $picked    = $items->sum(fn ($item) => $item->quantity_picked ?? 0);
    $progress  = $pickingList->progress_percentage ?? 0;

    // Stock rows for the source warehouse, keyed by product so each line can
    // report what is physically on the shelf.
    $stocks = $hasWarehouse ? $warehouse->productStocks->keyBy('product_id') : collect();

    // Lines the shelf cannot cover. Compared against physical quantity, not
    // quantity - reserved: the reservation being netted off is this pick's own.
    $onHandFor  = fn ($item) => (int) ($stocks->get($item->product_id)->quantity ?? 0);
    $shortLines = $items->filter(fn ($item) => $onHandFor($item) < $item->quantity_requested);

    $stages = $order ? \App\Support\OrderWorkflow::forPicking($pickingList) : null;
@endphp

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Picking {{ $pickingList->picking_number ?: $pickingList->formatted_id }}</h1>
            <p class="text-muted mb-0">
                <span class="badge bg-{{ $statusColour }}">{{ ucfirst($status) }}</span>
                <span class="ms-2">{{ $warehouseName }}</span>
                <span class="ms-2">→</span>
                <span class="ms-2">{{ $destinationName ?: 'No destination' }}</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            @if($isOpen && $items->isNotEmpty())
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#completePickingModal">
                    <i class="bi bi-check-circle me-1"></i> Mark as Completed
                </button>
            @endif
            @if($order)
                <a href="{{ route('orders.show', $order->id) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-receipt me-1"></i> View Order
                </a>
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

    {{-- Picking is one stage of the order's journey, so the whole rail is shown --}}
    @if($stages)
        <x-workflow-rail :stages="$stages" />
    @endif

    {{-- Headline figures --}}
    <div class="detail-card mb-4">
        <div class="detail-card__body">
            <div class="detail-figures">
                <div class="detail-figure">
                    <span class="detail-figure__label">Units to Pick</span>
                    <span class="detail-figure__value detail-figure__value--lead">{{ number_format($requested) }}</span>
                    <span class="detail-figure__note">
                        Held at {{ $warehouseName }}
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Units Picked</span>
                    <span class="detail-figure__value">{{ number_format($picked) }}</span>
                    <span class="detail-figure__note">
                        @if($isCompleted)
                            Left the warehouse
                        @elseif($isCancelled)
                            Picking cancelled
                        @else
                            Not picked yet
                        @endif
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Products</span>
                    <span class="detail-figure__value">{{ number_format($items->count()) }}</span>
                    <span class="detail-figure__note">Distinct lines</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">{{ $order ? 'Order Value' : 'Raised' }}</span>
                    <span class="detail-figure__value">
                        @if($order)
                            ${{ number_format($order->total_amount ?? 0, 2) }}
                        @else
                            {{ optional($pickingList->picking_date)->format('M d') ?: '—' }}
                        @endif
                    </span>
                    <span class="detail-figure__note">
                        @if($order)
                            Invoiced when this pick completes
                        @else
                            {{ optional($pickingList->picking_date)->format('Y') ?: 'No date set' }}
                        @endif
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Status, progress, and what completing the pick will do --}}
    <div class="detail-card mb-4">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-flag"></i></span>
            <div>
                <h2 class="detail-card__title">Status</h2>
                <p class="detail-card__subtitle">Where this pick has reached, and what happens next</p>
            </div>
        </div>
        <div class="detail-card__body">
            <div class="row g-4">
                <div class="col-md-5">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Current Status</span>
                        <span class="detail-kv__value">
                            <span class="badge bg-{{ $statusColour }}">{{ ucfirst($status) }}</span>
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Raised</span>
                        <span class="detail-kv__value detail-kv__value--muted">
                            {{ optional($pickingList->picking_date)->format('M d, Y') ?: optional($pickingList->created_at)->format('M d, Y') ?: '—' }}
                        </span>
                    </div>
                    @if($pickingList->completed_at)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Completed</span>
                            <span class="detail-kv__value detail-kv__value--muted">
                                {{ $pickingList->completed_at->format('M d, Y H:i') }}
                            </span>
                        </div>
                    @endif
                </div>
                <div class="col-md-7">
                    <span class="detail-kv__label">Progress</span>
                    <div class="progress mt-2 mb-2" style="height: 0.6rem;" role="progressbar"
                         aria-label="Units picked" aria-valuenow="{{ (int) $progress }}"
                         aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar bg-success" style="width: {{ $progress }}%;"></div>
                    </div>
                    <span class="detail-figure__note">
                        {{ number_format($picked) }} of {{ number_format($requested) }}
                        {{ Str::plural('unit', $requested) }} picked
                        · {{ number_format($progress, 0) }}%
                    </span>

                    <div class="detail-panel mt-3 mb-0">
                        @if($isCompleted)
                            The goods have left {{ $warehouseName }} and the reservation is released.
                            @if($order)
                                Order {{ $order->formatted_id }} is completed and its invoice raised.
                            @endif
                        @elseif($isCancelled)
                            This pick was cancelled. Nothing left {{ $warehouseName }}.
                        @else
                            The stock is still on the shelf at {{ $warehouseName }}, held against this
                            pick so nothing else can sell it. Completing the pick is what moves it out.
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Where the goods come from, where they go, and the order behind them --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-building"></i></span>
                    <div>
                        <h2 class="detail-card__title">Picked From</h2>
                        <p class="detail-card__subtitle">Where the goods are on the shelf</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    @if($hasWarehouse)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Warehouse</span>
                            <span class="detail-kv__value">{{ $warehouse->name ?: '—' }}</span>
                        </div>
                        @if($warehouse->contact_person)
                            <div class="detail-kv">
                                <span class="detail-kv__label">Contact</span>
                                <span class="detail-kv__value">{{ $warehouse->contact_person }}</span>
                            </div>
                        @endif
                        @if($warehouse->address)
                            <div class="detail-kv">
                                <span class="detail-kv__label">Address</span>
                                <span class="detail-kv__value detail-kv__value--muted">{{ $warehouse->address }}</span>
                            </div>
                        @endif
                    @else
                        <div class="detail-panel mb-0">
                            No source warehouse is recorded on this picking list, so there is nothing to
                            pick from.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-person"></i></span>
                    <div>
                        <h2 class="detail-card__title">Delivered To</h2>
                        <p class="detail-card__subtitle">Who the goods are going to</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    @if($customer)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Customer</span>
                            <span class="detail-kv__value">{{ $customer->name ?: '—' }}</span>
                        </div>
                        @if($customer->phone)
                            <div class="detail-kv">
                                <span class="detail-kv__label">Phone</span>
                                <span class="detail-kv__value">{{ $customer->phone }}</span>
                            </div>
                        @endif
                        @if($customer->email)
                            <div class="detail-kv">
                                <span class="detail-kv__label">Email</span>
                                <span class="detail-kv__value">{{ $customer->email }}</span>
                            </div>
                        @endif
                        @if($customer->address)
                            <div class="detail-kv">
                                <span class="detail-kv__label">Address</span>
                                <span class="detail-kv__value detail-kv__value--muted">{{ $customer->address }}</span>
                            </div>
                        @endif
                    @elseif($hasDestination)
                        <div class="detail-kv">
                            <span class="detail-kv__label">{{ $destinationRole }}</span>
                            <span class="detail-kv__value">{{ $destination->name ?: '—' }}</span>
                        </div>
                        @if($destination->address)
                            <div class="detail-kv">
                                <span class="detail-kv__label">Address</span>
                                <span class="detail-kv__value detail-kv__value--muted">{{ $destination->address }}</span>
                            </div>
                        @endif
                    @else
                        <div class="detail-panel mb-0">
                            No destination is recorded against this pick.
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
                        <p class="detail-card__subtitle">What this pick belongs to</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Order</span>
                        <span class="detail-kv__value">
                            @if($order)
                                <a href="{{ route('orders.show', $order->id) }}">{{ $order->formatted_id }}</a>
                                <span class="text-muted small d-block">
                                    {{ ucfirst($order->status) }} ·
                                    {{ optional($order->order_date)->format('M d, Y') ?: 'No date set' }}
                                </span>
                            @else
                                <span class="text-muted">Not raised from a sales order</span>
                            @endif
                        </span>
                    </div>
                    @if($order)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Invoice</span>
                            <span class="detail-kv__value">
                                @if($order->invoice)
                                    <a href="{{ route('invoices.show', $order->invoice->id) }}">
                                        {{ $order->invoice->invoice_number ?: $order->invoice->formatted_id }}
                                    </a>
                                    <span class="text-muted small d-block">
                                        ${{ number_format($order->invoice->total, 2) }} ·
                                        {{ ucfirst(str_replace('_', ' ', $order->invoice->payment_status)) }}
                                    </span>
                                @else
                                    <span class="text-muted">Raised when this pick completes</span>
                                @endif
                            </span>
                        </div>
                    @endif
                    <div class="detail-kv">
                        <span class="detail-kv__label">Picking List</span>
                        <span class="detail-kv__value detail-kv__value--muted">
                            {{ $pickingList->picking_number ?: $pickingList->formatted_id }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- The pick itself --}}
    <div class="detail-card picking-items">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-list-check"></i></span>
            <div>
                <h2 class="detail-card__title">Items to Pick</h2>
                <p class="detail-card__subtitle">
                    What to take off the shelf at {{ $warehouseName }}, and whether it is there.
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
                            <th class="text-end">Requested</th>
                            <th class="text-end">Picked</th>
                            <th class="text-end">On Hand</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $index => $item)
                            @php
                                $onHand    = $onHandFor($item);
                                $pickedQty = (int) ($item->quantity_picked ?? 0);
                                $short     = $onHand < $item->quantity_requested;
                            @endphp
                            <tr>
                                <td data-label="#">{{ $index + 1 }}</td>
                                <td data-label="Product">
                                    <strong>{{ $item->product->name ?: 'Unknown product' }}</strong>
                                    <span class="d-block text-muted small">
                                        SKU {{ $item->product->sku ?: 'N/A' }}
                                    </span>
                                </td>
                                <td data-label="Requested" class="text-end">
                                    {{ number_format($item->quantity_requested) }}
                                </td>
                                <td data-label="Picked" class="text-end">
                                    <span class="fw-semibold">{{ number_format($pickedQty) }}</span>
                                    @if($pickedQty >= $item->quantity_requested)
                                        <span class="badge bg-success ms-1">Complete</span>
                                    @elseif($pickedQty > 0)
                                        <span class="badge bg-info ms-1">Partial</span>
                                    @else
                                        <span class="badge bg-warning ms-1">Pending</span>
                                    @endif
                                </td>
                                {{-- Physical stock, not quantity - reserved: the reservation being
                                     netted off is this pick's own, so it would always read short. --}}
                                <td data-label="On Hand" class="text-end">
                                    <span class="fw-semibold {{ $short ? 'text-danger' : '' }}">
                                        {{ number_format($onHand) }}
                                    </span>
                                    <span class="d-block text-muted small">
                                        @if($short)
                                            <i class="bi bi-exclamation-triangle me-1"></i>Short by
                                            {{ number_format($item->quantity_requested - $onHand) }}
                                        @else
                                            At {{ $warehouseName }}
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No items on this picking list.
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
                                <td data-label="Total requested" class="text-end fw-semibold">
                                    {{ number_format($requested) }}
                                </td>
                                <td data-label="Total picked" class="text-end fw-bold">
                                    {{ number_format($picked) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    @if($isOpen && $items->isNotEmpty())
        {{-- Completing the pick is what actually moves the stock, so it is spelled out --}}
        <div class="modal fade" id="completePickingModal" tabindex="-1" aria-labelledby="completePickingModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="completePickingModalLabel">Change Picking Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-warning">{{ ucfirst($status) }}</span>
                            <i class="bi bi-arrow-right text-muted"></i>
                            <span class="badge bg-success">Completed</span>
                        </div>
                        <p class="mb-2">
                            Completing {{ $pickingList->picking_number ?: $pickingList->formatted_id }} will:
                        </p>
                        <ul class="mb-3 ps-3">
                            <li class="mb-1">
                                Mark all {{ number_format($requested) }}
                                {{ Str::plural('unit', $requested) }} as picked in full
                            </li>
                            <li class="mb-1">
                                Take that stock out of <strong>{{ $warehouseName }}</strong> and release
                                the reservation holding it
                            </li>
                            @if($order)
                                <li class="mb-1">
                                    Complete order {{ $order->formatted_id }} and raise the invoice for
                                    {{ $customer?->name ?: 'the customer' }}
                                </li>
                                <li>Take you to that invoice, where payment is recorded</li>
                            @elseif(in_array($pickingList->to_location_type, [\App\Models\Warehouse::class, \App\Models\Retailer::class], true))
                                {{-- Only these destinations take an inbound posting from the observer --}}
                                <li>Add the picked stock to {{ $destinationName ?: 'the destination' }}</li>
                            @endif
                        </ul>
                        <div class="detail-panel mb-0">
                            <span class="d-block mb-1">
                                This is the point the goods actually leave {{ $warehouseName }}. It cannot
                                be undone from this page.
                            </span>
                            @if($shortLines->isNotEmpty())
                                <span class="text-danger small">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    {{ $shortLines->count() }}
                                    {{ Str::plural('line', $shortLines->count()) }}
                                    {{ $shortLines->count() === 1 ? 'is' : 'are' }} short of stock on the
                                    shelf, so completing will push that balance below zero.
                                </span>
                            @else
                                <span class="text-muted small">
                                    Every line has enough stock on the shelf to be picked in full.
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form action="{{ route('warehouse-to-customer-picking.update-status', $pickingList) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="completed">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i> Mark as Completed
                            </button>
                        </form>
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
        .picking-items tfoot tr {
            display: block;
            border-top: 1px solid #dee2e6;
        }

        .picking-items tfoot td {
            display: block;
            text-align: right;
            padding: 0.35rem 0.75rem;
        }

        .picking-items tfoot td:empty {
            display: none;
        }

        /* Mirror the tbody label treatment custom.css applies below 768px */
        .picking-items tfoot td[data-label]::before {
            content: attr(data-label);
            float: left;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.85em;
        }
    }
</style>
@endpush

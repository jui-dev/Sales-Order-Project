@extends('layouts.app')

@php
    $items  = $pickingList->pickingItems ?? collect();
    $from   = $pickingList->fromLocation;
    $to     = $pickingList->toLocation;

    // The picking list is a real row for every transfer recorded through the UI.
    // The show route falls back to an unsaved stand-in only when one is missing,
    // and that stand-in has no actions to offer.
    $hasPickingList = $pickingList->exists;

    $status      = $stockTransfer->status ?? 'confirmed';
    $isCompleted = $status === 'completed';
    $isCancelled = $status === 'cancelled';
    // 'pending' is accepted for rows predating the confirmed/completed rename.
    $isConfirmed = in_array($status, ['confirmed', 'pending'], true);

    $statusColour = $isCompleted ? 'success' : ($isCancelled ? 'danger' : 'info');
    $statusLabel  = $isConfirmed ? 'Confirmed' : ucfirst($status);

    // Fallback items are StockTransferItems, which carry `quantity` rather than
    // the picking list's requested/picked pair.
    $requested = $items->sum(fn ($item) => $item->quantity_requested ?? $item->quantity ?? 0);
    $moved     = $items->sum(fn ($item) => $item->quantity_picked ?? 0);

    // Valued at the cost in force on the transfer date - the same basis
    // StockTransferObserver posts to the ledger, so the page and the journal
    // entry agree even after a later delivery moves the cost.
    $costs = app(\App\Services\Pricing\ProductCostService::class);
    $movedAt = $transfer->transfer_date ? \Illuminate\Support\Carbon::parse($transfer->transfer_date) : now();
    $value = $items->sum(function ($item) use ($costs, $movedAt) {
        $quantity = $item->quantity_requested ?? $item->quantity ?? 0;
        return ($item->product ? $costs->costAtOrLegacy($item->product, $movedAt) : 0) * $quantity;
    });

    // Units the band shows travelling: what is reserved, or what actually left.
    $unitsInPlay = $isCompleted ? $moved : $requested;

    // This route also serves transfers that are not warehouse -> retailer: GRN
    // posting records vendor -> warehouse, and retailer returns record
    // retailer -> warehouse. Read the endpoint roles off the transfer rather
    // than assuming, so the band never mislabels a vendor as a warehouse.
    $roleOf = fn (?string $type) => match ($type) {
        \App\Models\Warehouse::class => 'warehouse',
        \App\Models\Retailer::class  => 'retailer',
        \App\Models\Vendor::class    => 'vendor',
        \App\Models\Customer::class  => 'customer',
        default                      => 'location',
    };

    $fromRole = $roleOf($stockTransfer->from_location_type);
    $toRole   = $roleOf($stockTransfer->to_location_type);

    $fromName = $from->name ?? 'Unknown ' . $fromRole;
    $toName   = $to->name ?? 'Unknown ' . $toRole;
@endphp

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Transfer {{ $stockTransfer->formatted_id ?? '#' . $stockTransfer->id }}</h1>
            <p class="text-muted mb-0">
                <span class="badge bg-{{ $statusColour }}">{{ $statusLabel }}</span>
                <span class="ms-2">{{ optional($stockTransfer->transfer_date)->format('M d, Y') ?: 'No date set' }}</span>
                <span class="ms-2">·</span>
                <span class="ms-2">Picking list {{ $pickingList->picking_number }}</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            @if($isConfirmed && $hasPickingList)
                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                        data-bs-target="#completeTransferModal{{ $pickingList->id }}">
                    <i class="bi bi-check-circle me-1"></i> Complete Transfer
                </button>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelTransferModal">
                    <i class="bi bi-x-circle me-1"></i> Cancel Transfer
                </button>
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

    {{-- The movement itself: where the goods start, where they end, and whether
         they have actually left. A transfer is the only record in the system
         whose subject is a journey, so the page leads with it. --}}
    <div class="detail-card transfer-route transfer-route--{{ $status }} mb-4">
        <div class="detail-card__body">
            <div class="transfer-route__grid">
                <div class="transfer-route__end">
                    <span class="transfer-route__role">From {{ $fromRole }}</span>
                    <span class="transfer-route__name">{{ $fromName }}</span>
                    @if($from?->address)
                        <span class="transfer-route__meta">{{ $from->address }}</span>
                    @endif
                </div>

                <div class="transfer-route__passage">
                    <span class="transfer-route__pill">
                        @if($isCancelled)
                            Nothing moved
                        @else
                            {{ number_format($unitsInPlay) }} {{ Str::plural('unit', $unitsInPlay) }}
                            {{ $isCompleted ? 'moved' : 'reserved' }}
                        @endif
                    </span>
                    {{-- The rule and arrow only restate the direction the labels
                         already give, so they stay out of the accessible tree. --}}
                    <span class="transfer-route__line" aria-hidden="true">
                        <i class="bi bi-chevron-right transfer-route__chevron"></i>
                    </span>
                </div>

                <div class="transfer-route__end transfer-route__end--to">
                    <span class="transfer-route__role">To {{ $toRole }}</span>
                    <span class="transfer-route__name">{{ $toName }}</span>
                    @if($to?->address)
                        <span class="transfer-route__meta">{{ $to->address }}</span>
                    @endif
                </div>
            </div>

            <p class="transfer-route__state mb-0">
                @if($isConfirmed)
                    Stock is held at {{ $fromName }} and is not available at {{ $toName }} yet.
                    Completing the transfer moves it.
                @elseif($isCompleted)
                    Stock has left {{ $fromName }} and is on hand at {{ $toName }}.
                @else
                    The reservation was released. Stock never left {{ $fromName }}.
                @endif
            </p>
        </div>
    </div>

    {{-- Headline figures --}}
    <div class="detail-card mb-4">
        <div class="detail-card__body">
            <div class="detail-figures">
                <div class="detail-figure">
                    <span class="detail-figure__label">Units Requested</span>
                    <span class="detail-figure__value detail-figure__value--lead">{{ number_format($requested) }}</span>
                    <span class="detail-figure__note">{{ $isConfirmed ? 'Reserved at source' : 'On the transfer' }}</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Units Moved</span>
                    <span class="detail-figure__value">{{ number_format($moved) }}</span>
                    <span class="detail-figure__note">
                        {{ $isCompleted ? 'Now at the destination' : 'Nothing moved yet' }}
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Products</span>
                    <span class="detail-figure__value">{{ number_format($items->count()) }}</span>
                    <span class="detail-figure__note">Distinct lines</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Inventory Value</span>
                    <span class="detail-figure__value">${{ number_format($value, 2) }}</span>
                    <span class="detail-figure__note">At product cost</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Line items. A transfer moves in full, so this is a read-only manifest in
         every state - the quantity is fixed when the transfer is confirmed. --}}
    <div class="detail-card transfer-items mb-4">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-box-seam"></i></span>
            <div>
                <h2 class="detail-card__title">{{ $isConfirmed ? 'Products to Transfer' : 'Transferred Products' }}</h2>
                <p class="detail-card__subtitle">
                    @if($isConfirmed)
                        Reserved at {{ $fromName }}, waiting to be sent to {{ $toName }}.
                    @else
                        What left {{ $fromName }} on this transfer.
                    @endif
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
                                <th class="text-end">Moved</th>
                                <th>Line Status</th>
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
                                    <td data-label="Requested" class="text-end">
                                        {{ number_format($item->quantity_requested ?? $item->quantity ?? 0) }}
                                    </td>
                                    <td data-label="Moved" class="text-end fw-semibold">
                                        {{ number_format($item->quantity_picked ?? 0) }}
                                    </td>
                                    <td data-label="Line Status">
                                        @php
                                            // Legacy rows may still read 'pending'; show them as confirmed.
                                            $lineStatus = $item->status ?? 'confirmed';
                                            $lineStatus = $lineStatus === 'pending' ? 'confirmed' : $lineStatus;
                                            $lineColour = match ($lineStatus) {
                                                'picked'    => 'success',
                                                'cancelled' => 'danger',
                                                'short'     => 'warning',
                                                'confirmed' => 'info',
                                                default     => 'secondary',
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $lineColour }}">{{ ucfirst($lineStatus) }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        No products on this transfer.
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
                                    <td data-label="Total moved" class="text-end fw-bold">
                                        {{ number_format($moved) }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-clock-history"></i></span>
                    <div>
                        <h2 class="detail-card__title">Record</h2>
                        <p class="detail-card__subtitle">Identifiers and dates for this transfer</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Transfer</span>
                        <span class="detail-kv__value">{{ $stockTransfer->formatted_id ?? '#' . $stockTransfer->id }}</span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Picking List</span>
                        <span class="detail-kv__value">{{ $pickingList->picking_number }}</span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Transfer Date</span>
                        <span class="detail-kv__value">
                            {{ optional($stockTransfer->transfer_date)->format('M d, Y') ?: '—' }}
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Recorded</span>
                        <span class="detail-kv__value detail-kv__value--muted">
                            {{ optional($stockTransfer->created_at)->format('M d, Y H:i') ?: '—' }}
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
            </div>
        </div>

        <div class="col-lg-6">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-sticky"></i></span>
                    <div>
                        <h2 class="detail-card__title">Notes</h2>
                        <p class="detail-card__subtitle">Recorded with the transfer</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    @if($stockTransfer->notes)
                        <div class="detail-panel mb-0">{{ $stockTransfer->notes }}</div>
                    @else
                        <p class="text-muted mb-0">No notes were added to this transfer.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($isConfirmed && $hasPickingList)
        {{-- Shared with the transfer listing so completing reads the same either way --}}
        <x-transfer-complete-modal
            :picking-list="$pickingList"
            :from-name="$fromName"
            :to-name="$toName"
            :units="$requested" />

        <div class="modal fade" id="cancelTransferModal" tabindex="-1" aria-labelledby="cancelTransferModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cancelTransferModalLabel">Cancel Transfer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-info">Confirmed</span>
                            <i class="bi bi-arrow-right text-muted"></i>
                            <span class="badge bg-danger">Cancelled</span>
                        </div>
                        <p class="mb-2">
                            Releases the {{ number_format($requested) }}
                            {{ Str::plural('unit', $requested) }} held at {{ $fromName }},
                            making them available to sell again.
                        </p>
                        <p class="text-muted small mb-0">
                            No stock moves to {{ $toName }}. This cannot be undone.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep confirmed</button>
                        <form action="{{ route('stock-transfers.warehouse-to-retailer.cancel', $pickingList) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-x-circle me-1"></i> Cancel Transfer
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
    /* ---- Route band -------------------------------------------------
       The one piece of this page that is not shared kit. A transfer's
       subject is a journey, so the two locations are set as endpoints with
       the goods shown in the passage between them. The line's treatment
       carries the state: dashed while the stock is only reserved, solid
       once it has actually moved.
       ------------------------------------------------------------------ */
    .transfer-route__grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(7rem, 0.9fr) minmax(0, 1fr);
        align-items: center;
        gap: 1.25rem;
    }

    .transfer-route__end {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .transfer-route__end--to {
        text-align: right;
        align-items: flex-end;
    }

    .transfer-route__role {
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        color: var(--medium-text);
    }

    .transfer-route__name {
        margin-top: 0.25rem;
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.25;
        color: var(--dark-text);
    }

    .transfer-route__meta {
        margin-top: 0.15rem;
        font-size: 0.78rem;
        color: var(--medium-text);
    }

    /* ---- Passage ---- */
    .transfer-route__passage {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
    }

    .transfer-route__pill {
        padding: 0.3rem 0.7rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 600;
        white-space: nowrap;
        background-color: rgba(248, 150, 30, 0.14);
        color: #8a5200;
    }

    .transfer-route__line {
        position: relative;
        display: block;
        width: 100%;
        border-top: 2px dashed rgba(79, 93, 117, 0.35);
    }

    .transfer-route__chevron {
        position: absolute;
        top: 50%;
        right: -0.35rem;
        transform: translateY(-50%);
        font-size: 1rem;
        line-height: 1;
        color: rgba(79, 93, 117, 0.55);
    }

    .transfer-route__state {
        margin-top: 1.1rem;
        padding-top: 0.9rem;
        border-top: 1px solid var(--subtle-border);
        font-size: 0.875rem;
        color: var(--medium-text);
    }

    /* Completed: the goods really did travel, so the line closes up. */
    .transfer-route--completed .transfer-route__pill {
        background-color: rgba(44, 110, 73, 0.12);
        color: var(--primary-dark);
    }

    .transfer-route--completed .transfer-route__line {
        border-top-style: solid;
        border-top-color: var(--primary-light);
    }

    .transfer-route--completed .transfer-route__chevron {
        color: var(--primary);
    }

    /* Cancelled: nothing travelled - drop the arrow entirely. */
    .transfer-route--cancelled .transfer-route__pill {
        background-color: rgba(231, 111, 81, 0.12);
        color: #a03e24;
    }

    .transfer-route--cancelled .transfer-route__line {
        border-top-color: rgba(79, 93, 117, 0.2);
    }

    .transfer-route--cancelled .transfer-route__chevron {
        display: none;
    }

    @media (max-width: 768px) {
        /* Stack the journey top-to-bottom and turn the passage upright. */
        .transfer-route__grid {
            grid-template-columns: minmax(0, 1fr);
            gap: 0.85rem;
        }

        .transfer-route__end--to {
            text-align: left;
            align-items: flex-start;
        }

        .transfer-route__passage {
            flex-direction: row;
            align-items: center;
            gap: 0.75rem;
        }

        .transfer-route__line {
            flex: 1 1 auto;
        }

        /* Mirror the tbody label treatment custom.css applies below 768px */
        .transfer-items tfoot tr {
            display: block;
            border-top: 1px solid #dee2e6;
        }

        .transfer-items tfoot td {
            display: block;
            text-align: right;
            padding: 0.35rem 0.75rem;
        }

        .transfer-items tfoot td:empty {
            display: none;
        }

        .transfer-items tfoot td[data-label]::before {
            content: attr(data-label);
            float: left;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.85em;
        }
    }
</style>
@endpush

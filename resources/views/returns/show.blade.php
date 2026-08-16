@extends('layouts.app')

@php
    use App\Models\StockTransaction;

    $config  = $return->getDisplayConfig();
    $product = $return->product;

    $isCustomer = $return->isCustomerReturn();
    $isVendor   = $return->isVendorReturn();
    $isRetailer = $return->isRetailerReturn();

    $status      = $return->status;
    $isPending   = $status === StockTransaction::STATUS_PENDING;
    $isIssued    = $status === StockTransaction::STATUS_ISSUED;
    $isApproved  = $status === StockTransaction::STATUS_APPROVED;
    $isCompleted = $status === StockTransaction::STATUS_COMPLETED;
    $isRejected  = $status === StockTransaction::STATUS_REJECTED;
    $isCancelled = $status === StockTransaction::STATUS_CANCELLED;

    // Retailer returns open at "issued"; every other type opens at "pending".
    $awaitingApproval = $isPending || ($isIssued && $isRetailer);

    $statusColour = match (true) {
        $isApproved, $isCompleted => 'success',
        $isRejected               => 'danger',
        $isCancelled              => 'secondary',
        $isIssued                 => 'info',
        default                   => 'warning',
    };

    // Stock only moves once a return is approved, and stock_posted_at is what
    // records that it happened - so the page can state it rather than imply it.
    $stockApplied = $return->stock_posted_at !== null;

    // The location the return was recorded against: the warehouse goods leave
    // for a vendor return, the retailer they leave for a retailer return, the
    // place they come back to for a customer return.
    $location     = $return->location;
    $hasLocation  = $location && $location->exists;
    $locationName = $hasLocation ? $location->name : null;
    $locationRole = $hasLocation ? class_basename($location) : 'Location';

    // Retailer returns travel back to whichever warehouse sent the transfer.
    // Resolved once here; the old page repeated this lookup three times.
    $transfer        = $return->stockTransfer;
    $sourceWarehouse = null;
    if ($isRetailer && $transfer && in_array($transfer->from_location_type, ['App\\Models\\Warehouse', \App\Models\Warehouse::class], true)) {
        $sourceWarehouse = \App\Models\Warehouse::find($transfer->from_location_id);
    }

    $bill     = $return->supplierBill;
    $invoice  = $return->invoice;
    $vendor   = $bill?->vendor;
    $customer = $invoice?->customer;

    // Where the goods come from and where they end up, in plain terms.
    [$fromName, $fromRole, $toName, $toRole] = match (true) {
        $isCustomer => [
            $customer->name ?? 'Customer',
            'Customer',
            $locationName ?? 'Return location',
            $locationRole,
        ],
        $isVendor   => [
            $locationName ?? 'Warehouse',
            $locationRole,
            $vendor->name ?? 'Vendor',
            'Vendor',
        ],
        $isRetailer => [
            $locationName ?? 'Retailer',
            $locationRole,
            $sourceWarehouse->name ?? 'Warehouse',
            'Warehouse',
        ],
        default     => ['Source', 'Source', 'Destination', 'Destination'],
    };

    // The ledger effect, as lines against internal locations. A vendor is
    // external, so goods sent back to one leave our books entirely.
    $stockLines = match (true) {
        $isCustomer => [[$locationName ?? 'Return location', $locationRole, $return->quantity]],
        $isVendor   => [[$locationName ?? 'Warehouse', $locationRole, -$return->quantity]],
        $isRetailer => [
            [$locationName ?? 'Retailer', $locationRole, -$return->quantity],
            [$sourceWarehouse->name ?? 'Warehouse', 'Warehouse', $return->quantity],
        ],
        default     => [],
    };

    // The credit or debit note approval raised, if it got that far.
    $note    = \App\Support\ReturnWorkflow::noteFor($return);
    $noteUrl = $note
        ? ($isCustomer ? route('credit-notes.show', $note->id) : route('debit-notes.show', $note->id))
        : null;

    $stages = \App\Support\ReturnWorkflow::forReturn($return);

    // The document this return was raised against.
    [$referenceLabel, $referenceUrl, $referenceValue] = match (true) {
        $isCustomer && $invoice  => ['Invoice', route('invoices.show', $invoice->id), $invoice->invoice_number ?: $invoice->formatted_id],
        $isVendor && $bill       => ['Supplier Bill', route('supplier-bills.show', $bill->id), $bill->formatted_id],
        $isRetailer && $transfer => ['Stock Transfer', route('stock-transfers.warehouse-to-retailer.show', $transfer->id), $transfer->formatted_id],
        default                  => ['Source Document', null, null],
    };

    $originalAmount = match (true) {
        $isCustomer && $invoice => $invoice->total,
        $isVendor && $bill      => $bill->total_amount,
        default                 => null,
    };
@endphp

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Return {{ $return->formatted_id }}</h1>
            <p class="text-muted mb-0">
                <span class="badge bg-{{ $statusColour }}">{{ ucfirst($status) }}</span>
                <span class="ms-2 badge bg-{{ $config['badge_color'] }}">
                    <i class="{{ $config['icon'] }} me-1"></i>{{ $config['label'] }}
                </span>
                <span class="ms-2">{{ optional($return->transaction_date)->format('M d, Y') ?: '—' }}</span>
                <span class="ms-2">·</span>
                <span class="ms-2">{{ number_format($return->quantity) }} {{ Str::plural('unit', $return->quantity) }}
                    of {{ $product->name ?: 'unknown product' }}</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            @if($awaitingApproval)
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#approveReturnModal">
                    <i class="bi bi-check-circle me-1"></i> Approve Return
                </button>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectReturnModal">
                    <i class="bi bi-x-circle me-1"></i> Reject
                </button>
            @elseif($isApproved && ! $isRetailer)
                {{-- Retailer returns end at approved: the stock move is the whole job. --}}
                <form action="{{ route('returns.complete', $return) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-flag-checkered me-1"></i> Mark as Completed
                    </button>
                </form>
            @endif
            @if($noteUrl)
                <a href="{{ $noteUrl }}" class="btn btn-outline-primary">
                    <i class="bi bi-receipt me-1"></i> View {{ $isCustomer ? 'Credit Note' : 'Debit Note' }}
                </a>
            @endif
            <a href="{{ route('returns.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Returns
            </a>
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

    {{-- Where this return sits in its workflow --}}
    <x-workflow-rail :stages="$stages" />

    {{-- Headline figures --}}
    <div class="detail-card mb-4">
        <div class="detail-card__body">
            <div class="detail-figures">
                <div class="detail-figure">
                    <span class="detail-figure__label">Units Returned</span>
                    <span class="detail-figure__value detail-figure__value--lead">
                        {{ number_format($return->quantity) }}
                    </span>
                    <span class="detail-figure__note">{{ $product->sku ? 'SKU ' . $product->sku : 'One product line' }}</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Stock Effect</span>
                    <span class="detail-figure__value">
                        {{ $stockApplied ? 'Applied' : 'Pending' }}
                    </span>
                    <span class="detail-figure__note">
                        @if($stockApplied)
                            {{ optional($return->stock_posted_at)->format('M d, Y') }}
                        @else
                            Moves on approval
                        @endif
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">{{ $isRetailer ? 'Financial Impact' : 'Original Amount' }}</span>
                    <span class="detail-figure__value">
                        @if($isRetailer)
                            None
                        @elseif($originalAmount !== null)
                            ${{ number_format($originalAmount, 2) }}
                        @else
                            —
                        @endif
                    </span>
                    <span class="detail-figure__note">
                        @if($isRetailer)
                            Internal stock move
                        @elseif($referenceValue)
                            On {{ $referenceValue }}
                        @else
                            No source document
                        @endif
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">{{ $isCustomer ? 'Credit Note' : ($isVendor ? 'Debit Note' : 'Recorded') }}</span>
                    <span class="detail-figure__value">
                        @if($isRetailer)
                            {{ optional($return->created_at)->format('M d') ?: '—' }}
                        @elseif($note)
                            {{ ucfirst($note->status) }}
                        @else
                            —
                        @endif
                    </span>
                    <span class="detail-figure__note">
                        @if($isRetailer)
                            {{ optional($return->created_at)->format('Y') }}
                        @elseif($note)
                            {{ $note->formatted_id }}
                        @else
                            Raised on approval
                        @endif
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- The movement: what is going where --}}
    <div class="detail-card mb-4">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-arrow-left-right"></i></span>
            <div>
                <h2 class="detail-card__title">The Movement</h2>
                <p class="detail-card__subtitle">{{ $config['description'] }}</p>
            </div>
        </div>
        <div class="detail-card__body">
            <div class="return-move">
                <div class="return-move__node">
                    <span class="return-move__role">{{ $fromRole }}</span>
                    <span class="return-move__name">{{ $fromName }}</span>
                    <span class="return-move__note">Goods leave here</span>
                </div>
                <div class="return-move__arrow" aria-hidden="true">
                    <span class="return-move__qty">{{ number_format($return->quantity) }}
                        {{ Str::plural('unit', $return->quantity) }}</span>
                    <span class="return-move__line"><i class="bi bi-arrow-right"></i></span>
                    <span class="return-move__product">{{ $product->name ?: 'Unknown product' }}</span>
                </div>
                <div class="return-move__node return-move__node--to">
                    <span class="return-move__role">{{ $toRole }}</span>
                    <span class="return-move__name">{{ $toName }}</span>
                    <span class="return-move__note">
                        {{ $isVendor ? 'Leaves our inventory' : 'Goods arrive here' }}
                    </span>
                </div>
            </div>

            {{-- The same movement as ledger lines --}}
            @if($stockLines)
                <div class="return-effect mt-4">
                    <span class="return-effect__title">
                        Effect on inventory
                        <span class="badge bg-{{ $stockApplied ? 'success' : 'secondary' }} ms-2">
                            {{ $stockApplied ? 'Applied' : 'Not yet applied' }}
                        </span>
                    </span>
                    @foreach($stockLines as [$lineName, $lineRole, $delta])
                        <div class="return-effect__line">
                            <span class="return-effect__where">
                                {{ $lineName }}
                                <span class="text-muted small">{{ $lineRole }}</span>
                            </span>
                            <span class="return-effect__delta return-effect__delta--{{ $delta >= 0 ? 'up' : 'down' }}">
                                {{ $delta >= 0 ? '+' : '−' }}{{ number_format(abs($delta)) }}
                            </span>
                        </div>
                    @endforeach
                    @if($isVendor)
                        <p class="return-effect__foot">
                            The vendor is outside our inventory, so nothing is recorded against them —
                            the goods simply leave {{ $fromName }}.
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Status and what happens next --}}
    <div class="detail-card mb-4">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-flag"></i></span>
            <div>
                <h2 class="detail-card__title">Status</h2>
                <p class="detail-card__subtitle">Where this return has reached, and what happens next</p>
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
                @if($awaitingApproval)
                    Nothing has moved yet. Approving this return takes
                    <strong>{{ number_format($return->quantity) }} {{ Str::plural('unit', $return->quantity) }}</strong>
                    of {{ $product->name ?: 'this product' }} out of {{ $fromName }}@if(! $isVendor), into {{ $toName }}@endif.
                    @unless($isRetailer)
                        A {{ $isCustomer ? 'credit' : 'debit' }} note is raised at the same time, which is then
                        posted to the ledger separately.
                    @endunless
                @elseif($isRejected)
                    This return was rejected, so no stock moved and no
                    {{ $isCustomer ? 'credit' : 'debit' }} note was raised.
                    @if($return->rejection_reason)
                        <span class="d-block mt-2 text-muted">Reason: {{ $return->rejection_reason }}</span>
                    @endif
                @elseif($isCancelled)
                    This return was cancelled before approval, so inventory was never touched.
                @elseif($isRetailer)
                    The stock has moved from {{ $fromName }} to {{ $toName }}. Nothing is owed either way,
                    so no note is raised — but a draft journal entry moves the inventory value back with it,
                    ready to be posted from the journal entries screen.
                @elseif($note)
                    The stock has moved and {{ $note->formatted_id }} was raised for
                    ${{ number_format($note->total_amount ?? 0, 2) }}. Posting it to the ledger is done
                    from the note itself.
                @elseif($isApproved || $isCompleted)
                    The stock has moved. No {{ $isCustomer ? 'credit' : 'debit' }} note is recorded against
                    this return — it can be raised from the note screen if one is still needed.
                @else
                    This return is {{ $status }}.
                @endif
            </div>
        </div>
    </div>

    {{-- Details, source document, audit trail --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-box"></i></span>
                    <div>
                        <h2 class="detail-card__title">Returned Product</h2>
                        <p class="detail-card__subtitle">What is coming back</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Product</span>
                        <span class="detail-kv__value">
                            @if($product && $product->exists)
                                <a href="{{ route('products.show', $product) }}">{{ $product->name }}</a>
                            @else
                                <span class="text-muted">Unknown product</span>
                            @endif
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">SKU</span>
                        <span class="detail-kv__value detail-kv__value--muted">{{ $product->sku ?: '—' }}</span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Quantity</span>
                        <span class="detail-kv__value">
                            {{ number_format($return->quantity) }} {{ Str::plural('unit', $return->quantity) }}
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Return Reason</span>
                        <span class="detail-kv__value detail-kv__value--muted">
                            {{ $return->return_reason ? Str::headline($return->return_reason) : 'No reason recorded' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-file-earmark-text"></i></span>
                    <div>
                        <h2 class="detail-card__title">Returned Against</h2>
                        <p class="detail-card__subtitle">The document these goods came in on</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">{{ $referenceLabel }}</span>
                        <span class="detail-kv__value">
                            @if($referenceUrl && $referenceValue)
                                <a href="{{ $referenceUrl }}">{{ $referenceValue }}</a>
                            @else
                                <span class="text-muted">Not available</span>
                            @endif
                        </span>
                    </div>
                    @if($isCustomer && $customer)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Customer</span>
                            <span class="detail-kv__value">{{ $customer->name }}</span>
                        </div>
                    @endif
                    @if($isVendor && $vendor)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Vendor</span>
                            <span class="detail-kv__value">{{ $vendor->name }}</span>
                        </div>
                    @endif
                    @if($originalAmount !== null)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Original Amount</span>
                            <span class="detail-kv__value">${{ number_format($originalAmount, 2) }}</span>
                        </div>
                    @endif
                    <div class="detail-kv">
                        <span class="detail-kv__label">{{ $isCustomer ? 'Credit Note' : ($isVendor ? 'Debit Note' : 'Financial Impact') }}</span>
                        <span class="detail-kv__value">
                            @if($isRetailer)
                                <span class="text-muted">None — internal stock move</span>
                            @elseif($note)
                                <a href="{{ $noteUrl }}">{{ $note->formatted_id }}</a>
                                <span class="text-muted small d-block">
                                    ${{ number_format($note->total_amount ?? 0, 2) }} · {{ ucfirst($note->status) }}
                                </span>
                            @else
                                <span class="text-muted">Raised when the return is approved</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-clock-history"></i></span>
                    <div>
                        <h2 class="detail-card__title">Audit Trail</h2>
                        <p class="detail-card__subtitle">Who moved this return along</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Recorded</span>
                        <span class="detail-kv__value detail-kv__value--muted">
                            {{ optional($return->created_at)->format('M d, Y H:i') ?: '—' }}
                        </span>
                    </div>
                    @if($return->approved_at || $return->approved_by)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Approved</span>
                            <span class="detail-kv__value">
                                {{ optional($return->approved_at)->format('M d, Y H:i') ?: 'Approved' }}
                                <span class="text-muted small d-block">
                                    by {{ $return->approvedBy->name ?? 'Unknown user' }}
                                </span>
                            </span>
                        </div>
                    @endif
                    @if($return->rejected_at || $return->rejected_by)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Rejected</span>
                            <span class="detail-kv__value">
                                {{ optional($return->rejected_at)->format('M d, Y H:i') ?: 'Rejected' }}
                                <span class="text-muted small d-block">
                                    by {{ $return->rejectedBy->name ?? 'Unknown user' }}
                                </span>
                            </span>
                        </div>
                    @endif
                    @if($return->completed_at || $return->completed_by)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Completed</span>
                            <span class="detail-kv__value">
                                {{ optional($return->completed_at)->format('M d, Y H:i') ?: 'Completed' }}
                                <span class="text-muted small d-block">
                                    by {{ $return->completedBy->name ?? 'Unknown user' }}
                                </span>
                            </span>
                        </div>
                    @endif
                    @if($return->cancelled_at || $return->cancelled_by)
                        <div class="detail-kv">
                            <span class="detail-kv__label">Cancelled</span>
                            <span class="detail-kv__value">
                                {{ optional($return->cancelled_at)->format('M d, Y H:i') ?: 'Cancelled' }}
                                <span class="text-muted small d-block">
                                    by {{ $return->cancelledBy->name ?? 'Unknown user' }}
                                </span>
                            </span>
                        </div>
                    @endif
                    <div class="detail-kv">
                        <span class="detail-kv__label">Stock Posted</span>
                        <span class="detail-kv__value {{ $stockApplied ? '' : 'detail-kv__value--muted' }}">
                            @if($stockApplied)
                                {{ optional($return->stock_posted_at)->format('M d, Y H:i') }}
                            @else
                                Not yet — moves on approval
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($isPending)
        <div class="d-flex justify-content-end d-print-none">
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal">
                <i class="bi bi-trash me-1"></i> Delete this return
            </button>
        </div>
    @endif

    {{-- Approve: the one action that moves stock --}}
    @if($awaitingApproval)
        <div class="modal fade" id="approveReturnModal" tabindex="-1" aria-labelledby="approveReturnModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="approveReturnModalLabel">Approve Return</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-{{ $statusColour }}">{{ ucfirst($status) }}</span>
                            <i class="bi bi-arrow-right text-muted"></i>
                            <span class="badge bg-success">Approved</span>
                        </div>
                        <p class="mb-2">Approving {{ $return->formatted_id }} will:</p>
                        <ul class="mb-3 ps-3">
                            @foreach($stockLines as [$lineName, $lineRole, $delta])
                                <li class="mb-1">
                                    {{ $delta >= 0 ? 'Add' : 'Remove' }}
                                    <strong>{{ number_format(abs($delta)) }}
                                        {{ Str::plural('unit', abs($delta)) }}</strong>
                                    {{ $delta >= 0 ? 'to' : 'from' }} <strong>{{ $lineName }}</strong>
                                </li>
                            @endforeach
                            @unless($isRetailer)
                                <li class="mb-1">
                                    Raise a {{ $isCustomer ? 'credit' : 'debit' }} note against
                                    {{ $referenceValue ?: 'the source document' }}
                                </li>
                            @endunless
                        </ul>
                        <div class="detail-panel mb-0">
                            <span class="d-block mb-1">
                                <strong>What does not happen yet:</strong>
                                @if($isRetailer)
                                    nothing further — a retailer return is an internal stock move with no
                                    financial side.
                                @else
                                    the {{ $isCustomer ? 'credit' : 'debit' }} note is raised but not posted.
                                    Posting it to the ledger is a separate step on the note itself.
                                @endif
                            </span>
                            <span class="text-muted small">
                                Stock moves once and only once. An approved return cannot be returned to
                                {{ ucfirst($status) }} from this page.
                            </span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form action="{{ route('returns.approve', $return) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i> Approve Return
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Reject: closes the return without touching stock --}}
        <div class="modal fade" id="rejectReturnModal" tabindex="-1" aria-labelledby="rejectReturnModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('returns.reject', $return) }}" method="POST">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="rejectReturnModalLabel">Reject Return</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3">
                                Rejecting {{ $return->formatted_id }} closes it without moving any stock and
                                without raising a {{ $isCustomer ? 'credit' : 'debit' }} note. The units go
                                back to what can still be returned against
                                {{ $referenceValue ?: 'the source document' }}.
                            </p>
                            <label for="rejection_reason" class="form-label">Reason for rejection</label>
                            <textarea class="form-control @error('rejection_reason') is-invalid @enderror"
                                      id="rejection_reason" name="rejection_reason" rows="3" maxlength="500" required
                                      placeholder="e.g. Outside the returns window">{{ old('rejection_reason') }}</textarea>
                            @error('rejection_reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Recorded against the return so the decision can be traced.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-x-circle me-1"></i> Reject Return
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($isPending)
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Delete Return</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">
                            Delete return <strong>{{ $return->formatted_id }}</strong> permanently?
                        </p>
                        <div class="detail-panel mb-0">
                            <span class="d-block mb-1">
                                No stock has moved for this return, so there is nothing to reverse — the
                                record is simply removed.
                            </span>
                            <span class="text-muted small">This cannot be undone.</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form action="{{ route('returns.destroy', $return) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash me-1"></i> Delete Return
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
    /* ---- The movement strip -------------------------------------------
       A return is goods travelling between two places, so the page leads
       with that rather than burying it in a key/value table. */
    .return-move {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: stretch;
        gap: 1rem;
    }

    .return-move__node {
        padding: 1rem 1.1rem;
        border: 1px solid var(--subtle-border);
        border-radius: 8px;
        background-color: #f5f8f6;
    }

    .return-move__node--to {
        border-color: rgba(44, 110, 73, 0.35);
    }

    .return-move__role {
        display: block;
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--medium-text);
    }

    .return-move__name {
        display: block;
        margin-top: 0.25rem;
        font-size: 1.125rem;
        font-weight: 600;
        line-height: 1.3;
        color: var(--dark-text);
    }

    .return-move__note {
        display: block;
        margin-top: 0.2rem;
        font-size: 0.78rem;
        color: var(--medium-text);
    }

    .return-move__arrow {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.15rem;
        min-width: 8rem;
        padding: 0 0.5rem;
        text-align: center;
    }

    .return-move__qty {
        font-size: 0.875rem;
        font-weight: 700;
        color: var(--primary);
    }

    .return-move__line {
        display: block;
        width: 100%;
        border-top: 2px dashed var(--border-color);
        line-height: 0;
        color: var(--medium-text);
    }

    .return-move__line i {
        position: relative;
        top: -0.62em;
        background-color: var(--light-panel);
        padding: 0 0.35rem;
        font-size: 1.1rem;
    }

    .return-move__product {
        font-size: 0.75rem;
        color: var(--medium-text);
        word-break: break-word;
    }

    /* ---- Inventory effect --------------------------------------------- */
    .return-effect {
        padding-top: 1rem;
        border-top: 1px solid var(--subtle-border);
    }

    .return-effect__title {
        display: block;
        margin-bottom: 0.6rem;
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--medium-text);
    }

    .return-effect__line {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.5rem 0;
    }

    .return-effect__line + .return-effect__line {
        border-top: 1px solid var(--subtle-border);
    }

    .return-effect__where {
        font-size: 0.9375rem;
        color: var(--dark-text);
    }

    .return-effect__where .small {
        display: block;
        font-size: 0.75rem;
    }

    .return-effect__delta {
        font-size: 1.125rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .return-effect__delta--up {
        color: #1a7f4b;
    }

    .return-effect__delta--down {
        color: #b02a37;
    }

    .return-effect__foot {
        margin: 0.75rem 0 0;
        font-size: 0.78rem;
        color: var(--medium-text);
    }

    @media (max-width: 768px) {
        /* Stack the two ends and turn the connector to point down */
        .return-move {
            grid-template-columns: 1fr;
            gap: 0.5rem;
        }

        .return-move__arrow {
            flex-direction: row;
            flex-wrap: wrap;
            justify-content: flex-start;
            gap: 0.5rem;
            min-width: 0;
            padding: 0.25rem 0.1rem;
            text-align: left;
        }

        .return-move__line {
            width: auto;
            border-top: 0;
        }

        .return-move__line i {
            top: 0;
            padding: 0;
            transform: rotate(90deg);
            display: inline-block;
        }
    }
</style>
@endpush

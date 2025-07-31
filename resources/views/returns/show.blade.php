@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <x-breadcrumb :items="[
        ['label' => 'Returns', 'url' => route('returns.index')],
        ['label' => $return->formatted_id, 'url' => '#']
    ]" />
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="bi bi-arrow-return-left me-2"></i>
            Return Transaction - {{ $return->formatted_id }}
        </h1>
        <div>
            <a href="{{ route('returns.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Returns
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Return Details -->
        <div class="col-lg-8">
            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Return Details
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Transaction ID:</strong></td>
                                    <td>{{ $return->formatted_id }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Return Type:</strong></td>
                                    <td>
                                        @php $config = $return->getDisplayConfig(); @endphp
                                        <span class="badge bg-{{ $config['badge_color'] }}">
                                            <i class="{{ $config['icon'] }} me-1"></i>
                                            {{ $config['label'] }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Product:</strong></td>
                                    <td>
                                        <strong>{{ $return->product->name }}</strong>
                                        <br><small class="text-muted">{{ $return->product->sku }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Quantity:</strong></td>
                                    <td>
                                        <strong>{{ number_format($return->quantity) }}</strong>
                                        <br><small class="text-{{ $return->direction === 'inbound' ? 'success' : 'danger' }}">
                                            {{ $return->getEffectDescription() }}
                                        </small>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Return Date:</strong></td>
                                    <td>{{ $return->transaction_date->format('M d, Y') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Created:</strong></td>
                                    <td>{{ $return->created_at->format('M d, Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Destination:</strong></td>
                                    <td>
                                        @if($return->transaction_type === 'retailer_return')
                                            @php
                                                $warehouse = null;
                                                if ($return->stockTransfer && $return->stockTransfer->from_location_type === 'App\\Models\\Warehouse') {
                                                    $warehouse = \App\Models\Warehouse::find($return->stockTransfer->from_location_id);
                                                }
                                            @endphp
                                            @if($warehouse)
                                                <span class="badge bg-info">{{ $warehouse->name }}</span>
                                                <br><small class="text-muted">Warehouse</small>
                                            @else
                                                <span class="text-muted">Unknown Warehouse</span>
                                            @endif
                                        @elseif($return->location)
                                            <span class="badge bg-secondary">{{ $return->location->name }}</span>
                                            <br><small class="text-muted">{{ class_basename($return->location) }}</small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Direction:</strong></td>
                                    <td>
                                        <span class="badge bg-{{ $return->direction === 'inbound' ? 'success' : 'danger' }}">
                                            {{ ucfirst($return->direction) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        @php
                                            $statusColors = [
                                                'pending' => 'warning',
                                                'issued' => 'info',
                                                'approved' => 'success',
                                                'completed' => 'info',
                                                'cancelled' => 'secondary'
                                            ];
                                            $statusColor = $statusColors[$return->status] ?? 'secondary';
                                        @endphp
                                        <span class="badge bg-{{ $statusColor }}">
                                            {{ ucfirst($return->status) }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    @if($return->notes)
                    <div class="mt-3">
                        <strong>Notes:</strong>
                        <p class="text-muted mb-0">{{ $return->notes }}</p>
                    </div>
                    @endif
                    
                    @if($return->transaction_type === 'retailer_return')
                    <div class="mt-3">
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Retailer Return Information</h6>
                            <p class="mb-2">This is an <strong>internal stock transaction</strong> that moves inventory from a retailer back to the warehouse.</p>
                            <ul class="mb-0">
                                <li><strong>No Financial Impact:</strong> No journal entries are created</li>
                                <li><strong>Stock Adjustment Only:</strong> Inventory is transferred between internal locations</li>
                                <li><strong>Source:</strong> Retailer location (decrease)</li>
                                <li><strong>Destination:</strong> Warehouse location (increase)</li>
                            </ul>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Source Information -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-arrow-left me-2"></i>Source Information
                    </h5>
                </div>
                <div class="card-body">
                    @php 
                        try {
                            $sourceInfo = $return->getReturnSourceInfo();
                        } catch (Exception $e) {
                            $sourceInfo = [];
                        }
                    @endphp
                    @if(is_array($sourceInfo) && !empty($sourceInfo))
                        <div class="row g-3">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Source Type:</strong></td>
                                        <td>
                                            @switch($return->transaction_type)
                                                @case('customer_return')
                                                    <span class="badge bg-danger">Customer Return</span>
                                                    @break
                                                @case('vendor_return')
                                                    <span class="badge bg-info">Warehouse</span>
                                                    @break
                                                @case('retailer_return')
                                                    <span class="badge bg-warning">Retailer</span>
                                                    @break
                                                @default
                                                    <span class="badge bg-secondary">Unknown</span>
                                            @endswitch
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Source:</strong></td>
                                        <td>
                                            @if($return->transaction_type === 'retailer_return')
                                                @if($return->location)
                                                    <span class="badge bg-warning">{{ $return->location->name }}</span>
                                                    <br><small class="text-muted">Retailer</small>
                                                @else
                                                    <span class="text-muted">Unknown Retailer</span>
                                                @endif
                                            @else
                                                {{ $sourceInfo['source'] ?? 'Unknown' }}
                                            @endif
                                        </td>
                                    </tr>
                                    @if($return->transaction_type === 'vendor_return' && $return->reference)
                                        <tr>
                                            <td><strong>Destination:</strong></td>
                                            <td>
                                                @php
                                                    $vendor = $return->reference->vendor ?? null;
                                                @endphp
                                                <span class="badge bg-warning">{{ $vendor ? $vendor->name : 'Unknown Vendor' }}</span>
                                            </td>
                                        </tr>
                                    @endif
                                    @if($return->reference)
                                        <tr>
                                            <td><strong>Reference Document:</strong></td>
                                            <td>
                                                @if($return->reference_type === 'App\Models\Invoice')
                                                    <a href="{{ route('invoices.show', $return->reference) }}" class="text-decoration-none">
                                                        {{ $return->reference->invoice_number ?? 'Invoice #' . ($return->reference->id ?? 'N/A') }}
                                                    </a>
                                                @elseif($return->reference_type === 'App\Models\SupplierBill')
                                                    <a href="{{ route('supplier-bills.show', $return->reference) }}" class="text-decoration-none">
                                                        {{ $return->reference->formatted_id ?? 'Bill #' . ($return->reference->id ?? 'N/A') }}
                                                    </a>
                                                @elseif($return->reference_type === 'App\Models\StockTransfer')
                                                    <a href="{{ route('stock-transfers.warehouse-to-retailer.show', $return->reference) }}" class="text-decoration-none">
                                                        {{ $return->reference->formatted_id ?? 'Transfer #' . ($return->reference->id ?? 'N/A') }}
                                                    </a>
                                                @else
                                                    {{ $sourceInfo['reference'] ?? 'Unknown' }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Reference Date:</strong></td>
                                        <td>{{ $sourceInfo['date'] ?? 'Unknown' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Return Reason:</strong></td>
                                        <td>
                                            @if($return->return_reason)
                                                <span class="text-muted">{{ $return->return_reason }}</span>
                                            @else
                                                <span class="text-muted">No reason specified</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if($return->reference)
                                        <tr>
                                            <td><strong>Original Amount:</strong></td>
                                            <td>
                                                @if($return->reference_type === 'App\Models\Invoice')
                                                    <span class="text-success">${{ number_format($return->reference->total ?? 0, 2) }}</span>
                                                @elseif($return->reference_type === 'App\Models\SupplierBill')
                                                    <span class="text-success">${{ number_format($return->reference->total_amount ?? 0, 2) }}</span>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                        </div>
                    @else
                        <p class="text-muted mb-0">No source information available.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Stock Impact -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-box me-2"></i>Stock Impact
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <div class="display-4 text-{{ $return->direction === 'inbound' ? 'success' : 'danger' }}">
                            {{ $return->direction === 'inbound' ? '+' : '-' }}{{ number_format($return->quantity) }}
                        </div>
                        <p class="text-muted mb-0">
                            {{ $return->location->name ?? 'Unknown Location' }}
                        </p>
                        <small class="text-muted">
                            {{ $return->product->name }}
                        </small>
                    </div>
                </div>
            </div>

            <!-- Status Actions -->
            @if($return->status === 'pending')
            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-check-circle me-2"></i>Approval Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Pending Approval:</strong> This return requires approval before stock adjustments and journal entries are processed.
                    </div>
                    <div class="d-grid gap-2">
                        <form action="{{ route('returns.approve', $return) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i> Approve Return
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @elseif($return->status === 'issued' && $return->transaction_type === 'retailer_return')
            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-check-circle me-2"></i>Retailer Return Approval
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Retailer Return Issued:</strong> This is an internal stock transaction. No financial journal entry is required. Only stock adjustment is needed.
                    </div>
                    
                    <!-- Enhanced Retailer Return Information -->
                    <div class="alert alert-warning">
                        <h6 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Retailer Return Details</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="mb-0">
                                    <li><strong>Source Location:</strong> {{ $return->location->name ?? 'Unknown Retailer' }}</li>
                                    <li><strong>Transaction Type:</strong> Internal Stock Transfer</li>
                                    <li><strong>Stock Movement:</strong> Decrease from retailer, increase to warehouse</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="mb-0">
                                    <li><strong>Destination:</strong> 
                                        @php
                                            $warehouse = null;
                                            if ($return->stockTransfer && $return->stockTransfer->from_location_type === 'App\\Models\\Warehouse') {
                                                $warehouse = \App\Models\Warehouse::find($return->stockTransfer->from_location_id);
                                            }
                                        @endphp
                                        {{ $warehouse ? $warehouse->name : 'Unknown Warehouse' }}
                                    </li>
                                    <li><strong>Financial Impact:</strong> None (internal transaction)</li>
                                    <li><strong>Status:</strong> Issued - Ready for Approval</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stock Adjustment Preview -->
                    <div class="alert alert-success">
                        <h6 class="alert-heading"><i class="bi bi-box me-2"></i>Stock Adjustment Preview</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="text-center p-3 border rounded">
                                    <i class="bi bi-arrow-down-circle text-danger fs-1"></i>
                                    <h6 class="mt-2 mb-1">Decrease From</h6>
                                    <strong>{{ $return->location->name ?? 'Retailer' }}</strong>
                                    <div class="text-danger fw-bold">-{{ number_format($return->quantity) }} units</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-center p-3 border rounded">
                                    <i class="bi bi-arrow-up-circle text-success fs-1"></i>
                                    <h6 class="mt-2 mb-1">Increase To</h6>
                                    <strong>{{ $warehouse ? $warehouse->name : 'Warehouse' }}</strong>
                                    <div class="text-success fw-bold">+{{ number_format($return->quantity) }} units</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <form action="{{ route('returns.approve', $return) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle me-1"></i> Approve Stock Adjustment
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @elseif($return->status === 'approved')
            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-check-circle me-2"></i>Completion Actions
                    </h5>
                </div>
                <div class="card-body">
                    @if($return->transaction_type === 'retailer_return')
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Retailer Return Approved:</strong> Stock has been successfully adjusted.
                        </div>
                        
                        <!-- Enhanced Stock Adjustment Summary -->
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="bi bi-box me-2"></i>Stock Adjustment Summary</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="text-center p-3 border rounded bg-light">
                                        <i class="bi bi-arrow-down-circle text-danger fs-1"></i>
                                        <h6 class="mt-2 mb-1">Decreased From</h6>
                                        <strong>{{ $return->location->name ?? 'Unknown Retailer' }}</strong>
                                        <div class="text-danger fw-bold">-{{ number_format($return->quantity) }} units</div>
                                        <small class="text-muted">{{ $return->product->name }}</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-center p-3 border rounded bg-light">
                                        <i class="bi bi-arrow-up-circle text-success fs-1"></i>
                                        <h6 class="mt-2 mb-1">Increased To</h6>
                                        @php
                                            $warehouse = null;
                                            if ($return->stockTransfer && $return->stockTransfer->from_location_type === 'App\\Models\\Warehouse') {
                                                $warehouse = \App\Models\Warehouse::find($return->stockTransfer->from_location_id);
                                            }
                                        @endphp
                                        <strong>{{ $warehouse ? $warehouse->name : 'Unknown Warehouse' }}</strong>
                                        <div class="text-success fw-bold">+{{ number_format($return->quantity) }} units</div>
                                        <small class="text-muted">{{ $return->product->name }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <ul class="mb-0">
                                    <li><strong>Transaction Type:</strong> Internal Stock Transfer</li>
                                    <li><strong>Financial Impact:</strong> None (internal transaction)</li>
                                    <li><strong>Status:</strong> Stock adjustment completed successfully</li>
                                    <li><strong>Reference:</strong> 
                                        @if($return->stockTransfer)
                                            <a href="{{ route('stock-transfers.warehouse-to-retailer.show', $return->stockTransfer) }}" class="text-decoration-none">
                                                {{ $return->stockTransfer->formatted_id ?? 'Stock Transfer #' . ($return->stockTransfer->id ?? 'N/A') }}
                                            </a>
                                        @else
                                            Unknown Reference
                                        @endif
                                    </li>
                                </ul>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Approved:</strong> Stock adjustments and journal entries have been processed. You can now mark this return as completed.
                        </div>
                    @endif
                    
                    @if($return->transaction_type !== 'retailer_return')
                    <div class="d-grid gap-2">
                        <form action="{{ route('returns.complete', $return) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to mark this return as completed?')">
                                <i class="bi bi-flag-checkered me-1"></i> Mark as Completed
                            </button>
                        </form>
                    </div>
                    @endif
                </div>
            </div>
            @elseif($return->status === 'completed')
            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-check-circle me-2"></i>Status
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        <strong>Completed:</strong> This return has been fully processed and completed.
                    </div>
                </div>
            </div>
            @endif

            <!-- Quick Actions -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('returns.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-list me-1"></i> View All Returns
                        </a>
                        <a href="{{ route('returns.create') }}" class="btn btn-outline-success">
                            <i class="bi bi-plus-circle me-1"></i> Create New Return
                        </a>
                        @if($return->product)
                        <a href="{{ route('products.show', $return->product) }}" class="btn btn-outline-info">
                            <i class="bi bi-box me-1"></i> View Product
                        </a>
                        @endif
                        @if($return->status === 'pending')
                        <button type="button" class="btn btn-outline-danger" 
                                onclick="deleteReturn({{ $return->id }}, '{{ $return->formatted_id }}')">
                            <i class="bi bi-trash me-1"></i> Delete Return
                        </button>
                        @endif

                    </div>
                </div>
            </div>

            <!-- Related Links -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-link-45deg me-2"></i>Related Links
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        @if($return->invoice)
                        <li class="mb-2">
                            <a href="{{ route('invoices.show', $return->invoice) }}" class="text-decoration-none">
                                <i class="bi bi-receipt me-1"></i> View Invoice
                            </a>
                        </li>
                        @endif
                        @if($return->supplierBill)
                        <li class="mb-2">
                            <a href="{{ route('supplier-bills.show', $return->supplierBill) }}" class="text-decoration-none">
                                <i class="bi bi-file-text me-1"></i> View Supplier Bill
                            </a>
                        </li>
                        @endif
                        @if($return->stockTransfer)
                        <li class="mb-2">
                            <a href="{{ route('stock-transfers.warehouse-to-retailer.show', $return->stockTransfer) }}" class="text-decoration-none">
                                <i class="bi bi-arrow-left-right me-1"></i> View Stock Transfer
                            </a>
                        </li>
                        @endif
                        @if($return->location)
                        <li class="mb-2">
                            <a href="{{ route('stock-locations.show', $return->location) }}" class="text-decoration-none">
                                <i class="bi bi-geo-alt me-1"></i> View Location
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete return transaction <strong id="deleteTransactionId"></strong>?</p>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action will:
                    <ul class="mb-0 mt-2">
                        <li>Reverse the stock adjustment</li>
                        <li>Permanently delete the transaction record</li>
                        <li>This action cannot be undone</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
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

@endsection

@push('styles')
<style>
/* Subtle improvements for Return Transaction page */
.card {
    transition: box-shadow 0.2s ease;
}

.card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
}

.table-borderless td {
    padding: 0.75rem 0.5rem;
    vertical-align: top;
}

.table-borderless td:first-child {
    font-weight: 600;
    color: var(--dark-text);
    min-width: 120px;
}

@media (max-width: 768px) {
    .table-borderless td {
        padding: 0.5rem 0.25rem;
    }
    
    .table-borderless td:first-child {
        min-width: 100px;
        font-size: 0.9rem;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .display-4 {
        font-size: 2.5rem;
    }
}

@media (max-width: 576px) {
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 1rem;
    }
    
    h1 {
        font-size: 1.5rem;
    }
    
    .display-4 {
        font-size: 2rem;
    }
    
    .card-body {
        padding: 0.75rem;
    }
}
</style>
@endpush

@push('scripts')
<script>
function deleteReturn(id, formattedId) {
    document.getElementById('deleteTransactionId').textContent = formattedId;
    document.getElementById('deleteForm').action = `/returns/${id}`;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}
</script>
@endpush 
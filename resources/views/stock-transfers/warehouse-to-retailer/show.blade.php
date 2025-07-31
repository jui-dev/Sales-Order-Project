@extends('layouts.app')

@section('styles')
<style>
    /* Enhanced styling for warehouse to retailer transfer details page */
    .card, .card-body, .card-header, .table, .table td, .table th, 
    .btn, .badge, .text-white, .bg-primary, .bg-success, .bg-info, .bg-warning {
        color: #212529 !important;
    }
    
    .info-card {
        border: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        background-color: #ffffff !important;
        border-radius: 12px;
        overflow: hidden;
    }
    
    .info-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    
    .info-card .card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
        color: #212529 !important;
        border-bottom: 1px solid #dee2e6;
        padding: 1rem 1.5rem;
    }
    
    .info-card .card-body {
        background-color: #ffffff !important;
        color: #212529 !important;
        padding: 1.5rem;
    }
    
    .status-badge {
        font-size: 0.875rem;
        font-weight: 500;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-pending { 
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        color: #856404 !important;
        border: 1px solid #ffeaa7;
    }
    
    .status-completed { 
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724 !important;
        border: 1px solid #c3e6cb;
    }
    
    .status-cancelled { 
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24 !important;
        border: 1px solid #f5c6cb;
    }
    
    .status-in_progress { 
        background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
        color: #0c5460 !important;
        border: 1px solid #bee5eb;
    }
    
    .table-modern {
        border: none;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .table-modern th {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: none;
        font-weight: 600;
        color: #495057 !important;
        font-size: 0.875rem;
        padding: 1rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .table-modern td {
        border: none;
        border-bottom: 1px solid #f1f3f4;
        vertical-align: middle;
        color: #212529 !important;
        padding: 1rem;
    }
    
    .table-modern tbody tr:hover {
        background-color: #f8f9fa;
        transition: background-color 0.2s ease;
    }
    
    .text-subtle { 
        color: #6c757d !important; 
        font-size: 0.875rem; 
    }
    
    .text-primary { color: #0d6efd !important; }
    .text-success { color: #198754 !important; }
    .text-info { color: #0dcaf0 !important; }
    .text-warning { color: #ffc107 !important; }
    .text-dark { color: #212529 !important; }
    .text-muted { color: #6c757d !important; }
    
    .action-buttons {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    
    .btn-modern {
        border-radius: 8px;
        font-weight: 500;
        padding: 0.75rem 1.5rem;
        transition: all 0.3s ease;
        border: none;
    }
    
    .btn-modern:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .stats-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border: 1px solid #dee2e6;
        border-radius: 12px;
        padding: 1.5rem;
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    
    .stats-number {
        font-size: 2rem;
        font-weight: 700;
        color: #0d6efd;
        margin-bottom: 0.5rem;
    }
    
    .stats-label {
        color: #6c757d;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .form-control-modern {
        border-radius: 8px;
        border: 1px solid #dee2e6;
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
    }
    
    .form-control-modern:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }
    
    /* Simple button styling to match system theme */
    .btn {
        border-radius: 4px;
        font-weight: 500;
        padding: 0.5rem 1rem;
        transition: all 0.2s ease;
    }
    
    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
        box-shadow: none !important;
    }
</style>
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

<div class="container-fluid">
    <!-- Enhanced Header -->
    <div class="card info-card mb-4">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-building-arrow-right" style="font-size: 2.5rem; color: #0d6efd;"></i>
                        </div>
                        <div>
                            <h3 class="mb-1 text-dark">Transfer Details</h3>
                            <h5 class="text-primary mb-0">{{ $pickingList->picking_number }}</h5>
                            <small class="text-muted">Warehouse to Retailer Transfer</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex flex-column align-items-end">
                        <span class="status-badge status-{{ $pickingList->status ?? 'pending' }} mb-2">
                            <i class="bi bi-{{ $pickingList->status === 'completed' ? 'check-circle' : ($pickingList->status === 'cancelled' ? 'x-circle' : 'clock') }} me-1"></i>
                            {{ ucfirst($pickingList->status ?? 'Pending') }}
                        </span>
                        <small class="text-muted">
                            Created: {{ $pickingList->created_at->format('M d, Y H:i') }}
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Transfer Information Card -->
            <div class="card info-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0 text-dark">
                        <i class="bi bi-info-circle me-2"></i>Transfer Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small text-uppercase">Transfer Number</label>
                                <div class="text-dark fw-bold">#{{ $pickingList->picking_number }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="text-muted small text-uppercase">From Location</label>
                                <div class="text-dark">{{ $pickingList->fromLocation->name ?? 'N/A' }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="text-muted small text-uppercase">To Location</label>
                                <div class="text-dark">{{ $pickingList->toLocation->name ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small text-uppercase">Status</label>
                                <div>
                                    <span class="status-badge status-{{ $pickingList->status ?? 'pending' }}">
                                        <i class="bi bi-{{ $pickingList->status === 'completed' ? 'check-circle' : ($pickingList->status === 'cancelled' ? 'x-circle' : 'clock') }} me-1"></i>
                                        {{ ucfirst($pickingList->status ?? 'Pending') }}
                                    </span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="text-muted small text-uppercase">Created Date</label>
                                <div class="text-dark">{{ $pickingList->created_at->format('M d, Y H:i') }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="text-muted small text-uppercase">Picking Date</label>
                                <div class="text-dark">{{ $pickingList->picking_date->format('M d, Y H:i') }}</div>
                            </div>
                            @if($pickingList->completed_at)
                            <div class="mb-3">
                                <label class="text-muted small text-uppercase">Completed Date</label>
                                <div class="text-success">{{ $pickingList->completed_at->format('M d, Y H:i') }}</div>
                            </div>
                            @endif
                        </div>
                    </div>
                    
                    @if($pickingList->notes)
                    <div class="mt-4">
                        <label class="text-muted small text-uppercase">Notes</label>
                        <div class="text-subtle mt-1">{{ $pickingList->notes }}</div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Transfer Items Card -->
            <div class="card info-card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-dark">
                            <i class="bi bi-list-check me-2"></i>Transfer Items
                        </h5>
                        <span class="badge bg-primary">{{ $pickingList->pickingItems->count() }} items</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    @if($pickingList->status === 'pending')
                        <!-- Editable form for pending transfers -->
                        <form action="{{ route('stock-transfers.warehouse-to-retailer.process', $pickingList) }}" method="POST">
                            @csrf
                            <div class="table-responsive">
                                <table class="table table-modern mb-0">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Requested Qty</th>
                                            <th>Transfer Qty</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pickingList->pickingItems as $item)
                                        <tr>
                                            <td>
                                                <div class="text-dark fw-bold">{{ $item->product->name }}</div>
                                                <div class="text-subtle">SKU: {{ $item->product->sku }}</div>
                                            </td>
                                            <td>
                                                <span class="text-dark fw-bold">{{ $item->quantity_requested }}</span>
                                            </td>
                                            <td>
                                                <input type="hidden" name="items[{{ $loop->index }}][picking_item_id]" value="{{ $item->id }}">
                                                <input type="number" 
                                                       name="items[{{ $loop->index }}][quantity_picked]" 
                                                       class="form-control form-control-modern" 
                                                       value="{{ $item->quantity_requested }}" 
                                                       min="0" 
                                                       max="{{ $item->quantity_requested }}" 
                                                       required>
                                            </td>
                                            <td>
                                                <span class="status-badge status-{{ $item->status ?? 'pending' }}">
                                                    {{ ucfirst($item->status ?? 'Pending') }}
                                                </span>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="p-3">
                                <div class="mb-3">
                                    <label for="notes" class="text-muted small text-uppercase">Processing Notes</label>
                                    <textarea name="notes" id="notes" class="form-control form-control-modern" rows="3" 
                                              placeholder="Optional notes for this transfer processing..."></textarea>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-check-circle me-1"></i>Complete Transfer
                                    </button>
                                </div>
                            </div>
                        </form>
                    @else
                        <!-- Read-only view for completed/cancelled transfers -->
                        <div class="table-responsive">
                            <table class="table table-modern mb-0">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Requested Qty</th>
                                        <th>Transferred Qty</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pickingList->pickingItems as $item)
                                    <tr>
                                        <td>
                                            <div class="text-dark fw-bold">{{ $item->product->name }}</div>
                                            <div class="text-subtle">SKU: {{ $item->product->sku }}</div>
                                        </td>
                                        <td>
                                            <span class="text-dark fw-bold">{{ $item->quantity_requested }}</span>
                                        </td>
                                        <td>
                                            <span class="text-dark fw-bold">{{ $item->quantity_picked ?? 0 }}</span>
                                        </td>
                                        <td>
                                            @if($item->status === 'completed')
                                                <span class="status-badge status-completed">Completed</span>
                                            @elseif($item->status === 'partial')
                                                <span class="status-badge status-in_progress">Partial</span>
                                            @elseif($item->status === 'cancelled')
                                                <span class="status-badge status-cancelled">Cancelled</span>
                                            @else
                                                <span class="status-badge status-{{ $item->status ?? 'pending' }}">
                                                    {{ ucfirst($item->status ?? 'Pending') }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- From Location Information Card -->
            <div class="card info-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0 text-dark">
                        <i class="bi bi-building me-2"></i>From Location
                    </h5>
                </div>
                <div class="card-body">
                    @if($pickingList->fromLocation)
                        <div class="text-center mb-3">
                            <i class="bi bi-building" style="font-size: 3rem; color: #0d6efd;"></i>
                        </div>
                        <h6 class="text-dark text-center mb-3">{{ $pickingList->fromLocation->name }}</h6>
                        @if($pickingList->fromLocation->address)
                            <p class="text-subtle text-center mb-3">
                                <i class="bi bi-geo-alt me-1"></i>{{ $pickingList->fromLocation->address }}
                            </p>
                        @endif
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                            <h6 class="text-muted mt-3">No From Location</h6>
                            <p class="text-muted">Source location not available.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- To Location Information Card -->
            <div class="card info-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0 text-dark">
                        <i class="bi bi-shop me-2"></i>To Location
                    </h5>
                </div>
                <div class="card-body">
                    @if($pickingList->toLocation)
                        <div class="text-center mb-3">
                            <i class="bi bi-shop" style="font-size: 3rem; color: #0d6efd;"></i>
                        </div>
                        <h6 class="text-dark text-center mb-3">{{ $pickingList->toLocation->name }}</h6>
                        @if($pickingList->toLocation->address)
                            <p class="text-subtle text-center mb-3">
                                <i class="bi bi-geo-alt me-1"></i>{{ $pickingList->toLocation->address }}
                            </p>
                        @endif
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                            <h6 class="text-muted mt-3">No To Location</h6>
                            <p class="text-muted">Destination location not available.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Transfer Summary Card -->
            <div class="card info-card">
                <div class="card-header">
                    <h5 class="mb-0 text-dark">
                        <i class="bi bi-info-circle me-2"></i>Transfer Summary
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="stats-card">
                                <div class="stats-number">
                                    {{ $pickingList->pickingItems->count() }}
                                </div>
                                <div class="stats-label">Total Items</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stats-card">
                                <div class="stats-number text-success">
                                    {{ $pickingList->pickingItems->where('status', 'completed')->count() }}
                                </div>
                                <div class="stats-label">Completed</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <div class="mb-2">
                            <label class="text-muted small text-uppercase">Reference Type</label>
                            <div class="text-dark">{{ ucfirst(str_replace('_', ' ', $pickingList->reference_type ?? 'N/A')) }}</div>
                        </div>
                        @if($pickingList->picked_by)
                        <div class="mb-2">
                            <label class="text-muted small text-uppercase">Picked By</label>
                            <div class="text-dark">{{ $pickingList->picked_by }}</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Action Buttons -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card info-card">
                <div class="card-body">
                    <div class="action-buttons justify-content-between">
                        <div class="d-flex gap-2">
                            <a href="{{ route('stock-transfers.warehouse-to-retailer') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Transfers
                            </a>
                        </div>
                        
                        <div class="d-flex gap-2">
                            @if($pickingList->status === 'pending')
                                <form action="{{ route('stock-transfers.warehouse-to-retailer.quick-complete', $pickingList) }}" 
                                      method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" 
                                            class="btn btn-success" 
                                            onclick="return confirm('Complete transfer with all requested quantities?')">
                                        <i class="bi bi-check-double me-1"></i>Quick Complete All
                                    </button>
                                </form>
                                
                                <form action="{{ route('stock-transfers.warehouse-to-retailer.cancel', $pickingList) }}" 
                                      method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-times me-1"></i>Cancel Transfer
                                    </button>
                                </form>
                            @elseif($pickingList->status === 'completed')
                                <span class="btn btn-success disabled">
                                    <i class="bi bi-check-circle me-1"></i>Transfer Completed
                                </span>
                            @elseif($pickingList->status === 'cancelled')
                                <span class="btn btn-danger disabled">
                                    <i class="bi bi-x-circle me-1"></i>Transfer Cancelled
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
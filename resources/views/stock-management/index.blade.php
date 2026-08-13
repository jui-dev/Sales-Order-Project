@extends('layouts.app')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">
                <i class="bi bi-clipboard-data me-2 text-primary"></i>Stock Transaction Records
            </h1>
            <p class="text-muted mb-0">Complete history of all inventory movements and stock transactions</p>
        </div>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    
    

    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-x-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Transaction Summary Cards -->
    <div class="summary-panel mb-4">
        <div class="row g-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm summary-card summary-card--total">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="card-title mb-0 text-primary">Total Transactions</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($stockTransactions->total()) }}</h4>
                        </div>
                        <div class="text-primary summary-card__icon">
                            <i class="bi bi-list-check fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm summary-card summary-card--completed">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="card-title mb-0 text-success">Completed</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($stockTransactions->where('status', 'completed')->count()) }}</h4>
                        </div>
                        <div class="text-success summary-card__icon">
                            <i class="bi bi-check-circle fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm summary-card summary-card--pending">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="card-title mb-0 text-warning-emphasis">Pending</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($stockTransactions->where('status', 'pending')->count()) }}</h4>
                        </div>
                        <div class="text-warning-emphasis summary-card__icon">
                            <i class="bi bi-clock fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm summary-card summary-card--today">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="card-title mb-0 text-info-emphasis">Today's Records</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($stockTransactions->where('created_at', '>=', today())->count()) }}</h4>
                        </div>
                        <div class="text-info-emphasis summary-card__icon">
                            <i class="bi bi-calendar-day fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>

    <!-- Records Toolbar -->
    <div class="unified-search-container card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <span class="badge bg-secondary-subtle text-secondary-emphasis border fs-6 px-3 py-2">
                        <i class="bi bi-list-check me-1"></i>{{ $stockTransactions->total() }} Total Records
                    </span>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-md-end gap-2 mt-3 mt-md-0">
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#filterTransactions">
                            <i class="bi bi-funnel me-1"></i>Filter Records
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="exportTransactions()">
                            <i class="bi bi-download me-1"></i>Export
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Stock Transactions Table -->
    <div class="card">
        @if(request()->hasAny(['location_type', 'location_id', 'transaction_type', 'status', 'date_from', 'date_to', 'product_search']))
        <div class="card-header bg-light border-0">
            <div class="row align-items-center">
                <div class="col-auto ms-auto">
                    <span class="badge bg-info me-2">
                        <i class="bi bi-funnel-fill me-1"></i>Filtered
                    </span>
                    <a href="{{ route('stock-management.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x me-1"></i>Clear Filters
                    </a>
                </div>
            </div>
        </div>
        @endif

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th width="80">ID</th>
                            <th width="200">Product</th>
                            <th width="150">Location</th>
                            <th width="160">Transaction Type</th>
                            <th width="120">Reference</th>
                            <th width="100" class="text-center">Quantity</th>
                            <th width="100" class="text-end">Unit Cost</th>
                            <th width="100" class="text-end">Total Cost</th>
                            <th width="130">Date</th>
                            <th width="100" class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stockTransactions as $transaction)
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark font-monospace">#{{ $transaction->id }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $transaction->product->name ?? 'Product Not Found' }}</div>
                                @if($transaction->product)
                                    <small class="text-muted">ID: {{ $transaction->product->id }}</small>
                                @endif
                            </td>
                                                        <td>
                                <div class="fw-medium">{{ $transaction->stockLocation->name ?? 'Location Not Found' }}</div>
                                @if($transaction->stockLocation)
                                <small class="text-muted">{{ ucfirst($transaction->stockLocation->type ?? '') }}</small>
                                @endif
                            </td>
                            <td>
                                @php $config = $transaction->getDisplayConfig(); @endphp
                                <span class="badge bg-{{ $config['badge_color'] }}">
                                    <i class="{{ $config['icon'] }} me-1"></i>{{ $config['label'] }}
                                </span>
                                <div class="small text-muted mt-1">{{ $config['description'] }}</div>
                            </td>

                            <td>
                                <div class="font-monospace small">{{ $transaction->reference_number }}</div>
                                @if($transaction->reference_type)
                                    @php $refDisplay = $transaction->getReferenceDisplay(); @endphp
                                    <small class="text-muted d-block">{{ $refDisplay['label'] }} #{{ $transaction->reference_id }}</small>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $transaction->effect > 0 ? 'success' : 'danger' }} fs-6">
                                    {{ $transaction->getFormattedQuantity() }}
                                </span>
                                <div class="small text-muted mt-1">{{ $transaction->getDetailedEffectDescription() }}</div>
                                <div class="small text-muted">{{ $transaction->getEffectDescription() }}</div>
                            </td>
                            <td class="text-end font-monospace">
                                ${{ number_format($transaction->unit_cost, 2) }}
                            </td>
                            <td class="text-end font-monospace fw-semibold">
                                ${{ number_format($transaction->total_cost, 2) }}
                            </td>

                            <td>
                                <div class="small fw-medium">{{ $transaction->created_at->format('M d, Y') }}</div>
                                <small class="text-muted">{{ $transaction->created_at->format('H:i') }}</small>
                            </td>
                            <td class="text-center">
                                @switch($transaction->status)
                                    @case('completed')
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i>Completed
                                        </span>
                                        @break
                                    @case('pending')
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-clock me-1"></i>Pending
                                        </span>
                                        @break
                                    @case('cancelled')
                                        <span class="badge bg-danger">
                                            <i class="bi bi-x-circle me-1"></i>Cancelled
                                        </span>
                                        @break
                                    @default
                                        <span class="badge bg-secondary">{{ ucfirst($transaction->status) }}</span>
                                @endswitch
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-inbox display-1 d-block mb-3"></i>
                                    <h5>No Stock Transactions Found</h5>
                                    <p class="mb-0">No stock transactions match your current criteria.</p>
                                    @if(request()->hasAny(['location_type', 'location_id', 'transaction_type', 'status', 'date_from', 'date_to', 'product_search']))
                                        <a href="{{ route('stock-management.index') }}" class="btn btn-outline-primary mt-2">
                                            <i class="bi bi-arrow-clockwise me-1"></i>Clear Filters
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$stockTransactions" />
        </div>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterTransactions" tabindex="-1" aria-labelledby="filterTransactionsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filterTransactionsLabel">
                    <i class="bi bi-funnel me-2"></i>Filter Stock Transaction Records
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('stock-management.index') }}" method="GET">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Product Search</label>
                            <input type="text" class="form-control" name="product_search" 
                                   value="{{ request('product_search') }}" 
                                   placeholder="Search by product name...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Location Type</label>
                            <select class="form-select" name="location_type">
                                <option value="">All Location Types</option>
                                <option value="warehouse" {{ request('location_type') === 'warehouse' ? 'selected' : '' }}>
                                    <i class="bi bi-building"></i> Warehouse
                                </option>
                                <option value="retailer" {{ request('location_type') === 'retailer' ? 'selected' : '' }}>
                                    <i class="bi bi-shop"></i> Retailer
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Specific Location</label>
                            <select class="form-select" name="location_id">
                                <option value="">All Locations</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" {{ request('location_id') == $location->id ? 'selected' : '' }}>
                                        {{ $location->name }} ({{ ucfirst($location->location_type ?? 'location') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Transaction Type</label>
                            <select class="form-select" name="transaction_type">
                                <option value="">All Transaction Types</option>
                                <!-- NEW TRANSACTION TYPES (Primary) -->
                                <optgroup label="Current Transaction Types">
                                    <option value="stock_in" {{ request('transaction_type') === 'stock_in' ? 'selected' : '' }}>Stock In (Vendor → Warehouse)</option>
                                    <option value="stock_transfer" {{ request('transaction_type') === 'stock_transfer' ? 'selected' : '' }}>Stock Transfer (Warehouse ⇄ Retailer)</option>
                                    <option value="order_fulfillment" {{ request('transaction_type') === 'order_fulfillment' ? 'selected' : '' }}>Order Fulfillment (Customer Orders)</option>
                                    <option value="adjustment" {{ request('transaction_type') === 'adjustment' ? 'selected' : '' }}>Stock Adjustment</option>
                                </optgroup>
                                <!-- LEGACY TRANSACTION TYPES -->
                                <optgroup label="Legacy Transaction Types">
                                    <option value="vendor_to_warehouse" {{ request('transaction_type') === 'vendor_to_warehouse' ? 'selected' : '' }}>Vendor to Warehouse (Legacy)</option>
                                    <option value="supply_in" {{ request('transaction_type') === 'supply_in' ? 'selected' : '' }}>Supply In (Legacy)</option>
                                    <option value="sale" {{ request('transaction_type') === 'sale' ? 'selected' : '' }}>Sale (Legacy)</option>
                                    <option value="transfer_in" {{ request('transaction_type') === 'transfer_in' ? 'selected' : '' }}>Transfer In (Legacy)</option>
                                    <option value="transfer_out" {{ request('transaction_type') === 'transfer_out' ? 'selected' : '' }}>Transfer Out (Legacy)</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date From</label>
                            <input type="date" class="form-control" name="date_from" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date To</label>
                            <input type="date" class="form-control" name="date_to" value="{{ request('date_to') }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-1"></i>Cancel
                    </button>
                    <a href="{{ route('stock-management.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise me-1"></i>Clear All Filters
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel me-1"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
<style>
.badge {
    font-weight: 500;
}

.font-monospace {
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
}

/* White container holding the summary cards */
.summary-panel {
    background-color: #ffffff;
    border: 1px solid var(--border-color, #e9ecef);
    border-radius: 8px;
    padding: 1.25rem;
    box-shadow: var(--card-shadow, 0 2px 15px rgba(0, 0, 0, 0.04));
}

.summary-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.summary-card:hover {
    transform: translateY(-2px);
}

.summary-card--total     { background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%); }
.summary-card--completed { background: linear-gradient(135deg, #e8f5e9 0%, #ffffff 100%); }
.summary-card--pending   { background: linear-gradient(135deg, #fff8e1 0%, #ffffff 100%); }
.summary-card--today     { background: linear-gradient(135deg, #e0f7fa 0%, #ffffff 100%); }

.summary-card__icon {
    opacity: 0.45;
}
</style>
@endpush

@push('scripts')
<script>
function exportTransactions() {
    // Get current filters
    const urlParams = new URLSearchParams(window.location.search);
    
    // Build export URL with current filters
    let exportUrl = '{{ route("stock-management.index") }}?export=1';
    
    for (let [key, value] of urlParams) {
        if (key !== 'page') {
            exportUrl += `&${key}=${encodeURIComponent(value)}`;
        }
    }
    
    // Create temporary link and click it
    const link = document.createElement('a');
    link.href = exportUrl;
    link.download = 'stock-transactions.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Auto-refresh functionality (optional)
document.addEventListener('DOMContentLoaded', function() {
    // Refresh badge every 30 seconds
    setInterval(function() {
        fetch('{{ route("stock-management.index") }}?ajax=1')
            .then(response => response.json())
            .then(data => {
                if (data.total) {
                    document.querySelector('.badge.bg-primary .bi-list-check').nextSibling.textContent = 
                        ` ${data.total.toLocaleString()} Total Records`;
                }
            })
            .catch(error => console.log('Auto-refresh error:', error));
    }, 30000);
});
</script>
@endpush
@endsection 
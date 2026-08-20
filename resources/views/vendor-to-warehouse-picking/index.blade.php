@extends('layouts.app')
@section('page-header')
<div class="mb-4">
        <h1><i class="bi bi-truck-arrow-right me-2"></i>Vendors to Warehouse Picking</h1>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    
    

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Info Banner -->
<div class="alert alert-info d-flex align-items-center" role="alert">
    <i class="bi bi-info-circle me-2"></i>
    <div>
        <strong>Vendor to Warehouse Picking:</strong> This section shows all automatic stock transactions created when vendors supply products to warehouses. Stock quantities are automatically updated when supplies are completed.
    </div>
</div>

<!-- System Overview Cards -->
<div class="summary-panel mb-4">
    <div class="row g-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm summary-card summary-card--blue">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title text-primary">Active Vendors</h6>
                        <h3>{{ $statistics['total_vendors'] }}</h3>
                    </div>
                    <i class="bi bi-building text-primary summary-card__icon" style="font-size: 2rem;"></i>
                </div>
                <small>
                    <a href="{{ route('vendors.index') }}" class="text-primary text-decoration-none">
                        <i class="bi bi-arrow-right me-1"></i>Manage Vendors
                    </a>
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm summary-card summary-card--green">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title text-success">Warehouses</h6>
                        <h3>{{ $statistics['total_warehouses'] }}</h3>
                    </div>
                    <i class="bi bi-house-fill text-success summary-card__icon" style="font-size: 2rem;"></i>
                </div>
                <small>
                    <a href="{{ route('stock-locations.index') }}" class="text-success text-decoration-none">
                        <i class="bi bi-arrow-right me-1"></i>Manage Locations
                    </a>
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm summary-card summary-card--amber">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title text-warning-emphasis">Recent Supplies</h6>
                        <h3>{{ $statistics['recent_supplies']->count() }}</h3>
                    </div>
                    <i class="bi bi-truck text-warning-emphasis summary-card__icon" style="font-size: 2rem;"></i>
                </div>
                <small>
                    <a href="{{ route('supplies.index') }}" class="text-warning-emphasis text-decoration-none">
                        <i class="bi bi-arrow-right me-1"></i>View All Supplies
                    </a>
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm summary-card summary-card--cyan">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title text-info-emphasis">Stock Transactions</h6>
                        <h3>{{ $stockTransactions->count() }}</h3>
                    </div>
                    <i class="bi bi-list-check text-info-emphasis summary-card__icon" style="font-size: 2rem;"></i>
                </div>
                <small class="text-muted">
                    Vendor to warehouse movements
                </small>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- Unified Search Component -->
<x-unified-search
    :searchPlaceholder="'Search by supply number, vendor, warehouse, or product...'"
    :filterOptions="$filterOptions"
    :sortOptions="$sortOptions"
    :defaultSort="'id'"
    :defaultDirection="'desc'"
/>

@php
    $hasActiveFilters = request()->hasAny(['search', 'warehouse_id', 'vendor_id', 'vendor', 'status', 'date_from', 'date_to']);
@endphp

{{-- While filtering, always keep the tabs on screen so an empty result reads as
     "nothing matched" rather than "nothing exists yet". --}}
@if($pickingLists->count() > 0 || $stockTransactions->count() > 0 || $hasActiveFilters)
<!-- Tabbed Content -->
<div class="card">
    <div class="card-header vp-tabs-header">
        <ul class="nav vp-tabs" id="vendorPickingTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="picking-lists-tab" data-bs-toggle="tab"
                        data-bs-target="#picking-lists" type="button" role="tab"
                        aria-controls="picking-lists" aria-selected="true">
                    <i class="bi bi-list-check"></i>
                    <span>Picking Lists</span>
                    <span class="vp-tab-count">{{ $pickingLists->count() }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="stock-transactions-tab" data-bs-toggle="tab"
                        data-bs-target="#stock-transactions" type="button" role="tab"
                        aria-controls="stock-transactions" aria-selected="false">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>Stock Transactions</span>
                    <span class="vp-tab-count">{{ $stockTransactions->count() }}</span>
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="vendorPickingTabContent">
            <!-- Picking Lists Tab -->
            <div class="tab-pane fade show active" id="picking-lists" role="tabpanel" aria-labelledby="picking-lists-tab">
                @if($pickingLists->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Picking Number</th>
                                    <th>Supply Info</th>
                                    <th>Vendor</th>
                                    <th>Warehouse</th>
                                    <th>Items</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pickingLists as $pickingList)
                                    <tr>
                                        <td>
                                            <strong>{{ $pickingList->picking_number }}</strong>
                                            <br><small class="text-muted">
                                                <i class="bi bi-truck-arrow-right me-1"></i>Vendor to Warehouse
                                            </small>
                                        </td>
                                        <td>
                                            @if($pickingList->supply)
                                                <strong>{{ $pickingList->supply->supply_number }}</strong>
                                                <br><small class="text-muted">
                                                    Supply Date: {{ $pickingList->supply->supply_date->format('M d, Y') }}
                                                </small>
                                                @if($pickingList->supply && isset($pickingList->supply->total_cost) && $pickingList->supply->total_cost)
                                                    <br><small class="text-success">
                                                        Total: ${{ number_format($pickingList->supply->total_cost, 2) }}
                                                    </small>
                                                @endif
                                                @if($pickingList->supply->grn)
                                                    <br><small class="text-info">
                                                        GRN #: {{ $pickingList->supply->grn->id }} — {{ ucfirst($pickingList->supply->grn->status) }}
                                                    </small>
                                                @endif
                                            @else
                                                <span class="text-muted">No supply info</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($pickingList->supply && $pickingList->supply->vendor)
                                                <strong>{{ $pickingList->supply->vendor->name }}</strong>
                                                @if($pickingList->supply->vendor->email)
                                                    <br><small class="text-muted">{{ $pickingList->supply->vendor->email }}</small>
                                                @endif
                                            @else
                                                <span class="text-muted">Unknown</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($pickingList->toLocation)
                                                <strong>{{ $pickingList->toLocation->name }}</strong>
                                                <br><small class="text-muted">{{ $pickingList->toLocation->address ?? 'No address' }}</small>
                                            @else
                                                <span class="text-muted">Unknown</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-info">{{ $pickingList->pickingItems->count() }} items</span>
                                            @if($pickingList->pickingItems->count() > 0)
                                                <br><small class="text-muted">
                                                    Total Qty: {{ $pickingList->pickingItems ? $pickingList->pickingItems->sum('quantity_requested') : 0 }}
                                                </small>
                                                @if($pickingList->status === 'completed')
                                                    <br><small class="text-success">
                                                        Picked: {{ $pickingList->pickingItems ? $pickingList->pickingItems->sum('quantity_picked') : 0 }}
                                                    </small>
                                                @endif
                                            @endif
                                        </td>
                                        <td>
                                            @switch($pickingList->status)
                                                @case('pending')
                                                    <span class="badge bg-warning">Pending</span>
                                                    @break
                                                @case('in_progress')
                                                    <span class="badge bg-info">In Progress</span>
                                                    @break
                                                @case('completed')
                                                    <span class="badge bg-success">Completed</span>
                                                    @if($pickingList->completed_at)
                                                        <br><small class="text-muted">{{ $pickingList->completed_at->format('M d, Y H:i') }}</small>
                                                    @endif
                                                    @break
                                                @case('cancelled')
                                                    <span class="badge bg-danger">Cancelled</span>
                                                    @break
                                                @default
                                                    <span class="badge bg-secondary">{{ ucfirst($pickingList->status) }}</span>
                                            @endswitch
                                        </td>
                                        <td>
                                            <strong>{{ $pickingList->picking_date->format('M d, Y') }}</strong>
                                            <br><small class="text-muted">{{ $pickingList->picking_date->format('H:i') }}</small>
                                        </td>
                                                                                                                         <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('vendor-to-warehouse-picking.show-picking-list', $pickingList) }}" 
                                                   class="btn btn-sm btn-outline-primary" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                @if($pickingList->supply)
                                                    <a href="{{ route('supplies.show', $pickingList->supply) }}" 
                                                       class="btn btn-sm btn-outline-info" title="View Supply">
                                                        <i class="bi bi-truck"></i>
                                                    </a>
                                                @endif
                                                @if($pickingList->status === 'pending')
                                                    <a href="{{ route('vendor-to-warehouse-picking.show-picking-list', $pickingList) }}" 
                                                       class="btn btn-sm btn-success" title="Process Picking">
                                                        <i class="bi bi-play-circle"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="bi bi-list-check text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-muted mt-3">No Picking Lists Found</h5>
                        <p class="text-muted">No vendor-to-warehouse picking lists match your current filters.</p>
                        @if(request()->hasAny(['search', 'warehouse_id', 'vendor_id', 'vendor', 'status', 'date_from', 'date_to']))
                            <a href="{{ route('vendor-to-warehouse-picking.index') }}" class="btn btn-outline-primary mt-2">
                                <i class="bi bi-arrow-clockwise me-1"></i>Clear Filters
                            </a>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Stock Transactions Tab -->
            <div class="tab-pane fade" id="stock-transactions" role="tabpanel" aria-labelledby="stock-transactions-tab">
                @if($stockTransactions->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Reference Number</th>
                                    <th>Product</th>
                                    <th>Warehouse</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Total Cost</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stockTransactions as $transaction)
                                    <tr>
                                        <td>
                                            <strong>{{ $transaction->reference_number }}</strong>
                                            <br><small class="text-muted">
                                                <i class="bi bi-truck-arrow-right me-1"></i>Vendor Supply
                                            </small>
                                            @if($transaction->reference_type === 'supply')
                                                <br><small class="text-info">Supply ID: {{ $transaction->reference_id }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-box me-2 text-primary"></i>
                                                <div>
                                                    <strong>{{ $transaction->product->name ?? 'N/A' }}</strong>
                                                    @if($transaction->product)
                                                        <br><small class="text-muted">SKU: {{ $transaction->product->sku ?? 'N/A' }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-house-fill me-2 text-success"></i>
                                                <div>
                                                    <strong>{{ $transaction->destinationLocation->name ?? 'N/A' }}</strong>
                                                    @if($transaction->destinationLocation && $transaction->destinationLocation->address)
                                                        <br><small class="text-muted">{{ Str::limit($transaction->destinationLocation->address, 40) }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-success fs-6">
                                                +{{ $transaction->stock_quantity }}
                                            </span>
                                        </td>
                                        <td>
                                            ${{ number_format($transaction->unit_cost, 2) }}
                                        </td>
                                        <td>
                                            <strong>${{ number_format($transaction->total_cost, 2) }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $transaction->status === 'pending' ? 'warning' : ($transaction->status === 'completed' ? 'success' : 'danger') }}">
                                                {{ ucfirst($transaction->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ $transaction->created_at->format('M d, Y') }}
                                            <br><small class="text-muted">{{ $transaction->created_at->format('H:i') }}</small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('vendor-to-warehouse-picking.show', $transaction) }}" class="btn btn-sm btn-outline-primary" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                @if($transaction->reference_type === 'supply' && $transaction->reference_id)
                                                    <a href="{{ route('supplies.show', $transaction->reference_id) }}" class="btn btn-sm btn-outline-info" title="View Supply">
                                                        <i class="bi bi-truck"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                                 @else
                     <div class="text-center py-4">
                         <i class="bi bi-arrow-repeat text-muted" style="font-size: 3rem;"></i>
                         <h5 class="text-muted mt-3">No Stock Transactions Found</h5>
                         <p class="text-muted">No stock transactions match your current filters.</p>
                     </div>
                 @endif
             </div>
         </div>
     </div>
 </div>
 @else
<!-- Enhanced Empty State -->
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-truck-arrow-right" style="font-size: 4rem; color: #6c757d;"></i>
                <h4 class="mt-3 text-muted">No Vendor to Warehouse Transactions Yet</h4>
                <p class="text-muted mb-4">
                    Stock transactions will appear here when vendors supply products to warehouses.<br>
                    When a supply is completed, the system automatically creates stock transactions and updates inventory.
                </p>
                
                @if($totalVendors > 0 && $totalWarehouses > 0)
                    <div class="alert alert-light border">
                        <h6 class="text-success">
                            <i class="bi bi-check-circle me-1"></i>System Ready
                        </h6>
                        <p class="mb-2">You have <strong>{{ $totalVendors }}</strong> vendor(s) and <strong>{{ $totalWarehouses }}</strong> warehouse(s) configured.</p>
                        <p class="mb-0">Create a supply from a vendor to see transactions here.</p>
                    </div>
                @else
                    <div class="alert alert-warning border">
                        <h6 class="text-warning">
                            <i class="bi bi-exclamation-triangle me-1"></i>Setup Required
                        </h6>
                        @if($totalVendors == 0)
                            <p class="mb-2">No vendors configured. <a href="{{ route('vendors.create') }}">Create your first vendor</a></p>
                        @endif
                        @if($totalWarehouses == 0)
                            <p class="mb-0">No warehouses configured. <a href="{{ route('stock-locations.create') }}">Create your first warehouse</a></p>
                        @endif
                    </div>
                @endif
                
                <div class="d-flex justify-content-center gap-2 mt-4">
                    <a href="{{ route('supplies.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Create New Supply
                    </a>
                    @if($totalVendors == 0)
                        <a href="{{ route('vendors.create') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-building me-1"></i> Add Vendor
                        </a>
                    @endif
                    @if($totalWarehouses == 0)
                        <a href="{{ route('stock-locations.create') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-house-plus me-1"></i> Add Warehouse
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Vendors List -->
        @if($vendors->count() > 0)
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-building me-1"></i>Available Vendors
                    </h6>
                </div>
                <div class="card-body">
                    @foreach($vendors->take(5) as $vendor)
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-building me-2 text-primary"></i>
                            <div class="flex-grow-1">
                                <strong>{{ $vendor->name }}</strong>
                                @if($vendor->contact_person)
                                    <br><small class="text-muted">{{ $vendor->contact_person }}</small>
                                @endif
                            </div>
                            <a href="{{ route('supplies.create') }}?vendor_id={{ $vendor->id }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus"></i>
                            </a>
                        </div>
                    @endforeach
                    @if($vendors->count() > 5)
                        <div class="text-center mt-3">
                            <a href="{{ route('vendors.index') }}" class="btn btn-sm btn-outline-secondary">
                                View All {{ $vendors->count() }} Vendors
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Warehouses List -->
        @if($warehouses->count() > 0)
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-house-fill me-1"></i>Available Warehouses
                    </h6>
                </div>
                <div class="card-body">
                    @foreach($warehouses->take(5) as $warehouse)
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-house-fill me-2 text-success"></i>
                            <div class="flex-grow-1">
                                <strong>{{ $warehouse->name }}</strong>
                                <br><small class="text-muted">Stock: {{ $warehouse->total_stock }} items</small>
                            </div>
                            <a href="{{ route('stock-locations.show', $warehouse) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </div>
                    @endforeach
                    @if($warehouses->count() > 5)
                        <div class="text-center mt-3">
                            <a href="{{ route('stock-locations.index') }}" class="btn btn-sm btn-outline-secondary">
                                View All {{ $warehouses->count() }} Warehouses
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Recent Supplies -->
        @if($recentSupplies->count() > 0)
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-truck me-1"></i>Recent Completed Supplies
                    </h6>
                </div>
                <div class="card-body">
                    @foreach($recentSupplies as $supply)
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-truck me-2 text-warning"></i>
                            <div class="flex-grow-1">
                                <strong>{{ $supply->vendor->name }}</strong>
                                <br><small class="text-muted">{{ $supply->items->count() }} items - ${{ number_format($supply->total_cost ?? 0, 2) }}</small>
                            </div>
                            <div class="text-end">
                                <a href="{{ route('supplies.show', $supply) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if(!$supply->stockTransactions()->where('transaction_type', 'vendor_to_warehouse')->exists())
                                    <form action="{{ route('vendor-to-warehouse-picking.trigger', $supply) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success" title="Process Stock">
                                            <i class="bi bi-play-fill"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endif

</div>
@endsection

@push('styles')
<style>
/* .summary-panel / .summary-card now live in public/css/custom.css, so every
   index page that uses them gets the same treatment. */

/* ---- Tabs: segmented control ----------------------------------------
   The global .nav-link rules in custom.css are written for the dark green
   navbar and force a near-white colour with !important. Inside this white
   card header that washes the active tab out, so the colours below have to
   be !important too. */
.vp-tabs-header {
    padding: 0.75rem 1.25rem;
    /* .card-header adds a bottom rule globally; the segmented tabs already
       separate themselves from the table, so drop it here. */
    border-bottom: none;
}

.vp-tabs {
    display: inline-flex;
    gap: 0.25rem;
    margin: 0;
    padding: 0.25rem;
    border: none;
    border-radius: 10px;
    background-color: rgba(44, 110, 73, 0.07);
}

.vp-tabs .nav-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 !important;
    padding: 0.5rem 1rem !important;
    border: none !important;
    border-radius: 8px;
    background-color: transparent;
    color: var(--medium-text, #4f5d75) !important;
    font-size: 0.9rem;
    font-weight: 500;
    line-height: 1.2;
    white-space: nowrap;
    transition: background-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
}

.vp-tabs .nav-link i {
    font-size: 1rem;
    opacity: 0.75;
}

.vp-tabs .nav-link:hover:not(.active) {
    background-color: rgba(44, 110, 73, 0.11) !important;
    color: var(--primary-dark, #1a472a) !important;
}

.vp-tabs .nav-link.active {
    background-color: #ffffff !important;
    color: var(--primary, #2c6e49) !important;
    font-weight: 600;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 0 0 1px rgba(44, 110, 73, 0.12);
}

.vp-tabs .nav-link.active i {
    opacity: 1;
}

.vp-tabs .nav-link:focus-visible {
    outline: 2px solid var(--primary-light, #4c956c);
    outline-offset: 2px;
}

/* Count chip */
.vp-tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.5rem;
    padding: 0.1rem 0.4rem;
    border-radius: 999px;
    background-color: rgba(44, 110, 73, 0.13);
    color: var(--primary-dark, #1a472a);
    font-size: 0.75rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    transition: background-color 0.18s ease, color 0.18s ease;
}

.vp-tabs .nav-link.active .vp-tab-count {
    background-color: var(--primary, #2c6e49);
    color: #ffffff;
}

/* On narrow screens let the two tabs share the full width */
@media (max-width: 575.98px) {
    .vp-tabs {
        display: flex;
        width: 100%;
    }
    .vp-tabs .nav-item {
        flex: 1 1 0;
    }
    .vp-tabs .nav-link {
        width: 100%;
        justify-content: center;
        gap: 0.35rem;
        padding: 0.5rem 0.5rem !important;
        font-size: 0.825rem;
    }
}
</style>
@endpush 
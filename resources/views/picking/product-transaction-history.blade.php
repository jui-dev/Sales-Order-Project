@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-clock-history me-2"></i>Transaction History: {{ $product->name }}</h1>
    <div>
        <a href="{{ route('products.stock-analysis', $product) }}" class="btn btn-outline-info">
            <i class="bi bi-graph-up me-1"></i> Stock Analysis
        </a>
        <a href="{{ route('picking.transaction-flow') }}" class="btn btn-outline-primary">
            <i class="bi bi-diagram-3 me-1"></i> Transaction Flow
        </a>
        <a href="{{ route('products.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Products
        </a>
    </div>
</div>

<!-- Product Summary Cards -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-success">Total Supplied</h5>
                <h2>{{ $totalSupplied }}</h2>
                <small class="text-muted">Units received</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-danger">Total Sold</h5>
                <h2>{{ $totalSold }}</h2>
                <small class="text-muted">Units sold</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-warning">Transferred</h5>
                <h2>{{ $totalTransferred }}</h2>
                <small class="text-muted">Between locations</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-primary">Current Stock</h5>
                <h2>{{ $currentTotalStock }}</h2>
                <small class="text-muted">Total in system</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-info">Available</h5>
                <h2>{{ $currentAvailableStock }}</h2>
                <small class="text-muted">Ready to pick</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-secondary">Reserved</h5>
                <h2>{{ $currentReservedStock }}</h2>
                <small class="text-muted">Pending picks</small>
            </div>
        </div>
    </div>
</div>

<!-- Current Stock by Location -->
@if($stockBalances->count() > 0)
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Current Stock by Location</h5>
    </div>
    <div class="card-body">
        <div class="row">
            @foreach($stockBalances as $balance)
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="card-title mb-0">{{ $balance->stockLocation ? $balance->stockLocation->name : 'Location Deleted' }}</h6>
                            <span class="badge bg-{{ $balance->stockLocation && $balance->stockLocation->location_type === 'warehouse' ? 'success' : 'info' }}">
                                {{ $balance->stockLocation ? ucfirst($balance->stockLocation->location_type) : 'Unknown' }}
                            </span>
                        </div>
                        
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="border-end">
                                    <h5 class="text-primary mb-0">{{ $balance->quantity }}</h5>
                                    <small class="text-muted">Total</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border-end">
                                    <h5 class="text-warning mb-0">{{ $balance->reserved_quantity }}</h5>
                                    <small class="text-muted">Reserved</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <h5 class="text-success mb-0">{{ $balance->available_quantity }}</h5>
                                <small class="text-muted">Available</small>
                            </div>
                        </div>
                        
                        @if($balance->last_movement_date)
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>Last updated: {{ $balance->last_movement_date->format('M d, Y H:i') }}
                                </small>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

<!-- Transaction History -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>All Stock Movements</h5>
    </div>
    <div class="card-body">
        @if($movements->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Quantity</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($movements as $movement)
                        <tr>
                            <td>
                                {{ $movement->movement_date->format('M d, Y') }}
                                <br><small class="text-muted">{{ $movement->movement_date->format('H:i') }}</small>
                            </td>
                            <td>
                                <span class="badge bg-{{ $movement->movement_type === 'supply_in' ? 'success' : ($movement->movement_type === 'sale' ? 'danger' : ($movement->movement_type === 'transfer' ? 'warning' : 'info')) }}">
                                    {{ ucfirst(str_replace('_', ' ', $movement->movement_type)) }}
                                </span>
                            </td>
                            <td>
                                @if($movement->fromLocation)
                                    <span class="badge bg-secondary">{{ $movement->fromLocation->name }}</span>
                                    @if(isset($movement->fromLocation->location_type))
                                        <br><small class="text-muted">{{ ucfirst($movement->fromLocation->location_type) }}</small>
                                    @endif
                                @else
                                    <span class="text-muted">
                                        @if($movement->movement_type === 'supply_in')
                                            <i class="bi bi-building me-1"></i>Vendor Supply
                                        @else
                                            -
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($movement->toLocation)
                                    <span class="badge bg-secondary">{{ $movement->toLocation->name }}</span>
                                    @if(isset($movement->toLocation->location_type))
                                        <br><small class="text-muted">{{ ucfirst($movement->toLocation->location_type) }}</small>
                                    @endif
                                @else
                                    <span class="text-muted">
                                        @if($movement->movement_type === 'sale')
                                            <i class="bi bi-people me-1"></i>Customer
                                        @else
                                            -
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td>
                                <strong class="text-{{ $movement->direction === 'inbound' ? 'success' : 'danger' }}">
                                    {{ $movement->direction === 'inbound' ? '+' : '-' }}{{ abs($movement->quantity) }}
                                </strong>
                            </td>
                            <td>
                                @if($movement->reference_type && $movement->reference_id)
                                    <span class="badge bg-light text-dark">
                                        {{ ucfirst($movement->reference_type) }} #{{ $movement->reference_id }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $movement->status === 'completed' ? 'success' : ($movement->status === 'pending' ? 'warning' : 'danger') }}">
                                    {{ ucfirst($movement->status) }}
                                </span>
                            </td>
                            <td>
                                @if($movement->notes)
                                    <small class="text-muted">{{ Str::limit($movement->notes, 30) }}</small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="d-flex justify-content-center mt-3">
                {{ $movements->links() }}
            </div>
        @else
            <div class="text-center py-4">
                <i class="bi bi-clock-history display-4 text-muted mb-3"></i>
                <h5>No Transaction History</h5>
                <p class="text-muted">No stock movements found for this product.</p>
            </div>
        @endif
    </div>
</div>

<!-- Related Picking Lists -->
@if($pickingLists->count() > 0)
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Recent Picking Lists</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Picking Number</th>
                        <th>Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pickingLists as $picking)
                    <tr>
                        <td>{{ $picking->picking_number }}</td>
                        <td>
                            <span class="badge bg-{{ $picking->picking_type === 'order_fulfillment' ? 'danger' : ($picking->picking_type === 'retailer_distribution' ? 'info' : 'warning') }}">
                                {{ ucfirst(str_replace('_', ' ', $picking->picking_type)) }}
                            </span>
                        </td>
                        <td>{{ $picking->fromLocation ? $picking->fromLocation->name : 'Location Deleted' }}</td>
                        <td>{{ $picking->toLocation ? $picking->toLocation->name : 'Customer' }}</td>
                        <td>
                            <span class="badge bg-{{ $picking->status === 'completed' ? 'success' : ($picking->status === 'pending' ? 'warning' : 'primary') }}">
                                {{ ucfirst(str_replace('_', ' ', $picking->status)) }}
                            </span>
                        </td>
                        <td>{{ $picking->picking_date->format('M d, Y') }}</td>
                        <td>
                            <a href="{{ route('picking.show', $picking) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection 
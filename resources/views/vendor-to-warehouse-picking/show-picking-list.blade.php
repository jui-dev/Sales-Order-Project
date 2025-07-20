@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1><i class="bi bi-list-check me-2"></i>Vendor to Warehouse Picking List</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('vendor-to-warehouse-picking.index') }}">Vendor to Warehouse</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $pickingList->picking_number }}</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('vendor-to-warehouse-picking.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
            @if($pickingList->supply)
                <a href="{{ route('supplies.show', $pickingList->supply) }}" class="btn btn-outline-info">
                    <i class="bi bi-truck me-1"></i> View Supply
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <!-- Picking List Details -->
        <div class="col-md-8">
            <!-- Basic Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle me-2"></i>Picking List Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Picking Number:</strong><br>
                            <span class="fs-5">{{ $pickingList->picking_number }}</span>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong><br>
                            @switch($pickingList->status)
                                @case('pending')
                                    <span class="badge bg-warning fs-6">Pending</span>
                                    @break
                                @case('in_progress')
                                    <span class="badge bg-info fs-6">In Progress</span>
                                    @break
                                @case('completed')
                                    <span class="badge bg-success fs-6">Completed</span>
                                    @break
                                @case('cancelled')
                                    <span class="badge bg-danger fs-6">Cancelled</span>
                                    @break
                                @default
                                    <span class="badge bg-secondary fs-6">{{ ucfirst($pickingList->status) }}</span>
                            @endswitch
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <strong>Picking Date:</strong><br>
                            {{ $pickingList->picking_date->format('M d, Y H:i') }}
                        </div>
                        @if($pickingList->completed_at)
                            <div class="col-md-6">
                                <strong>Completed At:</strong><br>
                                {{ $pickingList->completed_at->format('M d, Y H:i') }}
                            </div>
                        @endif
                    </div>

                    @if($pickingList->toLocation)
                        <hr>
                        <div class="row">
                            <div class="col-md-12">
                                <strong>Destination Warehouse:</strong><br>
                                <i class="bi bi-house-fill me-1"></i>{{ $pickingList->toLocation->name }}
                                @if($pickingList->toLocation->address)
                                    <br><small class="text-muted">{{ $pickingList->toLocation->address }}</small>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($pickingList->notes)
                        <hr>
                        <div class="row">
                            <div class="col-md-12">
                                <strong>Notes:</strong><br>
                                <div class="bg-light p-2 rounded">{{ $pickingList->notes }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Picking Items -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-box me-2"></i>Picking Items
                        <span class="badge bg-primary ms-2">{{ $pickingList->pickingItems->count() }}</span>
                    </h5>
                </div>
                <div class="card-body">
                    @if($pickingList->pickingItems->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Requested Qty</th>
                                        <th>Picked Qty</th>
                                        <th>Status</th>
                                        <th>Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pickingList->pickingItems as $item)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-box me-2 text-primary"></i>
                                                    <div>
                                                        <strong>{{ $item->product->name ?? 'Unknown Product' }}</strong>
                                                        @if($item->product && $item->product->sku)
                                                            <br><small class="text-muted">SKU: {{ $item->product->sku }}</small>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">{{ $item->quantity_requested }}</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">{{ $item->quantity_picked }}</span>
                                            </td>
                                            <td>
                                                @switch($item->status)
                                                    @case('pending')
                                                        <span class="badge bg-warning">Pending</span>
                                                        @break
                                                    @case('completed')
                                                        <span class="badge bg-success">Completed</span>
                                                        @break
                                                    @case('partial')
                                                        <span class="badge bg-info">Partial</span>
                                                        @break
                                                    @default
                                                        <span class="badge bg-secondary">{{ ucfirst($item->status) }}</span>
                                                @endswitch
                                            </td>
                                            <td>
                                                @php
                                                    $progress = $item->quantity_requested > 0 ? ($item->quantity_picked / $item->quantity_requested) * 100 : 0;
                                                @endphp
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar 
                                                        @if($progress === 100) bg-success 
                                                        @elseif($progress > 0) bg-warning 
                                                        @else bg-secondary @endif" 
                                                         role="progressbar" 
                                                         style="width: {{ $progress }}%;" 
                                                         aria-valuenow="{{ $progress }}" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                        {{ round($progress) }}%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Summary -->
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6>Total Items</h6>
                                        <h4>{{ $pickingList->pickingItems->count() }}</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6>Total Requested</h6>
                                        <h4>{{ $pickingList->pickingItems->sum('quantity_requested') }}</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6>Total Picked</h6>
                                        <h4>{{ $pickingList->pickingItems->sum('quantity_picked') }}</h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Processing Form for Pending Picking Lists -->
                        @if($pickingList->status === 'pending' && $pickingList->pickingItems->count() > 0)
                            <div class="mt-4">
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">
                                            <i class="bi bi-play-circle me-2"></i>Process Picking List
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <form action="{{ route('vendor-to-warehouse-picking.process-picking-list', $pickingList) }}" method="POST">
                                            @csrf
                                            
                                            <div class="mb-3">
                                                <p class="text-muted small mb-3">
                                                    <i class="bi bi-info-circle me-1"></i>
                                                    Adjust the picked quantities below if needed, then click "Process Picking List" to complete the receiving process.
                                                </p>
                                                
                                                <div class="table-responsive">
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Product</th>
                                                                <th>Requested</th>
                                                                <th>Picked Quantity</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($pickingList->pickingItems as $item)
                                                                <tr>
                                                                    <td>
                                                                        <strong>{{ $item->product->name ?? 'Unknown Product' }}</strong>
                                                                        @if($item->product && $item->product->sku)
                                                                            <br><small class="text-muted">{{ $item->product->sku }}</small>
                                                                        @endif
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge bg-info">{{ $item->quantity_requested }}</span>
                                                                    </td>
                                                                    <td>
                                                                        <input type="number" 
                                                                               name="picked_quantities[{{ $item->id }}]" 
                                                                               class="form-control form-control-sm" 
                                                                               value="{{ $item->quantity_requested }}" 
                                                                               min="0" 
                                                                               max="{{ $item->quantity_requested }}"
                                                                               style="width: 100px;">
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label for="notes" class="form-label">Processing Notes (Optional)</label>
                                                <textarea name="notes" id="notes" class="form-control" rows="2" 
                                                          placeholder="Add any notes about the receiving process..."></textarea>
                                            </div>

                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-success">
                                                    <i class="bi bi-check-circle me-1"></i>Process Picking List
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" onclick="setAllToRequested()">
                                                    <i class="bi bi-arrow-clockwise me-1"></i>Reset All to Requested
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @elseif($pickingList->status === 'completed')
                            <div class="alert alert-success mt-4">
                                <i class="bi bi-check-circle me-2"></i>
                                <strong>Picking Completed!</strong> 
                                This picking list has been successfully processed and all stock movements have been recorded.
                                @if($pickingList->completed_at)
                                    <br><small>Completed on: {{ $pickingList->completed_at->format('M d, Y \a\t g:i A') }}</small>
                                @endif
                            </div>
                        @endif
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-box text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3">No Items Found</h5>
                            <p class="text-muted">This picking list doesn't have any items.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Supply Information Sidebar -->
        <div class="col-md-4">
            @if($pickingList->supply)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-truck me-2"></i>Supply Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Supply Number:</strong><br>
                            <span class="fs-6">{{ $pickingList->supply->supply_number }}</span>
                        </div>

                        @if($pickingList->supply->vendor)
                            <div class="mb-3">
                                <strong>Vendor:</strong><br>
                                <i class="bi bi-building me-1"></i>{{ $pickingList->supply->vendor->name }}
                                @if($pickingList->supply->vendor->email)
                                    <br><small class="text-muted">{{ $pickingList->supply->vendor->email }}</small>
                                @endif
                            </div>
                        @endif

                        <div class="mb-3">
                            <strong>Supply Date:</strong><br>
                            {{ $pickingList->supply->supply_date->format('M d, Y') }}
                        </div>

                        @if($pickingList->supply->total_cost)
                            <div class="mb-3">
                                <strong>Total Cost:</strong><br>
                                <span class="fs-5 text-success">${{ number_format($pickingList->supply->total_cost, 2) }}</span>
                            </div>
                        @endif

                        <div class="mb-3">
                            <strong>Supply Status:</strong><br>
                            @switch($pickingList->supply->status)
                                @case('pending')
                                    <span class="badge bg-warning">Pending</span>
                                    @break
                                @case('processing')
                                    <span class="badge bg-info">Processing</span>
                                    @break
                                @case('completed')
                                    <span class="badge bg-success">Completed</span>
                                    @break
                                @default
                                    <span class="badge bg-secondary">{{ ucfirst($pickingList->supply->status) }}</span>
                            @endswitch
                        </div>

                        @if($pickingList->supply->notes)
                            <div class="mb-3">
                                <strong>Supply Notes:</strong><br>
                                <div class="bg-light p-2 rounded small">{{ $pickingList->supply->notes }}</div>
                            </div>
                        @endif

                        <div class="d-grid">
                            <a href="{{ route('supplies.show', $pickingList->supply) }}" class="btn btn-outline-info">
                                <i class="bi bi-truck me-1"></i> View Full Supply Details
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Supply Items Summary -->
                                        @if($pickingList->supply->items && $pickingList->supply->items->count() > 0)
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-list-ul me-2"></i>Supply Items Summary
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Qty</th>
                                            <th>Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pickingList->supply->items->take(5) as $supplyItem)
                                            <tr>
                                                <td>
                                                    <small>{{ $supplyItem->product->name ?? 'Unknown' }}</small>
                                                </td>
                                                <td>
                                                    <small>{{ $supplyItem->quantity }}</small>
                                                </td>
                                                <td>
                                                    <small>${{ number_format($supplyItem->unit_cost, 2) }}</small>
                                                </td>
                                            </tr>
                                        @endforeach
                                        @if($pickingList->supply->items->count() > 5)
                                            <tr>
                                                <td colspan="3" class="text-center">
                                                    <small class="text-muted">
                                                        ... and {{ $pickingList->supply->items->count() - 5 }} more items
                                                    </small>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            @else
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                        <h6 class="mt-3">No Supply Information</h6>
                        <p class="text-muted small">This picking list is not associated with a supply record.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
function setAllToRequested() {
    // Get all quantity inputs and set them to their max value (requested quantity)
    const quantityInputs = document.querySelectorAll('input[name^="picked_quantities"]');
    quantityInputs.forEach(input => {
        input.value = input.getAttribute('max');
    });
}
</script>

@endsection 
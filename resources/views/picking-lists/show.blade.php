@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-arrow-left-right me-2"></i>Stock Transfer Details</h1>
    <a href="{{ route('picking-lists.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Transfers
    </a>
</div>
@endsection

@section('content')


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

<!-- Transfer Overview Banner -->
<div class="alert alert-info d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-info-circle me-2"></i>
    <div>
        <strong>Transfer {{ $pickingList->picking_number }}:</strong> 
        Moving stock from <strong>{{ $pickingList->fromLocation->name }}</strong> (Warehouse) 
        to <strong>{{ $pickingList->toLocation->name }}</strong> (Retailer)
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clipboard-data me-2"></i>Transfer Information
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th>Transfer Number:</th>
                        <td><strong>{{ $pickingList->picking_number }}</strong></td>
                    </tr>
                    <tr>
                        <th>Transfer Type:</th>
                        <td>
                            <span class="badge bg-info">
                                <i class="bi bi-arrow-right me-1"></i>Warehouse to Retailer
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>From Warehouse:</th>
                        <td>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-house-fill me-2 text-primary"></i>
                                <div>
                                    <strong>{{ $pickingList->fromLocation->name }}</strong>
                                    @if($pickingList->fromLocation->address)
                                        <br><small class="text-muted">{{ $pickingList->fromLocation->address }}</small>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>To Retailer:</th>
                        <td>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-shop me-2 text-success"></i>
                                <div>
                                    <strong>{{ $pickingList->toLocation->name }}</strong>
                                    @if($pickingList->toLocation->address)
                                        <br><small class="text-muted">{{ $pickingList->toLocation->address }}</small>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge bg-{{ $pickingList->status === 'completed' ? 'success' : ($pickingList->status === 'in_progress' ? 'primary' : ($pickingList->status === 'pending' ? 'warning' : 'danger')) }}">
                                {{ ucfirst(str_replace('_', ' ', $pickingList->status)) }}
                            </span>
                            @if($pickingList->picked_by)
                                <br><small class="text-muted">Handled by: {{ $pickingList->picked_by }}</small>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Transfer Date:</th>
                        <td>{{ $pickingList->picking_date->format('M d, Y H:i') }}</td>
                    </tr>
                    @if($pickingList->completed_at)
                    <tr>
                        <th>Completed At:</th>
                        <td>{{ $pickingList->completed_at->format('M d, Y H:i') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <th>Progress:</th>
                        <td>
                            <div class="progress mb-2" style="height: 20px;">
                                <div class="progress-bar bg-{{ $pickingList->progress_percentage == 100 ? 'success' : ($pickingList->progress_percentage > 0 ? 'warning' : 'secondary') }}" 
                                     role="progressbar" 
                                     style="width: {{ $pickingList->progress_percentage }}%">
                                    {{ number_format($pickingList->progress_percentage, 0) }}%
                                </div>
                            </div>
                            <small class="text-muted">
                                {{ $pickingList->picked_items }} of {{ $pickingList->total_items }} items transferred
                            </small>
                        </td>
                    </tr>
                    @if($pickingList->notes)
                    <tr>
                        <th>Notes:</th>
                        <td>{{ $pickingList->notes }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center bg-light">
                <h5 class="card-title mb-0">
                    <i class="bi bi-box-seam me-2"></i>Items to Transfer
                </h5>
                @if(!$pickingList->isCompleted() && $pickingList->status !== 'cancelled')
                <form action="{{ route('picking-lists.update-status', $pickingList) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-success" {{ $pickingList->progress_percentage < 100 ? 'disabled' : '' }}>
                        <i class="bi bi-check-circle me-1"></i>Complete Transfer
                    </button>
                </form>
                @endif
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Quantity to Transfer</th>
                                <th>Transferred Quantity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pickingList->pickingItems as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item->product->name }}</strong>
                                    @if($item->product->description)
                                        <br><small class="text-muted">{{ Str::limit($item->product->description, 50) }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-secondary">{{ $item->quantity_requested }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $item->quantity_picked == $item->quantity_requested ? 'success' : ($item->quantity_picked > 0 ? 'warning' : 'light text-dark') }}">
                                        {{ $item->quantity_picked }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $item->status === 'completed' ? 'success' : ($item->status === 'partial' ? 'warning' : 'secondary') }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if($item->status !== 'completed' && !$pickingList->isCompleted() && $pickingList->status !== 'cancelled')
                                    <form action="{{ route('picking-items.update', $item) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-success" title="Mark as transferred">
                                            <i class="bi bi-check-lg"></i> Mark Transferred
                                        </button>
                                    </form>
                                    @else
                                        @if($item->status === 'completed')
                                            <span class="text-success">
                                                <i class="bi bi-check-circle-fill"></i> Transferred
                                            </span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                @if($pickingList->status === 'completed')
                <div class="alert alert-success mt-3" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>Transfer Completed!</strong> All items have been successfully transferred from {{ $pickingList->fromLocation->name }} to {{ $pickingList->toLocation->name }}.
                </div>
                @elseif($pickingList->status === 'cancelled')
                <div class="alert alert-danger mt-3" role="alert">
                    <i class="bi bi-x-circle-fill me-2"></i>
                    <strong>Transfer Cancelled!</strong> This stock transfer has been cancelled.
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection 
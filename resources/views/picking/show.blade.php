@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="mb-4">Picking List Details</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('picking.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to List
            </a>
        </div>
    </div>

    <!-- Picking List Information -->
    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0">
                <i class="bi bi-box me-2"></i>Picking List #{{ $pickingList->picking_number }}
            </h2>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm">
                        <tr>
                            <th>Status:</th>
                            <td>
                                <span class="badge bg-{{ $pickingList->status === 'completed' ? 'success' : ($pickingList->status === 'in_progress' ? 'warning' : 'secondary') }}">
                                    {{ ucfirst($pickingList->status) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Type:</th>
                            <td>{{ ucwords(str_replace('_', ' ', $pickingList->picking_type)) }}</td>
                        </tr>
                        <tr>
                            <th>Created:</th>
                            <td>{{ $pickingList->created_at ? $pickingList->created_at->format('M d, Y H:i') : 'N/A' }}</td>
                        </tr>
                        @if($pickingList->completed_at)
                        <tr>
                            <th>Completed:</th>
                            <td>{{ $pickingList->completed_at ? $pickingList->completed_at->format('M d, Y H:i') : 'N/A' }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm">
                        <tr>
                            <th>From Location:</th>
                            <td>
                                @if($pickingList->fromLocation)
                                    <span class="badge bg-primary">{{ $pickingList->fromLocation->name }}</span>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>To Location:</th>
                            <td>
                                @if($pickingList->toLocation)
                                    <span class="badge bg-success">{{ $pickingList->toLocation->name }}</span>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                        </tr>
                        @if($pickingList->order)
                        <tr>
                            <th>Order:</th>
                            <td>
                                <a href="{{ route('orders.show', $pickingList->order) }}" class="text-decoration-none">
                                    Order #{{ $pickingList->order->id }}
                                </a>
                            </td>
                        </tr>
                        @endif
                        @if($pickingList->supply)
                        <tr>
                            <th>Supply:</th>
                            <td>
                                <a href="{{ route('supplies.show', $pickingList->supply) }}" class="text-decoration-none">
                                    Supply #{{ $pickingList->supply->id }}
                                </a>
                            </td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Picking Items -->
    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0">
                <i class="bi bi-list-check me-2"></i>Picking Items
            </h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Requested Qty</th>
                            <th>Picked Qty</th>
                            <th>Status</th>
                            @if($pickingList->status !== 'completed')
                            <th>Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pickingList->pickingItems as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->product->name }}</strong>
                                <br>
                                <small class="text-muted">SKU: {{ $item->product->sku }}</small>
                            </td>
                            <td>{{ $item->quantity_requested }}</td>
                            <td>{{ $item->quantity_picked }}</td>
                            <td>
                                <span class="badge bg-{{ $item->status === 'completed' ? 'success' : ($item->status === 'partial' ? 'warning' : 'secondary') }}">
                                    {{ ucfirst($item->status) }}
                                </span>
                            </td>
                            @if($pickingList->status !== 'completed')
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#updateItem{{ $item->id }}">
                                    Update Quantity
                                </button>
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $pickingList->status === 'completed' ? '4' : '5' }}" class="text-center">No items found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($pickingList->notes)
    <!-- Notes -->
    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0">
                <i class="bi bi-pencil me-2"></i>Notes
            </h2>
        </div>
        <div class="card-body">
            {{ $pickingList->notes }}
        </div>
    </div>
    @endif
</div>

<!-- Update Item Quantity Modals -->
@foreach($pickingList->pickingItems as $item)
<div class="modal fade" id="updateItem{{ $item->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Quantity: {{ $item->product->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('picking.update-item-quantity', [$pickingList, $item]) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label">Quantity Picked</label>
                        <input type="number" class="form-control" name="quantity_picked" value="{{ $item->quantity_picked }}" min="0" max="{{ $item->quantity_requested }}" required>
                        <small class="text-muted">Maximum: {{ $item->quantity_requested }}</small>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endforeach
@endsection 
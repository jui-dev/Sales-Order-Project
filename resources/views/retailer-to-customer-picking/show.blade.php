@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">
                            <i class="bi bi-box-seam me-2"></i>Picking List Details
                        </h3>
                        <div class="card-tools">
                            <a href="{{ route('retailer-to-customer-picking.index') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Back to List
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
                        </div>
                    @endif

                    <!-- Picking List Information -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-info-circle me-2"></i>Picking Information
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <th>Picking Number:</th>
                                            <td>
                                                <strong>{{ $pickingList->picking_number }}</strong>
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
                                            <th>Picking Date:</th>
                                            <td>{{ $pickingList->picking_date->format('M d, Y H:i') }}</td>
                                        </tr>
                                        <tr>
                                            <th>Retailer:</th>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-shop me-2 text-success"></i>
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
                                                    {{ $pickingList->pickingItems->sum('quantity_picked') }} of {{ $pickingList->pickingItems->sum('quantity_requested') }} items picked
                                                </small>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-cart me-2"></i>Order Information
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <th>Order Number:</th>
                                            <td>
                                                @if($pickingList->order)
                                                    <a href="{{ route('orders.show', $pickingList->order) }}" class="text-primary">
                                                        #{{ $pickingList->order->id }}
                                                    </a>
                                                @else
                                                    N/A
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Customer:</th>
                                            <td>
                                                @if($pickingList->order && $pickingList->order->customer)
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-person me-2 text-primary"></i>
                                                        <div>
                                                            <strong>{{ $pickingList->order->customer->name }}</strong>
                                                            @if($pickingList->order->customer->email)
                                                                <br><small class="text-muted">{{ $pickingList->order->customer->email }}</small>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @else
                                                    N/A
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Order Date:</th>
                                            <td>
                                                @if($pickingList->order)
                                                    {{ $pickingList->order->order_date->format('M d, Y') }}
                                                @else
                                                    N/A
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Order Status:</th>
                                            <td>
                                                @if($pickingList->order)
                                                    <span class="badge bg-{{ $pickingList->order->status === 'completed' ? 'success' : 'info' }}">
                                                        {{ ucfirst($pickingList->order->status) }}
                                                    </span>
                                                @else
                                                    N/A
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Picking Items -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-list-check me-2"></i>Picking Items
                                </h5>
                                @if(!$pickingList->isCompleted() && $pickingList->status !== 'cancelled')
                                    <form action="{{ route('retailer-to-customer-picking.complete', $pickingList) }}" 
                                          method="POST" 
                                          class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" 
                                                class="btn btn-success" 
                                                {{ $pickingList->progress_percentage < 100 ? 'disabled' : '' }}
                                                onclick="return confirm('Are you sure you want to mark this picking list as completed?')">
                                            <i class="bi bi-check-circle me-1"></i>Complete Picking
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Product</th>
                                            <th>SKU</th>
                                            <th>Requested Qty</th>
                                            <th>Picked Qty</th>
                                            <th>Status</th>
                                            @if($pickingList->status !== 'completed')
                                                <th>Actions</th>
                                            @endif
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
                                                <td>{{ $item->product->sku }}</td>
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
                                                @if($pickingList->status !== 'completed')
                                                    <td>
                                                        @if($item->status !== 'completed')
                                                            <form action="{{ route('retailer-to-customer-picking.update-item', [$pickingList, $item]) }}" 
                                                                  method="POST" 
                                                                  class="d-inline">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button type="submit" 
                                                                        class="btn btn-sm btn-success" 
                                                                        title="Mark as picked">
                                                                    <i class="bi bi-check-lg"></i> Mark Picked
                                                                </button>
                                                            </form>
                                                        @else
                                                            <span class="text-success">
                                                                <i class="bi bi-check-circle-fill"></i> Picked
                                                            </span>
                                                        @endif
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Add any JavaScript functionality here if needed
</script>
@endsection 
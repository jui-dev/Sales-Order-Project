@extends('layouts.app')

@section('content')
<div class="container">
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

    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h4>Transfer Details - {{ $pickingList->picking_number }}</h4>
                    <p class="mb-0 text-muted">
                        From {{ $pickingList->fromLocation->name }} to {{ $pickingList->toLocation->name }}
                    </p>
                </div>

                <div class="card-body">
                    <!-- Transfer Information -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Transfer Information</h6>
                            <ul class="list-unstyled">
                                <li><strong>Transfer #:</strong> {{ $pickingList->picking_number }}</li>
                                <li><strong>From:</strong> {{ $pickingList->fromLocation->name }}</li>
                                <li><strong>To:</strong> {{ $pickingList->toLocation->name }}</li>
                                <li><strong>Status:</strong> 
                                    @if($pickingList->status === 'pending')
                                        <span class="badge badge-warning">Pending</span>
                                    @elseif($pickingList->status === 'completed')
                                        <span class="badge badge-success">Completed</span>
                                    @elseif($pickingList->status === 'cancelled')
                                        <span class="badge badge-danger">Cancelled</span>
                                    @else
                                        <span class="badge badge-secondary">{{ ucfirst($pickingList->status) }}</span>
                                    @endif
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Dates</h6>
                            <ul class="list-unstyled">
                                <li><strong>Created:</strong> {{ $pickingList->created_at->format('M d, Y H:i') }}</li>
                                <li><strong>Picking Date:</strong> {{ $pickingList->picking_date->format('M d, Y H:i') }}</li>
                                @if($pickingList->completed_at)
                                    <li><strong>Completed:</strong> {{ $pickingList->completed_at->format('M d, Y H:i') }}</li>
                                @endif
                            </ul>
                        </div>
                    </div>

                    @if($pickingList->notes)
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h6>Notes</h6>
                            <p class="text-muted">{{ $pickingList->notes }}</p>
                        </div>
                    </div>
                    @endif

                    <!-- Items Section -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h6>Items ({{ $pickingList->pickingItems->count() }})</h6>
                            
                            @if($pickingList->status === 'pending')
                                <!-- Editable form for pending transfers -->
                                <form action="{{ route('stock-transfers.warehouse-to-retailer.process', $pickingList) }}" method="POST">
                                    @csrf
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
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
                                                    <td>{{ $item->product->name }}</td>
                                                    <td>{{ $item->quantity_requested }}</td>
                                                    <td>
                                                        <input type="hidden" name="items[{{ $loop->index }}][picking_item_id]" value="{{ $item->id }}">
                                                        <input type="number" 
                                                               name="items[{{ $loop->index }}][quantity_picked]" 
                                                               class="form-control" 
                                                               value="{{ $item->quantity_requested }}" 
                                                               min="0" 
                                                               max="{{ $item->quantity_requested }}" 
                                                               required>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-warning">{{ ucfirst($item->status) }}</span>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="notes">Processing Notes</label>
                                        <textarea name="notes" id="notes" class="form-control" rows="2" 
                                                  placeholder="Optional notes for this transfer processing..."></textarea>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-check"></i> Complete Transfer
                                        </button>
                                    </div>
                                </form>
                            @else
                                <!-- Read-only view for completed/cancelled transfers -->
                                <div class="table-responsive">
                                    <table class="table table-bordered">
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
                                                <td>{{ $item->product->name }}</td>
                                                <td>{{ $item->quantity_requested }}</td>
                                                <td>{{ $item->quantity_picked }}</td>
                                                <td>
                                                    @if($item->status === 'completed')
                                                        <span class="badge badge-success">Completed</span>
                                                    @elseif($item->status === 'partial')
                                                        <span class="badge badge-warning">Partial</span>
                                                    @elseif($item->status === 'cancelled')
                                                        <span class="badge badge-danger">Cancelled</span>
                                                    @else
                                                        <span class="badge badge-secondary">{{ ucfirst($item->status) }}</span>
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

                    <!-- Action Buttons -->
                    <div class="row">
                        <div class="col-md-12">
                            @if($pickingList->status === 'pending')
                                <form action="{{ route('stock-transfers.warehouse-to-retailer.quick-complete', $pickingList) }}" 
                                      method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-success" 
                                            onclick="return confirm('Complete transfer with all requested quantities?')">
                                        <i class="fas fa-check-double"></i> Quick Complete All
                                    </button>
                                </form>
                                
                                <form action="{{ route('stock-transfers.warehouse-to-retailer.cancel', $pickingList) }}" 
                                      method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-danger" 
                                            onclick="return confirm('Cancel this transfer? This action cannot be undone.')">
                                        <i class="fas fa-times"></i> Cancel Transfer
                                    </button>
                                </form>
                            @endif
                            
                            <a href="{{ route('stock-transfers.warehouse-to-retailer') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Transfers
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Supply #{{ $supply->formatted_id ?? $supply->id }}</h1>
    <div>
        <a href="{{ route('supplies.index') }}" class="btn btn-secondary">Back to Supplies</a>
    </div>
</div>
@endsection

@section('content')


<!-- Status Alert -->
@if($supply->status === 'pending')
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Supply Pending</strong> 
        This supply is currently pending. Mark it as completed to proceed to the GRN (Goods Received Note) process.
    </div>
@elseif($supply->status === 'completed')
    <div class="alert alert-success">
        <i class="bi bi-check-circle me-2"></i>
        <strong>Supply Completed</strong> 
        This supply has been marked as completed. You can now proceed to the GRN process.
        @if($supply->grn)
            <a href="{{ route('grns.show', $supply->grn) }}" class="btn btn-sm btn-primary ms-2">View GRN</a>
        @endif
    </div>
@endif

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Supply Details</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <th>Vendor:</th>
                        <td>{{ $supply->vendor->name }}</td>
                    </tr>
                    <tr>
                        <th>Supply Date:</th>
                        <td>{{ $supply->supply_date->format('M d, Y') }}</td>
                    </tr>
                    <tr>
                        <th>Total Products:</th>
                        <td>{{ $supply->items->count() }}</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <th>Status:</th>
                        <td>
                            @php
                                $statusBadge = match($supply->status) {
                                    'pending'    => 'warning',
                                    'processing' => 'primary',
                                    'confirmed'  => 'info',
                                    'completed'  => 'success',
                                    default      => 'secondary'
                                };
                            @endphp
                            <span class="badge bg-{{ $statusBadge }}">{{ ucfirst($supply->status) }}</span>
                            
                            @if($supply->status != 'completed')
                            <div class="dropdown d-inline ms-2">
                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    Change Status
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <form action="{{ route('supplies.completed', $supply) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="dropdown-item">
                                                <i class="bi bi-check-circle me-1"></i> Mark Completed
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Total Items:</th>
                        <td>{{ $supply->items ? $supply->items->sum('quantity') : 0 }}</td>
                    </tr>
                    <tr>
                        <th>Total Cost:</th>
                        <td>${{ number_format($supply->total_cost, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="mt-4">
            <h5><i class="bi bi-box me-1"></i> Supplied Products</h5>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-center">Unit Cost</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($supply->items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <strong>{{ $item->product->name }}</strong>
                            @if($item->product->description)
                                <br><small class="text-muted">{{ $item->product->description }}</small>
                            @endif
                        </td>
                        <td>{{ $item->product->sku ?? 'N/A' }}</td>
                        <td class="text-center">
                            <span class="badge bg-primary">{{ number_format($item->quantity) }}</span>
                        </td>
                        <td class="text-center">${{ number_format($item->unit_cost, 2) }}</td>
                        <td class="text-end">${{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center">No items found.</td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="table-active">
                        <td colspan="5" class="text-end"><strong>Total Cost:</strong></td>
                        <td class="text-end"><strong>${{ number_format($supply->total_cost, 2) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        @if($supply->notes)
        <div class="mt-3">
            <h6><i class="bi bi-sticky me-1"></i> Notes:</h6>
            <p>{{ $supply->notes }}</p>
        </div>
        @endif

        @if($supply->grn)
        <div class="mt-4">
            <h5><i class="bi bi-receipt me-1"></i> Goods Receipt Note</h5>
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>GRN #:</th>
                                    <td>{{ $supply->grn->formatted_id ?? $supply->grn->id }}</td>
                                </tr>
                                <tr>
                                    <th>Received Date:</th>
                                    <td>{{ $supply->grn->received_date->format('M d, Y') }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        @php
                                            $grnBadge = match($supply->grn->status) {
                                                'draft' => 'secondary',
                                                'verified' => 'warning',
                                                'posted' => 'success',
                                                default => 'secondary'
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $grnBadge }}">{{ ucfirst($supply->grn->status) }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Actions:</th>
                                    <td>
                                        <a href="{{ route('grns.show', $supply->grn) }}" class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye me-1"></i> View GRN
                                        </a>
                                        @if($supply->grn->status !== 'posted')
                                            <form action="{{ route('grns.update-status', $supply->grn) }}" method="POST" class="d-inline ms-2">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="bi bi-check-circle me-1"></i> Post GRN
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection 
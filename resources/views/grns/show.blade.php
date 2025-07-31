@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Goods Received Note #{{ $grn->formatted_id ?? $grn->id }}</h1>
        <div>
            <a href="{{ route('grns.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
            @if($grn->status === 'draft')
                <form action="{{ route('grns.update-status', $grn) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Post GRN
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- GRN Meta Information -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong><i class="bi bi-truck me-1"></i> Vendor Information</strong>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ $grn->supply->vendor->name }}</strong></p>
                    <small class="text-muted">{{ $grn->supply->vendor->address }}</small>
                    @if($grn->supply->vendor->contact_person)
                        <br><small class="text-muted">Contact: {{ $grn->supply->vendor->contact_person }}</small>
                    @endif
                    @if($grn->supply->vendor->phone)
                        <br><small class="text-muted">Phone: {{ $grn->supply->vendor->phone }}</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong><i class="bi bi-building me-1"></i> Receiving Location</strong>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ $grn->supply->warehouse->name }}</strong></p>
                    <small class="text-muted">{{ $grn->supply->warehouse->address }}</small>
                    @if($grn->supply->warehouse->contact_person)
                        <br><small class="text-muted">Contact: {{ $grn->supply->warehouse->contact_person }}</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong><i class="bi bi-info-circle me-1"></i> GRN Details</strong>
                </div>
                <div class="card-body">
                    @php
                        $badge = [
                            'draft'  => 'secondary',
                            'posted' => 'success',
                        ][$grn->status] ?? 'secondary';
                    @endphp
                    <p class="mb-1">Status: 
                        <span class="badge bg-{{ $badge }}">{{ ucfirst($grn->status) }}</span>
                    </p>
                    <p class="mb-1">Received Date: {{ optional($grn->received_date)->format('M d, Y') }}</p>
                    <p class="mb-1">Supply Reference: <a href="{{ route('supplies.show', $grn->supply_id) }}">#{{ $grn->supply_id }}</a></p>
                    <p class="mb-1">Total Items: {{ $grn->supply && $grn->supply->items ? $grn->supply->items->sum('quantity') : 0 }}</p>
                    <p class="mb-1">Total Products: {{ $grn->supply && $grn->supply->items ? $grn->supply->items->count() : 0 }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- GRN Items Received -->
    <div class="card">
        <div class="card-header">
            <strong><i class="bi bi-box me-1"></i> Items Received</strong>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th class="text-center">Quantity Received</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($grn->supply->items as $index => $item)
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
                                <td>
                                    <small class="text-muted">
                                        Received on {{ $grn->received_date->format('M d, Y') }}
                                    </small>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">No items found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="table-active">
                            <td colspan="3" class="text-end"><strong>Total Items Received:</strong></td>
                            <td class="text-center"><strong>{{ number_format($grn->supply && $grn->supply->items ? $grn->supply->items->sum('quantity') : 0) }}</strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Notes Section -->
    @if($grn->supply->notes)
        <div class="card mt-4">
            <div class="card-header">
                <strong><i class="bi bi-sticky me-1"></i> Notes</strong>
            </div>
            <div class="card-body">
                <p class="mb-0">{{ $grn->supply->notes }}</p>
            </div>
        </div>
    @endif

    <!-- Status Information -->
    @if($grn->status === 'posted')
        <div class="alert alert-success mt-4">
            <i class="bi bi-check-circle me-2"></i>
            <strong>GRN Posted Successfully!</strong> 
            This GRN has been posted and stock has been updated. A supplier bill has been generated automatically.
        </div>
    @elseif($grn->status === 'draft')
        <div class="alert alert-info mt-4">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Draft GRN</strong> 
            This GRN is in draft status. Click "Post GRN" to finalize the receiving process and update stock levels.
        </div>
    @endif
</div>
@endsection 
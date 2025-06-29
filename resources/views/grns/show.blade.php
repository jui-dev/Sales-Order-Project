@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>GRN #{{ $grn->id }}</h1>
        <div>
            <a href="{{ route('grns.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
            @if($grn->status !== 'posted')
                <form action="{{ route('grns.update-status', $grn) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-primary">
                        Mark {{ $grn->status === 'draft' ? 'Verified' : 'Posted' }}
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- GRN Meta -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light"><strong>Vendor</strong></div>
                <div class="card-body">
                    <p class="mb-1">{{ $grn->supply->vendor->name }}</p>
                    <small class="text-muted">{{ $grn->supply->vendor->address }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light"><strong>Warehouse</strong></div>
                <div class="card-body">
                    <p class="mb-1">{{ $grn->supply->warehouse->name }}</p>
                    <small class="text-muted">{{ $grn->supply->warehouse->address }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light"><strong>Details</strong></div>
                <div class="card-body">
                    <p class="mb-1">Status: 
                        <span class="badge bg-{{ match($grn->status){
                            'draft'    => 'secondary',
                            'verified' => 'warning',
                            'posted'   => 'success',
                            default    => 'secondary'
                        }}}">{{ ucfirst($grn->status) }}</span>
                    </p>
                    <p class="mb-1">Received Date: {{ optional($grn->received_date)->format('M d, Y') ?? '-' }}</p>
                    <p class="mb-1">Supply: <a href="{{ route('supplies.show', $grn->supply_id) }}">#{{ $grn->supply_id }}</a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- GRN Items -->
    <div class="card">
        <div class="card-header"><strong>Items</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($grn->supply->items as $item)
                            <tr>
                                <td>{{ $item->product->name }}</td>
                                <td class="text-end">{{ number_format($item->quantity) }}</td>
                                <td class="text-end">${{ number_format($item->unit_cost, 2) }}</td>
                                <td class="text-end">${{ number_format($item->unit_cost * $item->quantity, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-end">Total Cost</th>
                            <th class="text-end">${{ number_format($grn->supply->total_cost ?? $grn->supply->items->sum(fn($i)=>$i->unit_cost*$i->quantity), 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection 
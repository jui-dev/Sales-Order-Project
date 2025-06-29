@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Supply #{{ $supply->id }}</h1>
    <div>
        {{-- Edit route not implemented --}}
        <a href="{{ route('supplies.index') }}" class="btn btn-secondary">Back to Supplies</a>
    </div>
</div>

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
                            <span class="text-{{ match($supply->status) {
                                'pending'    => 'warning',
                                'processing' => 'primary',
                                'confirmed'  => 'info',
                                'completed'  => 'success',
                                default      => 'secondary'
                            } }}">
                                {{ ucfirst($supply->status) }}
                            </span>
                            
                            @if($supply->status != 'completed')
                            <div class="dropdown d-inline ms-2">
                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    Change
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <form action="{{ route('supplies.confirm', $supply) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="dropdown-item">Confirm Supply</button>
                                        </form>
                                    </li>
                                    <li>
                                        <form action="{{ route('supplies.completed', $supply) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="dropdown-item">Mark Completed (Override)</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Total Items:</th>
                        <td>{{ $supply->items->sum('quantity') }}</td>
                    </tr>
                    <tr>
                        <th>Total Cost:</th>
                        <td>${{ number_format($supply->total_cost, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="mt-4">
            <h5>Supplied Products</h5>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Unit Cost</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($supply->items as $item)
                    <tr>
                        <td>{{ $item->product->name }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>${{ number_format($item->unit_cost, 2) }}</td>
                        <td>${{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        @if($supply->notes)
        <div class="mt-3">
            <h6>Notes:</h6>
            <p>{{ $supply->notes }}</p>
        </div>
        @endif

        @if($supply->grn)
        <div class="mt-4">
            <h5>Goods Receipt Note</h5>
            <table class="table table-borderless w-auto">
                <tr>
                    <th>GRN #:</th>
                    <td>{{ $supply->grn->id }}</td>
                </tr>
                <tr>
                    <th>Received Date:</th>
                    <td>{{ $supply->grn->received_date->format('M d, Y') }}</td>
                </tr>
                <tr>
                    <th>Status:</th>
                    <td>
                        <span class="badge bg-{{ $supply->grn->status == 'draft' ? 'secondary' : ($supply->grn->status == 'verified' ? 'warning' : 'success') }}">
                            {{ ucfirst($supply->grn->status) }}
                        </span>

                        @if($supply->grn->status !== 'posted')
                            <form action="{{ route('grns.update-status', $supply->grn) }}" method="POST" class="d-inline ms-2">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    Mark {{ $supply->grn->status === 'draft' ? 'Verified' : 'Posted' }}
                                </button>
                            </form>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection 
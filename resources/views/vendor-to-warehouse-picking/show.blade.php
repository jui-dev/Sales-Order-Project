@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <x-breadcrumb :items="[
        ['label' => 'Picking & Transfers', 'url' => '#'],
        ['label' => 'Vendors to Warehouse', 'url' => route('vendor-to-warehouse-picking.index')],
        ['label' => $pickingList->picking_number, 'url' => '#']
    ]" />
    
    <div class="mb-4">
        <a href="{{ route('vendor-to-warehouse-picking.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back to List
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0">
                <i class="bi bi-list-check me-2"></i>Picking List Details
            </h2>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h3 class="h6 text-muted mb-3">Basic Information</h3>
                    <dl class="row">
                        <dt class="col-sm-4">Picking Number</dt>
                        <dd class="col-sm-8">{{ $pickingList->picking_number }}</dd>

                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-{{ $pickingList->status === 'completed' ? 'success' : 'warning' }}">
                                {{ ucfirst($pickingList->status) }}
                            </span>
                        </dd>

                        <dt class="col-sm-4">Picking Date</dt>
                        <dd class="col-sm-8">{{ $pickingList->picking_date->format('M d, Y H:i') }}</dd>

                        @if($pickingList->completed_at)
                            <dt class="col-sm-4">Completed At</dt>
                            <dd class="col-sm-8">{{ $pickingList->completed_at->format('M d, Y H:i') }}</dd>
                        @endif
                    </dl>
                </div>

                <div class="col-md-6">
                    <h3 class="h6 text-muted mb-3">Supply Information</h3>
                    <dl class="row">
                        <dt class="col-sm-4">Supply Number</dt>
                        <dd class="col-sm-8">{{ $pickingList->supply->supply_number }}</dd>

                        <dt class="col-sm-4">Vendor</dt>
                        <dd class="col-sm-8">{{ $pickingList->supply->vendor->name }}</dd>

                        <dt class="col-sm-4">Warehouse</dt>
                        <dd class="col-sm-8">{{ $pickingList->toLocation->name }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Picking Items -->
    <div class="card">
        <div class="card-header">
            <h2 class="h5 mb-0">
                <i class="bi bi-box me-2"></i>Picked Items
            </h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Quantity Requested</th>
                            <th>Quantity Picked</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pickingList->pickingItems as $item)
                            <tr>
                                <td>{{ $item->product->name }}</td>
                                <td>{{ $item->product->sku }}</td>
                                <td>{{ $item->quantity_requested }}</td>
                                <td>{{ $item->quantity_picked }}</td>
                                <td>
                                    <span class="badge bg-{{ $item->status === 'completed' ? 'success' : 'warning' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection 
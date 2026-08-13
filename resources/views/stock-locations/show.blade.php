@php
    // Ensure we have the correct data types
    $location = $location ?? null;
    if (!$location) {
        abort(404, 'Location not found');
    }
@endphp

@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <a href="{{ route('stock-locations.index') }}" class="btn btn-secondary mb-3">
        <i class="bi bi-chevron-left"></i> Back to Locations
    </a>

    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">
                {{ $location->name }}
                <span class="badge bg-info text-dark text-uppercase">{{ ucfirst($location->location_type) }}</span>
            </h1>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Contact Details</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <i class="bi bi-person me-2"></i>
                        <span class="text-muted">Contact Person:</span>
                        <span>{{ $location->contact_person ?? 'N/A' }}</span>
                    </div>
                    <div class="mb-3">
                        <i class="bi bi-telephone me-2"></i>
                        <span class="text-muted">Phone:</span>
                        <span>{{ $location->contact_number ?? 'N/A' }}</span>
                    </div>
                    <div class="mb-3">
                        <i class="bi bi-envelope me-2"></i>
                        <span class="text-muted">Email:</span>
                        <span>{{ $location->email ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="badge bg-{{ $location->status === 'active' ? 'success' : 'danger' }}">
                            {{ ucfirst($location->status) }}
                        </span>
                        @if($location->is_default)
                            <span class="badge bg-primary ms-1">Default</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h2>{{ $location->stockBalances->count() }}</h2>
                            <p class="text-muted mb-0">Products</p>
                        </div>
                        <div class="col-md-4">
                            <h2>{{ $location->stockBalances->sum('quantity') }}</h2>
                            <p class="text-muted mb-0">Total Stock</p>
                        </div>
                        <div class="col-md-4">
                            <h2>{{ $location->stockTransactions->count() }}</h2>
                            <p class="text-muted mb-0">Transactions</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Balances Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Product Stock Balances</h5>
        </div>
        <div class="card-body p-0">
            @if($location->stockBalances->isEmpty())
                <p class="p-3 mb-0 text-muted">No stock records found for this location.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Reserved</th>
                                <th class="text-end">Available</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($location->stockBalances as $balance)
                                <tr>
                                    <td>{{ $balance->product->name ?? '#' . $balance->product_id }}</td>
                                    <td class="text-end">{{ $balance->quantity }}</td>
                                    <td class="text-end">{{ $balance->reserved_quantity }}</td>
                                    <td class="text-end">{{ $balance->available_quantity }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Recent Transactions Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Recent Stock Transactions</h5>
        </div>
        <div class="card-body p-0">
            @if($location->stockTransactions->isEmpty())
                <p class="p-3 mb-0 text-muted">No transactions found for this location.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th class="text-end">Quantity</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($location->stockTransactions->take(50) as $txn)
                                <tr>
                                    <td>{{ $txn->transaction_date?->format('Y-m-d') ?? $txn->created_at->format('Y-m-d') }}</td>
                                    <td>{{ $txn->product->name ?? '#' . $txn->product_id }}</td>
                                    <td class="text-end">{{ $txn->quantity }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $txn->transaction_type)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
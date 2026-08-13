@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Vendor Details</h1>
    <div>
        <a href="{{ route('vendors.edit', $vendor) }}" class="btn btn-primary">Edit</a>
        <a href="{{ route('vendors.index') }}" class="btn btn-secondary">Back to Vendors</a>
    </div>
</div>
@endsection

@section('content')


<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h3>{{ $vendor->name }}</h3>
                <p class="text-muted">Vendor ID: {{ $vendor->id }}</p>
                
                <div class="mb-3">
                    <h5>Contact Person</h5>
                    <p>{{ $vendor->contact_person ?? 'N/A' }}</p>
                </div>
                
                <div class="mb-3">
                    <h5>Email</h5>
                    <p>{{ $vendor->email ?? 'N/A' }}</p>
                </div>
                
                <div class="mb-3">
                    <h5>Phone</h5>
                    <p>{{ $vendor->phone ?? 'N/A' }}</p>
                </div>
                
                <div class="mb-3">
                    <h5>Address</h5>
                    <p>{{ $vendor->address ?? 'N/A' }}</p>
                </div>
                
                <div class="mb-3">
                    <h5>Created At</h5>
                    <p>{{ $vendor->created_at ? $vendor->created_at->format('F d, Y') : 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<h2>Supply History</h2>
<div class="card">
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Supply ID</th>
                    <th>Supply Number</th>
                    <th>Status</th>
                    <th>Total Cost</th>
                    <th>Supply Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vendor->supplies as $supply)
                <tr>
                    <td>{{ $supply->id }}</td>
                    <td>{{ $supply->supply_number ?? 'N/A' }}</td>
                    <td>
                        <span class="badge bg-{{ $supply->status === 'completed' ? 'success' : ($supply->status === 'pending' ? 'warning' : 'secondary') }}">
                            {{ ucfirst($supply->status ?? 'Unknown') }}
                        </span>
                    </td>
                    <td>${{ number_format($supply->total_cost ?? 0, 2) }}</td>
                    <td>{{ $supply->supply_date ? $supply->supply_date->format('M d, Y') : 'N/A' }}</td>
                    <td>
                        <a href="{{ route('supplies.show', $supply) }}" class="btn btn-sm btn-info">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center">No supply records found for this vendor</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection 
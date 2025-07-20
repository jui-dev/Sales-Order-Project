@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <x-breadcrumb :items="[
        ['label' => 'Inventory', 'url' => '#'],
        ['label' => 'Supplies', 'url' => '#']
    ]" />
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Supplies</h1>
        <a href="{{ route('supplies.create') }}" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i> Record New Supply
        </a>
    </div>

<!-- Unified Search Component -->
<x-unified-search 
    :searchPlaceholder="'Search supplies by ID, vendor name, or product...'"
    :filterOptions="$filterOptions"
    :sortOptions="$sortOptions"
    :defaultSort="'id'"
    :defaultDirection="'desc'"
/>

<div class="card">
    <div class="card-body">

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Vendor</th>
                        <th>Warehouse</th>
                        <th>Products</th>
                        <th>Total Items</th>
                        <th>Total Cost</th>
                        <th>Supply Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($supplies as $supply)
                    <tr>
                        <td>{{ $supply->formatted_id ?? $supply->id }}</td>
                        <td>{{ $supply->vendor->name }}</td>
                        <td>{{ $supply->warehouse->name }}</td>
                        <td>
                            <ul class="list-unstyled mb-0">
                                @foreach($supply->items->take(2) as $item)
                                    <li>{{ $item->product->name }} ({{ $item->quantity }})</li>
                                @endforeach
                                @if($supply->items->count() > 2)
                                    <li class="text-muted">+ {{ $supply->items->count() - 2 }} more...</li>
                                @endif
                            </ul>
                        </td>
                        <td>{{ $supply->items->sum('quantity') }}</td>
                        <td>${{ number_format($supply->total_cost, 2) }}</td>
                        <td>{{ $supply->supply_date->format('M d, Y') }}</td>
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
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                <a href="{{ route('supplies.show', $supply) }}" class="btn btn-sm btn-info d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('supplies.edit', $supply) }}" class="btn btn-sm btn-warning d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="Edit Supply">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @if($supply->status !== 'completed')
                                    <form action="{{ route('supplies.completed', $supply) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-success d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="Mark as Completed">
                                            <i class="bi bi-check-circle"></i>
                                        </button>
                                    </form>
                                @endif
                                @if($supply->grn)
                                    <a href="{{ route('grns.show', $supply->grn) }}" class="btn btn-sm btn-secondary d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View GRN">
                                        <i class="bi bi-receipt"></i>
                                    </a>
                                @endif
                                @if($supply->grn && $supply->grn->supplierBill)
                                    <a href="{{ route('supplier-bills.show', $supply->grn->supplierBill) }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Supplier Bill">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </a>
                                @endif
                                <button type="button" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1"
                                    data-bs-toggle="tooltip" title="Delete Supply"
                                    onclick="if(confirm('Are you sure you want to delete this supply?')) { 
                                        document.getElementById('delete-supply-{{ $supply->id }}').submit(); 
                                    }">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            <form id="delete-supply-{{ $supply->id }}" 
                                action="{{ route('supplies.destroy', $supply) }}" 
                                method="POST" 
                                style="display: none;">
                                @csrf
                                @method('DELETE')
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-inbox display-1 d-block mb-3"></i>
                                <h5>No Supplies Found</h5>
                                <p class="mb-0">No supplies match your current search criteria.</p>
                                @if(request()->hasAny(['search', 'status', 'vendor_id', 'warehouse_id', 'date_from', 'date_to']))
                                    <a href="{{ route('supplies.index') }}" class="btn btn-outline-primary mt-2">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Clear Filters
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <x-pagination :paginator="$supplies" />
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
@endsection 
@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Goods Receipt Notes</h1>
        <a href="{{ route('supplies.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Supplies
        </a>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    
    

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>GRN ID</th>
                        <th>Vendor</th>
                        <th>Supply ID</th>
                        <th>Received Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($grns as $grn)
                        <tr>
                            <td>
                                <a href="{{ route('grns.show', $grn) }}" class="text-decoration-none">
                                    <strong>{{ $grn->formatted_id ?? 'GRN-' . str_pad($grn->id, 6, '0', STR_PAD_LEFT) }}</strong>
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('grns.show', $grn) }}" class="text-decoration-none">
                                    {{ $grn->supply->vendor->name ?? '-' }}
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('supplies.show', $grn->supply_id) }}" class="text-decoration-none">
                                    {{ $grn->supply->formatted_id ?? 'SUP-' . str_pad($grn->supply_id, 6, '0', STR_PAD_LEFT) }}
                                </a>
                            </td>
                            <td>{{ optional($grn->received_date)->format('M d, Y') }}</td>
                            <td>
                                @php
                                    $badge = [
                                        'draft'  => 'secondary',
                                        'posted' => 'success',
                                    ][$grn->status] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $badge }}">{{ ucfirst($grn->status) }}</span>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <a href="{{ route('grns.show', $grn) }}" class="btn btn-sm btn-info d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('supplies.show', $grn->supply_id) }}" class="btn btn-sm btn-secondary d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Supply">
                                        <i class="bi bi-truck"></i>
                                    </a>
                                    @if($grn->status === 'draft')
                                        <form action="{{ route('grns.update-status', $grn) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-success d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="Post GRN">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if($grn->supplierBill)
                                        <a href="{{ route('supplier-bills.show', $grn->supplierBill) }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Supplier Bill">
                                            <i class="bi bi-file-earmark-text"></i>
                                        </a>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1"
                                        data-bs-toggle="tooltip" title="Delete GRN"
                                        onclick="if(confirm('Are you sure you want to delete this GRN?')) { 
                                            document.getElementById('delete-grn-{{ $grn->id }}').submit(); 
                                        }">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <form id="delete-grn-{{ $grn->id }}" 
                                    action="{{ route('grns.destroy', $grn) }}" 
                                    method="POST" 
                                    style="display: none;">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">No GRNs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
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
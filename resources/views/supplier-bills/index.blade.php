@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Supplier Bills</h1>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    
    

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>Bill ID</th>
                        <th>Vendor</th>
                        <th>GRN</th>
                        <th>Bill Date</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bills as $bill)
                        <tr>
                            <td>
                                <a href="{{ route('supplier-bills.show', $bill) }}" class="text-decoration-none">
                                    <strong>{{ $bill->formatted_id }}</strong>
                                </a>
                            </td>
                            <td>{{ $bill->vendor->name ?? '-' }}</td>
                            <td>
                                <a href="{{ route('grns.show', $bill->grn_id) }}" class="text-decoration-none">
                                    {{ $bill->grn->formatted_id ?? 'GRN-' . str_pad($bill->grn_id, 6, '0', STR_PAD_LEFT) }}
                                </a>
                            </td>
                            <td>{{ optional($bill->bill_date)->format('M d, Y') }}</td>
                            <td class="text-end">${{ number_format($bill->total_amount, 2) }}</td>
                            <td>
                                @php
                                    $badge = [
                                        'draft'  => 'secondary',
                                        'posted' => 'success',
                                    ][$bill->status] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $badge }}">{{ ucfirst($bill->status) }}</span>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <a href="{{ route('supplier-bills.show', $bill) }}" class="btn btn-sm btn-info d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('grns.show', $bill->grn_id) }}" class="btn btn-sm btn-secondary d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View GRN">
                                        <i class="bi bi-receipt"></i>
                                    </a>
                                    @if($bill->grn && $bill->grn->supply)
                                        <a href="{{ route('supplies.show', $bill->grn->supply) }}" class="btn btn-sm btn-warning d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Supply">
                                            <i class="bi bi-truck"></i>
                                        </a>
                                    @endif
                                    @if($bill->status === 'posted')
                                        <a href="{{ route('supplier-bills.payment-info', $bill) }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="Payment Info">
                                            <i class="bi bi-credit-card"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No supplier bills found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$bills" />
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
@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <x-breadcrumb :items="[
        ['label' => 'Purchases', 'url' => '#'],
        ['label' => 'Supplier Bills Payment', 'url' => '#']
    ]" />
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Supplier Bills Payment</h1>
        <div>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
                <i class="bi bi-funnel me-1"></i> Filter
            </button>
        </div>
    </div>

    <!-- Filter Modal -->
    <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="filterModalLabel">Filter Supplier Bill Payments</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="GET" id="filterForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Payment Status</label>
                            <select name="payment_status" class="form-select">
                                <option value="">-- Any --</option>
                                <option value="unpaid" {{ request('payment_status') == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                                <option value="paid" {{ request('payment_status') == 'paid' ? 'selected' : '' }}>Paid</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <a href="{{ route('supplier-bill-payments.index') }}" class="btn btn-outline-secondary">Clear Filters</a>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Supplier Bill</th>
                            <th>Vendor</th>
                            <th class="text-end">Payment Amount</th>
                            <th>Payment Status</th>
                            <th>Created At</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                            <tr>
                                <td>
                                    <a href="{{ route('supplier-bill-payments.show', $payment) }}" class="text-decoration-none">
                                        <strong>{{ $payment->formatted_id }}</strong>
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('supplier-bills.show', $payment->supplier_bill_id) }}" class="text-decoration-none">
                                        {{ $payment->supplierBill->formatted_id }}
                                    </a>
                                </td>
                                <td>{{ $payment->vendor->name ?? '-' }}</td>
                                <td class="text-end">${{ number_format($payment->payment_amount, 2) }}</td>
                                <td>
                                    @php
                                        $badge = [
                                            'unpaid' => 'warning',
                                            'paid'   => 'success',
                                        ][$payment->payment_status] ?? 'secondary';
                                    @endphp
                                    <span class="badge bg-{{ $badge }}">{{ ucfirst($payment->payment_status) }}</span>
                                </td>
                                <td>{{ $payment->created_at->format('M d, Y H:i') }}</td>
                                <td class="text-center">
                                    <div class="d-flex flex-wrap gap-1 justify-content-center">
                                        <a href="{{ route('supplier-bill-payments.show', $payment) }}" class="btn btn-sm btn-info d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('supplier-bills.show', $payment->supplier_bill_id) }}" class="btn btn-sm btn-secondary d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Supplier Bill">
                                            <i class="bi bi-file-earmark-text"></i>
                                        </a>
                                        @if($payment->supplierBill && $payment->supplierBill->grn)
                                            <a href="{{ route('grns.show', $payment->supplierBill->grn) }}" class="btn btn-sm btn-warning d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View GRN">
                                                <i class="bi bi-receipt"></i>
                                            </a>
                                        @endif
                                        @if($payment->supplierBill && $payment->supplierBill->grn && $payment->supplierBill->grn->supply)
                                            <a href="{{ route('supplies.show', $payment->supplierBill->grn->supply) }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Supply">
                                                <i class="bi bi-truck"></i>
                                            </a>
                                        @endif
                                        <a href="{{ route('supplier-bills.payment-info', $payment->supplierBill) }}" class="btn btn-sm btn-success d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="Payment Info">
                                            <i class="bi bi-credit-card"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">No supplier bill payments found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

        <x-pagination :paginator="$payments" />
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
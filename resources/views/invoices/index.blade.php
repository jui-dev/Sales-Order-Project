@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Invoices</h1>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Unified Search Component -->
    <x-unified-search
        :searchPlaceholder="'Search invoices by number, ID, or customer...'"
        :filterOptions="$filterOptions"
        :sortOptions="$sortOptions"
        :defaultSort="'id'"
        :defaultDirection="'desc'"
    />

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>#</th>
                <th>Invoice No.</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Total</th>
                <th>Status</th>
                <th class="text-end">Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->id }}</td>
                    <td>{{ $invoice->invoice_number }}</td>
                    <td>{{ optional($invoice->invoice_date)->format('M d, Y') }}</td>
                    <td>{{ optional($invoice->customer)->name }}</td>
                    <td>${{ number_format($invoice->total, 2) }}</td>
                    <td><span class="badge bg-secondary">{{ ucfirst($invoice->payment_status) }}</span></td>
                    <td class="text-end">
                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                            <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-info d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Details">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('invoices.download', $invoice) }}" class="btn btn-sm btn-secondary d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="Download PDF">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </a>
                            @if($invoice->order)
                                <a href="{{ route('orders.show', $invoice->order) }}" class="btn btn-sm btn-warning d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Order">
                                    <i class="bi bi-cart"></i>
                                </a>
                            @endif
                            @if($invoice->payment_status === 'unpaid')
                                <div class="dropdown d-inline">
                                    <button class="btn btn-sm btn-success dropdown-toggle d-inline-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-toggle="tooltip" title="Record Payment">
                                        <i class="bi bi-credit-card"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <form action="{{ route('invoices.pay', $invoice) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="amount" value="{{ $invoice->total }}">
                                                <input type="hidden" name="method" value="cash">
                                                <button type="submit" class="dropdown-item">Mark as Paid</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            @endif
                            <button type="button" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1"
                                data-bs-toggle="tooltip" title="Delete Invoice"
                                onclick="if(confirm('Are you sure you want to delete this invoice?')) { 
                                    document.getElementById('delete-invoice-{{ $invoice->id }}').submit(); 
                                }">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <form id="delete-invoice-{{ $invoice->id }}" 
                            action="{{ route('invoices.destroy', $invoice) }}" 
                            method="POST" 
                            style="display: none;">
                            @csrf
                            @method('DELETE')
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <div class="text-muted">
                            <i class="bi bi-inbox display-1 d-block mb-3"></i>
                            <h5>No Invoices Found</h5>
                            <p class="mb-0">No invoices match your current search criteria.</p>
                            @if(request()->hasAny(['search', 'status', 'customer_id', 'date_from', 'date_to']))
                                <a href="{{ route('invoices.index') }}" class="btn btn-outline-primary mt-2">
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

        <x-pagination :paginator="$invoices" />
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
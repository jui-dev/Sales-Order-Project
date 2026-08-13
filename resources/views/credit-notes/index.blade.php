@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Credit Notes</h1>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    
    

    <!-- Credit Note Management Guidance -->
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle"></i>
        <strong>Credit Note Management:</strong> Credit notes are automatically generated when customer returns are approved. Use the workflow buttons to control financial impact timing.
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">{{ $statistics['total'] }}</h4>
                            <small>Total Credit Notes</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-receipt display-6"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">{{ $statistics['pending'] }}</h4>
                            <small>Pending</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-clock display-6"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">{{ $statistics['issued'] }}</h4>
                            <small>Issued</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-check-circle display-6"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">${{ number_format($statistics['total_amount'], 2) }}</h4>
                            <small>Total Amount</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-currency-dollar display-6"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('credit-notes.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="{{ $filters['search'] ?? '' }}" 
                           placeholder="Credit note number, customer, invoice...">
                </div>
                <div class="col-md-2">
                    <label for="customer_id" class="form-label">Customer</label>
                    <select class="form-select" id="customer_id" name="customer_id">
                        <option value="">All Customers</option>
                        @if($customers && $customers->count() > 0)
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" {{ ($filters['customer_id'] ?? '') == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->name }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        @if($statusOptions && is_array($statusOptions))
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['status'] ?? '') == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Credit Notes Table -->
    @if($creditNotes && $creditNotes->count() > 0)
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Credit Note #</th>
                                <th>Customer</th>
                                <th>Invoice #</th>
                                <th>Return #</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Issue Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($creditNotes as $creditNote)
                                <tr>
                                    <td>
                                        <strong>{{ $creditNote->credit_note_number }}</strong>
                                    </td>
                                    <td>
                                        <strong>{{ $creditNote->customer->name }}</strong>
                                        <br><small class="text-muted">{{ $creditNote->customer->email }}</small>
                                    </td>
                                    <td>
                                        <a href="{{ route('invoices.show', $creditNote->invoice) }}" class="text-decoration-none">
                                            {{ $creditNote->invoice->invoice_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{ route('returns.show', $creditNote->returnTransaction) }}" class="text-decoration-none">
                                            {{ $creditNote->returnTransaction->formatted_id }}
                                        </a>
                                    </td>
                                    <td>
                                        <strong class="text-success">${{ number_format($creditNote->total_amount, 2) }}</strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $creditNote->status_color }}">
                                            {{ $creditNote->status_display }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $creditNote->issue_date ? $creditNote->issue_date->format('M d, Y') : '-' }}
                                        @if($creditNote->issue_date)
                                            <br><small class="text-muted">{{ $creditNote->issue_date->format('H:i') }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('credit-notes.show', $creditNote) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            @if($creditNote->status === 'issued')
                                                <a href="{{ route('credit-notes.download', $creditNote) }}" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <x-pagination :paginator="$creditNotes" />
    @else
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-receipt display-1 text-muted mb-3"></i>
                <h3>No Credit Notes Found</h3>
                <p class="text-muted mb-4">No credit notes have been generated yet.</p>
                <a href="{{ route('returns.index') }}" class="btn btn-primary">
                    <i class="bi bi-arrow-return-left me-1"></i> View Returns
                </a>
            </div>
        </div>
    @endif
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
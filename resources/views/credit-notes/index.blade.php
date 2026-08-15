@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Credit Notes</h1>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    
    

    {{-- Summary. Awaiting Posting is the number worth acting on: those notes
         exist but have not touched the accounts yet. Each card links to the
         filtered list behind it. --}}
    <div class="summary-panel mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm summary-card summary-card--blue">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-primary">Credit Notes</h6>
                                <h3>{{ number_format($statistics['total'] ?? 0) }}</h3>
                            </div>
                            <i class="bi bi-receipt text-primary summary-card__icon" style="font-size: 2rem;"></i>
                        </div>
                        <small>
                            <a href="{{ route('returns.index', ['type' => 'customer_return']) }}" class="text-primary text-decoration-none">
                                <i class="bi bi-arrow-right me-1"></i>From customer returns
                            </a>
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm summary-card summary-card--amber">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-warning-emphasis">Awaiting Posting</h6>
                                <h3>{{ number_format($statistics['issued'] ?? 0) }}</h3>
                            </div>
                            <i class="bi bi-hourglass-split text-warning-emphasis summary-card__icon" style="font-size: 2rem;"></i>
                        </div>
                        <small>
                            <a href="{{ route('credit-notes.index', ['status' => 'issued']) }}" class="text-warning-emphasis text-decoration-none">
                                <i class="bi bi-arrow-right me-1"></i>No ledger effect yet
                            </a>
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm summary-card summary-card--green">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-success">Posted</h6>
                                <h3>{{ number_format($statistics['posted'] ?? 0) }}</h3>
                            </div>
                            <i class="bi bi-journal-check text-success summary-card__icon" style="font-size: 2rem;"></i>
                        </div>
                        <small>
                            <a href="{{ route('credit-notes.index', ['status' => 'posted']) }}" class="text-success text-decoration-none">
                                <i class="bi bi-arrow-right me-1"></i>${{ number_format($statistics['posted_amount'] ?? 0, 2) }} through the ledger
                            </a>
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm summary-card summary-card--cyan">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-info-emphasis">Value Outstanding</h6>
                                <h3>${{ number_format($statistics['total_amount'] ?? 0, 2) }}</h3>
                            </div>
                            <i class="bi bi-currency-dollar text-info-emphasis summary-card__icon" style="font-size: 2rem;"></i>
                        </div>
                        <small class="text-muted">Owed back across issued notes</small>
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
                                        {{-- Solid so the action reads by colour at rest. Download
                                             lives on the note itself, alongside the actions that
                                             need its status. --}}
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('credit-notes.show', $creditNote) }}"
                                               class="btn btn-sm btn-primary" title="View credit note">
                                                <i class="bi bi-eye"></i>
                                            </a>
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
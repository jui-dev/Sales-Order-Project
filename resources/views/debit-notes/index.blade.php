@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Debit Notes</h1>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    
    

    <!-- Debit Note Management Guidance -->
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle"></i>
        <strong>Debit Note Management:</strong> Debit notes are automatically generated when vendor returns are approved. Use the workflow buttons to control financial impact timing.
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">{{ $statistics['total'] }}</h4>
                            <small>Total Debit Notes</small>
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
            <form method="GET" action="{{ route('debit-notes.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label for="vendor_id" class="form-label">Vendor</label>
                    <select name="vendor_id" id="vendor_id" class="form-select">
                        <option value="">All Vendors</option>
                        @if($vendors && $vendors->count() > 0)
                            @foreach($vendors as $vendor)
                                <option value="{{ $vendor->id }}" {{ request('vendor_id') == $vendor->id ? 'selected' : '' }}>
                                    {{ $vendor->name }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All Statuses</option>
                        @if($statusOptions && is_array($statusOptions))
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Search..." value="{{ request('search') }}">
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

    <!-- Debit Notes Table -->
    <div class="card">
        <div class="card-body">
            @if($debitNotes && $debitNotes->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Debit Note #</th>
                                <th>Vendor</th>
                                <th>Supplier Bill</th>
                                <th>Return Transaction</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Issue Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($debitNotes && $debitNotes->count() > 0)
                                @foreach($debitNotes as $debitNote)
                                    <tr>
                                        <td>
                                            <strong>{{ $debitNote->debit_note_number ?? 'N/A' }}</strong>
                                        </td>
                                        <td>
                                            @if($debitNote->vendor)
                                                <strong>{{ $debitNote->vendor->name }}</strong>
                                                <br><small class="text-muted">{{ $debitNote->vendor->email ?? '' }}</small>
                                            @else
                                                <span class="text-muted">No Vendor</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($debitNote->supplierBill)
                                                <a href="{{ route('supplier-bills.show', $debitNote->supplierBill) }}" class="text-decoration-none">
                                                    {{ $debitNote->supplierBill->formatted_id ?? 'N/A' }}
                                                </a>
                                            @else
                                                <span class="text-muted">No Bill</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($debitNote->returnTransaction)
                                                <a href="{{ route('returns.show', $debitNote->returnTransaction) }}" class="text-decoration-none">
                                                    {{ $debitNote->returnTransaction->formatted_id ?? 'N/A' }}
                                                </a>
                                            @else
                                                <span class="text-muted">No Return</span>
                                            @endif
                                        </td>
                                        <td>
                                            <strong class="text-success">${{ number_format($debitNote->total_amount ?? 0, 2) }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $debitNote->status_color ?? 'secondary' }}">
                                                {{ $debitNote->status_display ?? 'Unknown' }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ $debitNote->issue_date ? $debitNote->issue_date->format('M d, Y') : '-' }}
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('debit-notes.show', $debitNote) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                @if($debitNote->status === 'issued')
                                                    <a href="{{ route('debit-notes.download', $debitNote) }}" class="btn btn-sm btn-outline-secondary">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-receipt display-1 text-muted"></i>
                    <h4 class="mt-3">No Debit Notes Found</h4>
                    <p class="text-muted">No debit notes match your current filters.</p>
                </div>
            @endif
        </div>
    </div>

    @if($debitNotes && $debitNotes->count() > 0)
        <x-pagination :paginator="$debitNotes" />
    @endif
</div>
@endsection 
@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Credit Note #{{ $creditNote->credit_note_number }}</h1>
        <div class="d-flex gap-2">
            @if($creditNote->status === 'issued')
                @if(!$creditNote->journalEntry)
                    <form action="{{ route('credit-notes.post', $creditNote) }}" method="POST" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i> Post Credit Note
                        </button>
                    </form>
                @elseif($creditNote->journalEntry->status === 'draft')
                    <form action="{{ route('credit-notes.post-journal-entry', $creditNote) }}" method="POST" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-arrow-up-circle me-1"></i> Post Journal Entry
                        </button>
                    </form>
                @elseif($creditNote->journalEntry->status === 'posted')
                    <span class="badge bg-success fs-6">
                        <i class="bi bi-check-circle me-1"></i> Journal Entry Posted
                    </span>
                @endif
                
                <a href="{{ route('credit-notes.download', $creditNote) }}" class="btn btn-primary">
                    <i class="bi bi-download me-1"></i> Download PDF
                </a>
                <form action="{{ route('credit-notes.cancel', $creditNote) }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-danger"
                            onclick="return confirm('Are you sure you want to cancel this credit note?')">
                        <i class="bi bi-x-circle me-1"></i> Cancel Credit Note
                    </button>
                </form>
            @elseif($creditNote->status === 'posted')
                @if($creditNote->journalEntry && $creditNote->journalEntry->status === 'draft')
                    <form action="{{ route('credit-notes.post-journal-entry', $creditNote) }}" method="POST" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-arrow-up-circle me-1"></i> Post Journal Entry
                        </button>
                    </form>
                @elseif($creditNote->journalEntry && $creditNote->journalEntry->status === 'posted')
                    <span class="badge bg-success fs-6">
                        <i class="bi bi-check-circle me-1"></i> Journal Entry Posted
                    </span>
                @endif
                
                <a href="{{ route('credit-notes.download', $creditNote) }}" class="btn btn-primary">
                    <i class="bi bi-download me-1"></i> Download PDF
                </a>
                <form action="{{ route('credit-notes.cancel', $creditNote) }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-danger"
                            onclick="return confirm('Are you sure you want to cancel this credit note?')">
                        <i class="bi bi-x-circle me-1"></i> Cancel Credit Note
                    </button>
                </form>
                
                <!-- Disabled Post Credit Note button for posted status -->
                <button type="button" class="btn btn-secondary" disabled>
                    <i class="bi bi-check-circle me-1"></i> Post Credit Note
                </button>
            @endif
            <a href="{{ route('credit-notes.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    
    <!-- Credit Note Workflow Guidance -->
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle"></i>
        <strong>Credit Note Management:</strong> Use the workflow buttons to control when financial impact occurs. Credit notes are automatically generated when customer returns are approved.
    </div>

    <!-- Success Message -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    
    

    <div class="row">
        <!-- Credit Note Details -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Credit Note Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Credit Note Number:</strong></td>
                                    <td>{{ $creditNote->credit_note_number }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge bg-{{ $creditNote->status_color }}">
                                            {{ $creditNote->status_display }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Issue Date:</strong></td>
                                    <td>{{ $creditNote->issue_date ? $creditNote->issue_date->format('M d, Y H:i') : 'Not issued' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Amount:</strong></td>
                                    <td><strong class="text-success">${{ number_format($creditNote->total_amount, 2) }}</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Journal Entry:</strong></td>
                                    <td>
                                        @if($creditNote->journalEntry)
                                            <a href="{{ route('journal-entries.show', $creditNote->journalEntry) }}" class="text-decoration-none">
                                                {{ $creditNote->journalEntry->formatted_id }}
                                            </a>
                                            <br><small class="text-muted">
                                                Status: <span class="badge bg-{{ $creditNote->journalEntry->status === 'posted' ? 'success' : 'warning' }}">
                                                    {{ ucfirst($creditNote->journalEntry->status) }}
                                                </span>
                                            </small>
                                        @else
                                            <span class="text-muted">Not posted</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Customer:</strong></td>
                                    <td>
                                        <strong>{{ $creditNote->customer->name }}</strong><br>
                                        <small class="text-muted">{{ $creditNote->customer->email }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Original Invoice:</strong></td>
                                    <td>
                                        <a href="{{ route('invoices.show', $creditNote->invoice) }}" class="text-decoration-none">
                                            {{ $creditNote->invoice->invoice_number }}
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Return Transaction:</strong></td>
                                    <td>
                                        <a href="{{ route('returns.show', $creditNote->returnTransaction) }}" class="text-decoration-none">
                                            {{ $creditNote->returnTransaction->formatted_id }}
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Created:</strong></td>
                                    <td>{{ $creditNote->created_at->format('M d, Y H:i') }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    @if($creditNote->notes)
                        <div class="mt-3">
                            <strong>Notes:</strong>
                            <p class="mb-0 text-muted">{{ $creditNote->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Credit Note Items -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Credit Note Items</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($creditNote->metadata && isset($creditNote->metadata['product_name']))
                                    <tr>
                                        <td>
                                            <strong>{{ $creditNote->metadata['product_name'] }}</strong>
                                            @if(isset($creditNote->metadata['product_sku']))
                                                <br><small class="text-muted">SKU: {{ $creditNote->metadata['product_sku'] }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $creditNote->metadata['quantity_returned'] ?? 0 }}</td>
                                        <td>${{ number_format($creditNote->metadata['original_unit_price'] ?? 0, 2) }}</td>
                                        <td><strong>${{ number_format($creditNote->total_amount, 2) }}</strong></td>
                                        <td>
                                            @if(isset($creditNote->metadata['return_reason']))
                                                <small class="text-muted">{{ $creditNote->metadata['return_reason'] }}</small>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @else
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No item details available</td>
                                    </tr>
                                @endif
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                    <td><strong class="text-success">${{ number_format($creditNote->total_amount, 2) }}</strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Information -->
        <div class="col-md-4">
            <!-- Return Transaction Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Return Transaction</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Return ID:</strong>
                        <a href="{{ route('returns.show', $creditNote->returnTransaction) }}" class="text-decoration-none">
                            {{ $creditNote->returnTransaction->formatted_id }}
                        </a>
                    </div>
                    <div class="mb-3">
                        <strong>Product:</strong>
                        <div>{{ $creditNote->returnTransaction->product->name }}</div>
                        <small class="text-muted">SKU: {{ $creditNote->returnTransaction->product->sku }}</small>
                    </div>
                    <div class="mb-3">
                        <strong>Quantity Returned:</strong>
                        <div>{{ $creditNote->returnTransaction->quantity }}</div>
                    </div>
                    <div class="mb-3">
                        <strong>Return Date:</strong>
                        <div>{{ $creditNote->returnTransaction->transaction_date->format('M d, Y') }}</div>
                    </div>
                    <div>
                        <strong>Status:</strong>
                        <div>
                            <span class="badge bg-{{ $creditNote->returnTransaction->status === 'pending' ? 'warning' : ($creditNote->returnTransaction->status === 'approved' ? 'info' : 'success') }}">
                                {{ ucfirst($creditNote->returnTransaction->status) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Original Invoice Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Original Invoice</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Invoice Number:</strong>
                        <a href="{{ route('invoices.show', $creditNote->invoice) }}" class="text-decoration-none">
                            {{ $creditNote->invoice->invoice_number }}
                        </a>
                    </div>
                    <div class="mb-3">
                        <strong>Invoice Date:</strong>
                        <div>{{ $creditNote->invoice->invoice_date->format('M d, Y') }}</div>
                    </div>
                    <div class="mb-3">
                        <strong>Invoice Total:</strong>
                        <div>${{ number_format($creditNote->invoice->total, 2) }}</div>
                    </div>
                    <div>
                        <strong>Status:</strong>
                        <div>
                            <span class="badge bg-{{ $creditNote->invoice->status === 'paid' ? 'success' : 'warning' }}">
                                {{ ucfirst($creditNote->invoice->status) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Documents -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Related Documents</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Original Invoice:</strong><br>
                        <a href="{{ route('invoices.show', $creditNote->invoice) }}" class="text-decoration-none">
                            {{ $creditNote->invoice->invoice_number }}
                        </a>
                        <br><small class="text-muted">{{ $creditNote->invoice->invoice_date->format('M d, Y') }}</small>
                    </div>
                    
                    @if($creditNote->journalEntry)
                        <div>
                            <strong>Journal Entry:</strong><br>
                            <a href="{{ route('journal-entries.show', $creditNote->journalEntry) }}" class="text-decoration-none">
                                {{ $creditNote->journalEntry->formatted_id }}
                            </a>
                            <br><small class="text-muted">
                                Status: <span class="badge bg-{{ $creditNote->journalEntry->status === 'posted' ? 'success' : 'warning' }}">
                                    {{ ucfirst($creditNote->journalEntry->status) }}
                                </span>
                            </small>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Customer Information -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Customer Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Name:</strong>
                        <div>{{ $creditNote->customer->name }}</div>
                    </div>
                    <div class="mb-3">
                        <strong>Email:</strong>
                        <div>{{ $creditNote->customer->email }}</div>
                    </div>
                    <div class="mb-3">
                        <strong>Phone:</strong>
                        <div>{{ $creditNote->customer->phone ?? 'Not provided' }}</div>
                    </div>
                    <div>
                        <strong>Address:</strong>
                        <div>{{ $creditNote->customer->address ?? 'Not provided' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
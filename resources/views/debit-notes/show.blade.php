@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <x-breadcrumb :items="[
        ['label' => 'Returns', 'url' => '#'],
        ['label' => 'Debit Notes', 'url' => route('debit-notes.index')],
        ['label' => $debitNote->debit_note_number, 'url' => '#']
    ]" />
    
    <!-- Debit Note Workflow Guidance -->
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle"></i>
        <strong>Debit Note Management:</strong> Use the workflow buttons to control when financial impact occurs. Debit notes are automatically generated when vendor returns are approved.
    </div>
    
    <!-- Success Message -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Debit Note #{{ $debitNote->debit_note_number }}</h1>
        <div class="d-flex gap-2">
            @if($debitNote->status === 'issued')
                @if(!$debitNote->journalEntry)
                    <form action="{{ route('debit-notes.post', $debitNote) }}" method="POST" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i> Post Debit Note
                        </button>
                    </form>
                @elseif($debitNote->journalEntry->status === 'draft')
                    <form action="{{ route('debit-notes.post-journal-entry', $debitNote) }}" method="POST" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-arrow-up-circle me-1"></i> Post Journal Entry
                        </button>
                    </form>
                @elseif($debitNote->journalEntry->status === 'posted')
                    <span class="badge bg-success fs-6">
                        <i class="bi bi-check-circle me-1"></i> Journal Entry Posted
                    </span>
                @endif
                
                <a href="{{ route('debit-notes.download', $debitNote) }}" class="btn btn-primary">
                    <i class="bi bi-download me-1"></i> Download PDF
                </a>
            @endif
            
            @if($debitNote->status === 'posted')
                @if($debitNote->journalEntry && $debitNote->journalEntry->status === 'draft')
                    <form action="{{ route('debit-notes.post-journal-entry', $debitNote) }}" method="POST" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-arrow-up-circle me-1"></i> Post Journal Entry
                        </button>
                    </form>
                @elseif($debitNote->journalEntry && $debitNote->journalEntry->status === 'posted')
                    <span class="badge bg-success fs-6">
                        <i class="bi bi-check-circle me-1"></i> Journal Entry Posted
                    </span>
                @endif
                
                <a href="{{ route('debit-notes.download', $debitNote) }}" class="btn btn-primary">
                    <i class="bi bi-download me-1"></i> Download PDF
                </a>
                
                <!-- Disabled Post Debit Note button for posted status -->
                <button type="button" class="btn btn-secondary" disabled>
                    <i class="bi bi-check-circle me-1"></i> Post Debit Note
                </button>
            @endif
            
            @if($debitNote->status === 'issued' && !$debitNote->journalEntry)
                <form action="{{ route('debit-notes.cancel', $debitNote) }}" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this debit note?')">
                    @csrf
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                </form>
            @endif
            
            <a href="{{ route('debit-notes.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>

    <!-- Status Alert -->
    @if($debitNote->status === 'cancelled')
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>This debit note has been cancelled.</strong>
            @if($debitNote->cancellation_reason)
                <br>Reason: {{ $debitNote->cancellation_reason }}
            @endif
        </div>
    @endif

    <div class="row">
        <!-- Main Details -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Debit Note Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Debit Note Number:</strong></td>
                                    <td>{{ $debitNote->debit_note_number }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge bg-{{ $debitNote->status_color }}">
                                            {{ $debitNote->status_display }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Issue Date:</strong></td>
                                    <td>{{ $debitNote->issue_date ? $debitNote->issue_date->format('M d, Y') : '-' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Reason:</strong></td>
                                    <td>{{ $debitNote->reason ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Journal Entry:</strong></td>
                                    <td>
                                        @if($debitNote->journalEntry)
                                            <a href="{{ route('journal-entries.show', $debitNote->journalEntry) }}" class="text-decoration-none">
                                                {{ $debitNote->journalEntry->formatted_id }}
                                            </a>
                                            <br><small class="text-muted">
                                                Status: <span class="badge bg-{{ $debitNote->journalEntry->status === 'posted' ? 'success' : 'warning' }}">
                                                    {{ ucfirst($debitNote->journalEntry->status) }}
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
                                    <td><strong>Total Amount:</strong></td>
                                    <td><strong class="text-success">${{ number_format($debitNote->total_amount, 2) }}</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Subtotal:</strong></td>
                                    <td>${{ number_format($debitNote->subtotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Tax Amount:</strong></td>
                                    <td>${{ number_format($debitNote->tax_amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Discount Amount:</strong></td>
                                    <td>${{ number_format($debitNote->discount_amount, 2) }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    @if($debitNote->notes)
                        <div class="mt-3">
                            <strong>Notes:</strong>
                            <p class="mb-0">{{ $debitNote->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Debit Note Items -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Debit Note Items</h5>
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
                                @if($debitNote->items && $debitNote->items->count() > 0)
                                    @foreach($debitNote->items as $item)
                                        <tr>
                                            <td>
                                                <strong>{{ $item->product_name ?? $item->product->name }}</strong>
                                                @if($item->sku)
                                                    <br><small class="text-muted">SKU: {{ $item->sku }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $item->quantity }}</td>
                                            <td>${{ number_format($item->unit_price, 2) }}</td>
                                            <td><strong>${{ number_format($item->total_amount, 2) }}</strong></td>
                                            <td>
                                                @if($item->notes)
                                                    <small class="text-muted">{{ $item->notes }}</small>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @elseif($debitNote->metadata && isset($debitNote->metadata['product_name']))
                                    <tr>
                                        <td>
                                            <strong>{{ $debitNote->metadata['product_name'] }}</strong>
                                            @if(isset($debitNote->metadata['product_sku']))
                                                <br><small class="text-muted">SKU: {{ $debitNote->metadata['product_sku'] }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $debitNote->metadata['quantity_returned'] ?? 0 }}</td>
                                        <td>${{ number_format($debitNote->metadata['original_unit_price'] ?? 0, 2) }}</td>
                                        <td><strong>${{ number_format($debitNote->total_amount, 2) }}</strong></td>
                                        <td>
                                            @if(isset($debitNote->metadata['return_reason']))
                                                <small class="text-muted">{{ $debitNote->metadata['return_reason'] }}</small>
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
                                    <td><strong class="text-success">${{ number_format($debitNote->total_amount, 2) }}</strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Return Transaction -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Return Transaction</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Return ID:</strong>
                        <a href="{{ route('returns.show', $debitNote->returnTransaction) }}" class="text-decoration-none">
                            {{ $debitNote->returnTransaction->formatted_id }}
                        </a>
                    </div>
                    <div class="mb-3">
                        <strong>Product:</strong>
                        <div>{{ $debitNote->returnTransaction->product->name }}</div>
                        <small class="text-muted">SKU: {{ $debitNote->returnTransaction->product->sku }}</small>
                    </div>
                    <div class="mb-3">
                        <strong>Quantity Returned:</strong>
                        <div>{{ $debitNote->returnTransaction->quantity }}</div>
                    </div>
                    <div class="mb-3">
                        <strong>Return Date:</strong>
                        <div>{{ $debitNote->returnTransaction->transaction_date->format('M d, Y') }}</div>
                    </div>
                    <div>
                        <strong>Status:</strong>
                        <div>
                            <span class="badge bg-{{ $debitNote->returnTransaction->status === 'pending' ? 'warning' : ($debitNote->returnTransaction->status === 'approved' ? 'info' : 'success') }}">
                                {{ ucfirst($debitNote->returnTransaction->status) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Vendor Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Vendor Information</h5>
                </div>
                <div class="card-body">
                    <h6>{{ $debitNote->vendor->name }}</h6>
                    <p class="mb-1">
                        <strong>Email:</strong> {{ $debitNote->vendor->email }}<br>
                        <strong>Phone:</strong> {{ $debitNote->vendor->phone ?? '-' }}<br>
                        <strong>Address:</strong> {{ $debitNote->vendor->address ?? '-' }}
                    </p>
                </div>
            </div>

            <!-- Related Documents -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Related Documents</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Supplier Bill:</strong><br>
                        <a href="{{ route('supplier-bills.show', $debitNote->supplierBill) }}" class="text-decoration-none">
                            {{ $debitNote->supplierBill->formatted_id }}
                        </a>
                        <br><small class="text-muted">{{ $debitNote->supplierBill->bill_date->format('M d, Y') }}</small>
                    </div>
                    
                    @if($debitNote->journalEntry)
                        <div>
                            <strong>Journal Entry:</strong><br>
                            <a href="{{ route('journal-entries.show', $debitNote->journalEntry) }}" class="text-decoration-none">
                                {{ $debitNote->journalEntry->formatted_id }}
                            </a>
                            <br><small class="text-muted">
                                Status: <span class="badge bg-{{ $debitNote->journalEntry->status === 'posted' ? 'success' : 'warning' }}">
                                    {{ ucfirst($debitNote->journalEntry->status) }}
                                </span>
                            </small>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Audit Information -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Audit Information</h5>
                </div>
                <div class="card-body">
                    <small class="text-muted">
                        <strong>Created:</strong> {{ $debitNote->created_at->format('M d, Y H:i') }}<br>
                        @if($debitNote->createdBy)
                            <strong>By:</strong> {{ $debitNote->createdBy->name }}<br>
                        @endif
                        @if($debitNote->updated_at != $debitNote->created_at)
                            <strong>Last Updated:</strong> {{ $debitNote->updated_at->format('M d, Y H:i') }}<br>
                        @endif
                        @if($debitNote->cancelled_at)
                            <strong>Cancelled:</strong> {{ $debitNote->cancelled_at->format('M d, Y H:i') }}<br>
                            @if($debitNote->cancelledBy)
                                <strong>By:</strong> {{ $debitNote->cancelledBy->name }}
                            @endif
                        @endif
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
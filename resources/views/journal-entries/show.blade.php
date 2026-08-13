@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Journal Entry #{{ $journalEntry->formatted_id }}</h1>
        <div class="d-flex gap-2">
            @if($journalEntry->status === 'draft')
                <a href="{{ route('journal-entries.edit', $journalEntry) }}" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
                <form action="{{ route('journal-entries.post', $journalEntry) }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i> Post Entry
                    </button>
                </form>
            @elseif($journalEntry->status === 'posted')
                <span class="badge bg-success fs-6">
                    <i class="bi bi-check-circle me-1"></i> Posted
                </span>
            @elseif($journalEntry->status === 'approved')
                <span class="badge bg-info fs-6">
                    <i class="bi bi-check-circle me-1"></i> Approved
                </span>
            @elseif($journalEntry->status === 'rejected')
                <span class="badge bg-danger fs-6">
                    <i class="bi bi-x-circle me-1"></i> Rejected
                </span>
            @endif
            
            <a href="{{ route('journal-entries.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    
    

    <!-- Status Alert -->
    @if($journalEntry->status === 'draft')
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Draft Status:</strong> This journal entry is in draft status and has not been posted to the general ledger.
        </div>
    @elseif($journalEntry->status === 'posted')
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            <strong>Posted:</strong> This journal entry has been posted to the general ledger and affects financial statements.
        </div>
    @endif

    <div class="row">
        <!-- Main Details -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Journal Entry Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Journal Entry ID:</strong></td>
                                    <td>{{ $journalEntry->formatted_id }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge bg-{{ $journalEntry->status === 'posted' ? 'success' : ($journalEntry->status === 'draft' ? 'warning' : ($journalEntry->status === 'approved' ? 'info' : 'danger')) }}">
                                            {{ ucfirst($journalEntry->status) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Entry Date:</strong></td>
                                    <td>{{ $journalEntry->entry_date->format('M d, Y') }}</td>
                                </tr>
                                @if($journalEntry->posted_at)
                                    <tr>
                                        <td><strong>Posted At:</strong></td>
                                        <td>{{ $journalEntry->posted_at->format('M d, Y H:i') }}</td>
                                    </tr>
                                @endif
                                @if($journalEntry->approved_at)
                                    <tr>
                                        <td><strong>Approved At:</strong></td>
                                        <td>{{ $journalEntry->approved_at->format('M d, Y H:i') }}</td>
                                    </tr>
                                @endif
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Total Debit:</strong></td>
                                    <td><strong class="text-success">${{ number_format($journalEntry->totalDebit(), 2) }}</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Total Credit:</strong></td>
                                    <td><strong class="text-success">${{ number_format($journalEntry->totalCredit(), 2) }}</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Balance:</strong></td>
                                    <td>
                                        @if($journalEntry->isBalanced())
                                            <span class="badge bg-success">Balanced</span>
                                        @else
                                            <span class="badge bg-danger">Unbalanced</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($journalEntry->is_reverse)
                                    <tr>
                                        <td><strong>Reverse Entry:</strong></td>
                                        <td>
                                            <span class="badge bg-info">Yes</span>
                                            @if($journalEntry->reversesJournal)
                                                <br><small class="text-muted">Reverses: {{ $journalEntry->reversesJournal->formatted_id }}</small>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            </table>
                        </div>
                    </div>

                    @if($journalEntry->description)
                        <div class="mt-3">
                            <strong>Description:</strong>
                            <p class="mb-0">{{ $journalEntry->description }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Journal Entry Lines -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Line Items</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($journalEntry->lines as $line)
                                    <tr>
                                        <td>
                                            <strong>{{ $line->account->code }}</strong>
                                            <br><small class="text-muted">{{ $line->account->name }}</small>
                                        </td>
                                        <td class="text-end">
                                            @if($line->debit > 0)
                                                <strong class="text-success">${{ number_format($line->debit, 2) }}</strong>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if($line->credit > 0)
                                                <strong class="text-success">${{ number_format($line->credit, 2) }}</strong>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($line->description)
                                                <small class="text-muted">{{ $line->description }}</small>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <th class="text-end">Totals</th>
                                    <th class="text-end"><strong>${{ number_format($journalEntry->totalDebit(), 2) }}</strong></th>
                                    <th class="text-end"><strong>${{ number_format($journalEntry->totalCredit(), 2) }}</strong></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Source Information -->
            @if($journalEntry->source)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Source Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Source Type:</strong>
                            <div>{{ class_basename($journalEntry->source_type) }}</div>
                        </div>
                        <div class="mb-3">
                            <strong>Source ID:</strong>
                            <div>{{ $journalEntry->source_id }}</div>
                        </div>
                        @if($journalEntry->source)
                            <div>
                                <strong>Source Details:</strong>
                                <div>{{ $journalEntry->source->formatted_id ?? $journalEntry->source->id }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Reverse Journal Information -->
            @if($journalEntry->is_reverse || $journalEntry->reverseJournals->count() > 0)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Reverse Journal Information</h5>
                    </div>
                    <div class="card-body">
                        @if($journalEntry->is_reverse)
                            <div class="mb-3">
                                <strong>This is a reverse entry for:</strong>
                                @if($journalEntry->reversesJournal)
                                    <div>
                                        <a href="{{ route('journal-entries.show', $journalEntry->reversesJournal) }}" class="text-decoration-none">
                                            {{ $journalEntry->reversesJournal->formatted_id }}
                                        </a>
                                    </div>
                                @else
                                    <div class="text-muted">Original entry not found</div>
                                @endif
                            </div>
                        @endif

                        @if($journalEntry->reverseJournals->count() > 0)
                            <div>
                                <strong>Reverse entries for this journal:</strong>
                                <ul class="list-unstyled mt-2">
                                    @foreach($journalEntry->reverseJournals as $reverseEntry)
                                        <li>
                                            <a href="{{ route('journal-entries.show', $reverseEntry) }}" class="text-decoration-none">
                                                {{ $reverseEntry->formatted_id }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Linked Documents -->
            @if($journalEntry->linkedDebitNote || $journalEntry->linkedCreditNote)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Linked Documents</h5>
                    </div>
                    <div class="card-body">
                        @if($journalEntry->linkedDebitNote)
                            <div class="mb-3">
                                <strong>Linked Debit Note:</strong><br>
                                <a href="{{ route('debit-notes.show', $journalEntry->linkedDebitNote) }}" class="text-decoration-none">
                                    {{ $journalEntry->linkedDebitNote->debit_note_number }}
                                </a>
                            </div>
                        @endif

                        @if($journalEntry->linkedCreditNote)
                            <div>
                                <strong>Linked Credit Note:</strong><br>
                                <a href="{{ route('credit-notes.show', $journalEntry->linkedCreditNote) }}" class="text-decoration-none">
                                    {{ $journalEntry->linkedCreditNote->credit_note_number }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Audit Information -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Audit Information</h5>
                </div>
                <div class="card-body">
                    <small class="text-muted">
                        <strong>Created:</strong> {{ $journalEntry->created_at->format('M d, Y H:i') }}<br>
                        @if($journalEntry->updated_at != $journalEntry->created_at)
                            <strong>Last Updated:</strong> {{ $journalEntry->updated_at->format('M d, Y H:i') }}<br>
                        @endif
                        @if($journalEntry->posted_at)
                            <strong>Posted:</strong> {{ $journalEntry->posted_at->format('M d, Y H:i') }}<br>
                        @endif
                        @if($journalEntry->approved_at)
                            <strong>Approved:</strong> {{ $journalEntry->approved_at->format('M d, Y H:i') }}<br>
                        @endif
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
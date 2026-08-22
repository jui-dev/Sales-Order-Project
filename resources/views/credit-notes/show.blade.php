@extends('layouts.app')

@php
    $customer  = $creditNote->customer;
    $invoice   = $creditNote->invoice;
    $return    = $creditNote->returnTransaction;
    $journal   = $creditNote->journalEntry;
    $items     = $creditNote->items ?? collect();

    $isIssued    = $creditNote->status === 'issued';
    $isPosted    = $creditNote->status === 'posted';
    $isCancelled = $creditNote->status === 'cancelled';

    // Separate steps, and the page's whole job is to keep them apart: posting
    // the note writes a draft journal entry, that entry is then reviewed, and
    // only posting it moves the accounts.
    $hasJournal        = $journal !== null;
    $journalPosted     = $hasJournal && $journal->status === 'posted';
    $journalApproved   = $hasJournal && $journal->status === 'approved';
    $journalDraft      = $hasJournal && $journal->status === 'draft';
    $awaitingNote      = $isIssued && ! $hasJournal;
    $awaitingApproval  = $journalDraft;
    $awaitingLedger    = $journalApproved;

    $journalStateLabel = $journalPosted ? 'posted' : ($journalApproved ? 'approved' : 'draft');
    $journalStateColor = $journalPosted ? 'success' : ($journalApproved ? 'info' : 'secondary');

    $totalUnits = $items->sum('quantity');

    // Stages for the rail, seen from this note.
    $stages = \App\Support\ReturnWorkflow::forNote($creditNote);
@endphp

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Credit Note {{ $creditNote->formatted_id }}</h1>
            <p class="text-muted mb-0">
                <span class="badge bg-{{ $creditNote->status_color }}">{{ $creditNote->status_display }}</span>
                @if($hasJournal)
                    <span class="ms-2 badge bg-{{ $journalStateColor }}">
                        Journal {{ $journalStateLabel }}
                    </span>
                @endif
                <span class="ms-2">${{ number_format($creditNote->total_amount, 2) }}</span>
                <span class="ms-2">·</span>
                <span class="ms-2">{{ $customer->name ?? 'Unknown customer' }}</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            @if($awaitingNote)
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#postNoteModal">
                    <i class="bi bi-check-circle me-1"></i> Post Credit Note
                </button>
            @endif
            {{-- The entry is approved and posted in Journal Entries, alongside every
                 other entry, so this page only points at it. --}}
            @if($hasJournal)
                <a href="{{ route('journal-entries.show', $journal) }}" class="btn btn-outline-primary">
                    <i class="bi bi-journal-text me-1"></i> View Journal Entry
                </a>
            @endif
            @unless($isCancelled)
                <a href="{{ route('credit-notes.download', $creditNote) }}" class="btn btn-outline-primary">
                    <i class="bi bi-download me-1"></i> Download PDF
                </a>
            @endunless
        </div>
    </div>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- The credit note is one stage of the return that produced it --}}
    @if($stages)
        <x-workflow-rail :stages="$stages" />
    @endif

    {{-- Headline figures --}}
    <div class="detail-card mb-4">
        <div class="detail-card__body">
            <div class="detail-figures">
                <div class="detail-figure">
                    <span class="detail-figure__label">Credited</span>
                    <span class="detail-figure__value detail-figure__value--lead">
                        ${{ number_format($creditNote->total_amount, 2) }}
                    </span>
                    <span class="detail-figure__note">Owed back to the customer</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Units Credited</span>
                    <span class="detail-figure__value">{{ number_format($totalUnits) }}</span>
                    <span class="detail-figure__note">
                        Across {{ $items->count() }} {{ Str::plural('line', $items->count()) }}
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Ledger</span>
                    <span class="detail-figure__value">
                        {{ $hasJournal ? ucfirst($journalStateLabel) : 'None' }}
                    </span>
                    <span class="detail-figure__note">
                        @if($journalPosted)
                            Affecting the accounts
                        @elseif($journalApproved)
                            Reviewed, not yet posted
                        @elseif($hasJournal)
                            No effect until posted
                        @else
                            Created when the note is posted
                        @endif
                    </span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Issued</span>
                    <span class="detail-figure__value">
                        {{ optional($creditNote->issue_date)->format('M d') ?: '—' }}
                    </span>
                    <span class="detail-figure__note">
                        {{ optional($creditNote->issue_date)->format('Y') ?: 'Not issued' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    @if($isCancelled)
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>This credit note has been cancelled.</strong>
            @if($creditNote->cancellation_reason)
                <br>Reason: {{ $creditNote->cancellation_reason }}
            @endif
        </div>
    @endif

    {{-- Where this sits, and what the next click actually does --}}
    <div class="detail-card mb-4">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-flag"></i></span>
            <div>
                <h2 class="detail-card__title">Status</h2>
                <p class="detail-card__subtitle">What has happened to the accounts, and what happens next</p>
            </div>
        </div>
        <div class="detail-card__body">
            <div class="detail-kv">
                <span class="detail-kv__label">Current Status</span>
                <span class="detail-kv__value">
                    <span class="badge bg-{{ $creditNote->status_color }}">{{ $creditNote->status_display }}</span>
                </span>
            </div>

            <div class="detail-panel mt-3 mb-0">
                @if($isCancelled)
                    This credit note was cancelled. Nothing is owed back to
                    {{ $customer->name ?? 'the customer' }} against it.
                @elseif($journalPosted)
                    The reversal is posted. Sales returns and accounts receivable have both moved by
                    ${{ number_format($creditNote->total_amount, 2) }}, so this now shows in the
                    financial statements.
                @else
                    This credit note is issued but has no journal entry, so it has no effect on the
                    accounts yet. Posting it books the reversal against
                    {{ $invoice->invoice_number ?? 'the original invoice' }} straight away.
                @endif
            </div>
        </div>
    </div>

    {{-- The accounting, in full --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <x-journal-ledger
                :entry="$journal"
                title="Journal Entry"
                subtitle="The double entry this credit note reverses the sale with"
                icon="bi-journal-text"
                empty="No journal entry yet. It is created when the credit note is posted." />
        </div>

        <div class="col-lg-5">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-link-45deg"></i></span>
                    <div>
                        <h2 class="detail-card__title">Raised From</h2>
                        <p class="detail-card__subtitle">The return and sale behind this credit</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="detail-kv">
                        <span class="detail-kv__label">Return</span>
                        <span class="detail-kv__value">
                            @if($return)
                                <a href="{{ route('returns.show', $return) }}">{{ $return->formatted_id }}</a>
                                <span class="text-muted small d-block">
                                    {{ number_format($return->quantity) }}
                                    {{ Str::plural('unit', $return->quantity) }} ·
                                    {{ ucfirst($return->status) }}
                                </span>
                            @else
                                <span class="text-muted">No return recorded</span>
                            @endif
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Original Invoice</span>
                        <span class="detail-kv__value">
                            @if($invoice)
                                <a href="{{ route('invoices.show', $invoice) }}">
                                    {{ $invoice->invoice_number ?: $invoice->formatted_id }}
                                </a>
                                <span class="text-muted small d-block">
                                    ${{ number_format($invoice->total, 2) }} ·
                                    {{ optional($invoice->invoice_date)->format('M d, Y') ?: 'No date' }}
                                </span>
                            @else
                                <span class="text-muted">No invoice linked</span>
                            @endif
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Customer</span>
                        <span class="detail-kv__value">
                            {{ $customer->name ?? '—' }}
                            @if($customer && $customer->email)
                                <span class="text-muted small d-block">{{ $customer->email }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Document Number</span>
                        <span class="detail-kv__value detail-kv__value--muted">
                            {{ $creditNote->credit_note_number ?: '—' }}
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Created</span>
                        <span class="detail-kv__value detail-kv__value--muted">
                            {{ optional($creditNote->created_at)->format('M d, Y H:i') ?: '—' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- What is being credited --}}
    <div class="detail-card note-items">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-list-ul"></i></span>
            <div>
                <h2 class="detail-card__title">Credited Products</h2>
                <p class="detail-card__subtitle">
                    Priced at what {{ $customer->name ?? 'the customer' }} originally paid.
                </p>
            </div>
        </div>
        <div class="detail-card__body detail-card__body--flush">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 3rem;">#</th>
                            <th>Product</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $index => $item)
                            <tr>
                                <td data-label="#">{{ $index + 1 }}</td>
                                <td data-label="Product">
                                    <strong>{{ $item->product_name ?: ($item->product->name ?? 'Unknown product') }}</strong>
                                    <span class="d-block text-muted small">
                                        SKU {{ $item->sku ?: ($item->product->sku ?? 'N/A') }}
                                        @if($item->notes)
                                            · {{ Str::headline($item->notes) }}
                                        @endif
                                    </span>
                                </td>
                                <td data-label="Quantity" class="text-end">{{ number_format($item->quantity) }}</td>
                                <td data-label="Unit Price" class="text-end">
                                    ${{ number_format($item->unit_price, 2) }}
                                </td>
                                <td data-label="Line Total" class="text-end fw-semibold">
                                    ${{ number_format($item->total_amount ?? $item->subtotal, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No line detail recorded for this credit note.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($items->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="2" class="text-muted">
                                    Total across {{ $items->count() }}
                                    {{ Str::plural('line', $items->count()) }}
                                </td>
                                <td data-label="Total quantity" class="text-end fw-semibold">
                                    {{ number_format($totalUnits) }}
                                </td>
                                <td></td>
                                <td data-label="Credit total" class="text-end fw-bold">
                                    ${{ number_format($creditNote->total_amount, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    @if($creditNote->notes)
        <div class="detail-card mt-4">
            <div class="detail-card__header">
                <span class="detail-card__step"><i class="bi bi-sticky"></i></span>
                <div>
                    <h2 class="detail-card__title">Notes</h2>
                    <p class="detail-card__subtitle">Recorded with the credit note</p>
                </div>
            </div>
            <div class="detail-card__body">
                <div class="detail-panel mb-0">{{ $creditNote->notes }}</div>
            </div>
        </div>
    @endif

    {{-- Issued -> draft journal entry --}}
    @if($awaitingNote)
        <div class="modal fade" id="postNoteModal" tabindex="-1" aria-labelledby="postNoteModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="postNoteModalLabel">Post Credit Note</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Posting {{ $creditNote->formatted_id }} will:</p>
                        <ul class="mb-3 ps-3">
                            <li class="mb-1">
                                Create a journal entry reversing
                                <strong>${{ number_format($creditNote->total_amount, 2) }}</strong>
                                of revenue and receivable against
                                {{ $invoice->invoice_number ?? 'the original invoice' }}
                            </li>
                            <li>Reverse the cost of the goods back into inventory</li>
                        </ul>
                        <div class="detail-panel mb-0">
                            <span class="d-block mb-1">
                                <strong>What does not happen yet:</strong> the entry is created as a
                                <strong>draft</strong>. It has no effect on the financial statements until
                                it is posted, which is a second step on this page.
                            </span>
                            <span class="text-muted small">
                                A credit note can only be posted once.
                            </span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form action="{{ route('credit-notes.post', $creditNote) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i> Post Credit Note
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('styles')
<style>
    /* Totals row: keep it readable once the table collapses to cards on mobile */
    @media (max-width: 768px) {
        .note-items tfoot tr {
            display: block;
            border-top: 1px solid #dee2e6;
        }

        .note-items tfoot td {
            display: block;
            text-align: right;
            padding: 0.35rem 0.75rem;
        }

        .note-items tfoot td:empty {
            display: none;
        }

        /* Mirror the tbody label treatment custom.css applies below 768px */
        .note-items tfoot td[data-label]::before {
            content: attr(data-label);
            float: left;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.85em;
        }
    }
</style>
@endpush

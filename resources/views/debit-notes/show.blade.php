@extends('layouts.app')

@php
    $vendor  = $debitNote->vendor;
    $bill    = $debitNote->supplierBill;
    $return  = $debitNote->returnTransaction;
    $journal = $debitNote->journalEntry;
    $items   = $debitNote->items ?? collect();

    $isIssued    = $debitNote->status === 'issued';
    $isPosted    = $debitNote->status === 'posted';
    $isCancelled = $debitNote->status === 'cancelled';

    // Separate steps: posting the note writes a draft journal entry, that entry
    // is then reviewed, and only posting it moves the accounts.
    $hasJournal       = $journal !== null;
    $journalPosted    = $hasJournal && $journal->status === 'posted';
    $journalApproved  = $hasJournal && $journal->status === 'approved';
    $journalDraft     = $hasJournal && $journal->status === 'draft';
    $awaitingNote     = $isIssued && ! $hasJournal;
    $awaitingApproval = $journalDraft;
    $awaitingLedger   = $journalApproved;

    $journalStateLabel = $journalPosted ? 'posted' : ($journalApproved ? 'approved' : 'draft');
    $journalStateColor = $journalPosted ? 'success' : ($journalApproved ? 'info' : 'secondary');

    $totalUnits = $items->sum('quantity');

    // Stages for the rail, seen from this note.
    $stages = \App\Support\ReturnWorkflow::forNote($debitNote);
@endphp

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Debit Note {{ $debitNote->formatted_id }}</h1>
            <p class="text-muted mb-0">
                <span class="badge bg-{{ $debitNote->status_color }}">{{ $debitNote->status_display }}</span>
                @if($hasJournal)
                    <span class="ms-2 badge bg-{{ $journalStateColor }}">
                        Journal {{ $journalStateLabel }}
                    </span>
                @endif
                <span class="ms-2">${{ number_format($debitNote->total_amount, 2) }}</span>
                <span class="ms-2">·</span>
                <span class="ms-2">{{ $vendor->name ?? 'Unknown vendor' }}</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            @if($awaitingNote)
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#postNoteModal">
                    <i class="bi bi-check-circle me-1"></i> Post Debit Note
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
                <a href="{{ route('debit-notes.download', $debitNote) }}" class="btn btn-outline-primary">
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

    {{-- The debit note is one stage of the return that produced it --}}
    @if($stages)
        <x-workflow-rail :stages="$stages" />
    @endif

    {{-- Headline figures --}}
    <div class="detail-card mb-4">
        <div class="detail-card__body">
            <div class="detail-figures">
                <div class="detail-figure">
                    <span class="detail-figure__label">Debited</span>
                    <span class="detail-figure__value detail-figure__value--lead">
                        ${{ number_format($debitNote->total_amount, 2) }}
                    </span>
                    <span class="detail-figure__note">Off what we owe the vendor</span>
                </div>
                <div class="detail-figure">
                    <span class="detail-figure__label">Units Returned</span>
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
                        {{ optional($debitNote->issue_date)->format('M d') ?: '—' }}
                    </span>
                    <span class="detail-figure__note">
                        {{ optional($debitNote->issue_date)->format('Y') ?: 'Not issued' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    @if($isCancelled)
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>This debit note has been cancelled.</strong>
            @if($debitNote->cancellation_reason)
                <br>Reason: {{ $debitNote->cancellation_reason }}
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
                    <span class="badge bg-{{ $debitNote->status_color }}">{{ $debitNote->status_display }}</span>
                </span>
            </div>

            <div class="detail-panel mt-3 mb-0">
                @if($isCancelled)
                    This debit note was cancelled, so nothing has come off what is owed to
                    {{ $vendor->name ?? 'the vendor' }}.
                @elseif($journalPosted)
                    The entry is posted. Accounts payable has been reduced by
                    ${{ number_format($debitNote->total_amount, 2) }} and the goods have come out of
                    inventory, so this now shows in the financial statements.
                @elseif($journalApproved)
                    The journal entry has been <strong>approved</strong> but not posted, so what we owe
                    {{ $vendor->name ?? 'the vendor' }} has not changed yet. Posting it is what gives this
                    debit note its financial effect.
                @elseif($hasJournal)
                    A <strong>draft</strong> journal entry exists. It has to be approved and then posted
                    in Journal Entries before what we owe {{ $vendor->name ?? 'the vendor' }} changes; neither has happened yet.
                @else
                    This debit note is issued but has no journal entry, so it has no effect on the accounts
                    yet. Posting it creates a draft entry against
                    {{ $bill->formatted_id ?? 'the supplier bill' }}, which is then posted separately.
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
                subtitle="Accounts payable down, inventory out — the goods were never sold"
                icon="bi-journal-text"
                empty="No journal entry yet. It is created when the debit note is posted." />
        </div>

        <div class="col-lg-5">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-link-45deg"></i></span>
                    <div>
                        <h2 class="detail-card__title">Raised From</h2>
                        <p class="detail-card__subtitle">The return and purchase behind this debit</p>
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
                        <span class="detail-kv__label">Supplier Bill</span>
                        <span class="detail-kv__value">
                            @if($bill)
                                <a href="{{ route('supplier-bills.show', $bill) }}">{{ $bill->formatted_id }}</a>
                                <span class="text-muted small d-block">
                                    ${{ number_format($bill->total_amount, 2) }} ·
                                    {{ ucfirst($bill->status) }}
                                </span>
                            @else
                                <span class="text-muted">No bill linked</span>
                            @endif
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Vendor</span>
                        <span class="detail-kv__value">
                            {{ $vendor->name ?? '—' }}
                            @if($vendor && $vendor->email)
                                <span class="text-muted small d-block">{{ $vendor->email }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Document Number</span>
                        <span class="detail-kv__value detail-kv__value--muted">
                            {{ $debitNote->debit_note_number ?: '—' }}
                        </span>
                    </div>
                    <div class="detail-kv">
                        <span class="detail-kv__label">Created</span>
                        <span class="detail-kv__value detail-kv__value--muted">
                            {{ optional($debitNote->created_at)->format('M d, Y H:i') ?: '—' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- What is being sent back --}}
    <div class="detail-card note-items">
        <div class="detail-card__header">
            <span class="detail-card__step"><i class="bi bi-list-ul"></i></span>
            <div>
                <h2 class="detail-card__title">Returned Products</h2>
                <p class="detail-card__subtitle">
                    Priced at what {{ $vendor->name ?? 'the vendor' }} originally charged.
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
                            <th class="text-end">Unit Cost</th>
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
                                <td data-label="Unit Cost" class="text-end">
                                    ${{ number_format($item->unit_price, 2) }}
                                </td>
                                <td data-label="Line Total" class="text-end fw-semibold">
                                    ${{ number_format($item->total_amount ?? $item->subtotal, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No line detail recorded for this debit note.
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
                                <td data-label="Debit total" class="text-end fw-bold">
                                    ${{ number_format($debitNote->total_amount, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    @if($debitNote->notes)
        <div class="detail-card mt-4">
            <div class="detail-card__header">
                <span class="detail-card__step"><i class="bi bi-sticky"></i></span>
                <div>
                    <h2 class="detail-card__title">Notes</h2>
                    <p class="detail-card__subtitle">Recorded with the debit note</p>
                </div>
            </div>
            <div class="detail-card__body">
                <div class="detail-panel mb-0">{{ $debitNote->notes }}</div>
            </div>
        </div>
    @endif

    {{-- Issued -> draft journal entry --}}
    @if($awaitingNote)
        <div class="modal fade" id="postNoteModal" tabindex="-1" aria-labelledby="postNoteModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="postNoteModalLabel">Post Debit Note</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Posting {{ $debitNote->formatted_id }} will:</p>
                        <ul class="mb-3 ps-3">
                            <li class="mb-1">
                                Create a journal entry reducing accounts payable by
                                <strong>${{ number_format($debitNote->total_amount, 2) }}</strong>
                                against {{ $bill->formatted_id ?? 'the supplier bill' }}
                            </li>
                            <li>Take the returned goods back out of inventory</li>
                        </ul>
                        <div class="detail-panel mb-0">
                            <span class="d-block mb-1">
                                <strong>What does not happen yet:</strong> the entry is created as a
                                <strong>draft</strong>. It has no effect on the financial statements until
                                it is posted, which is a second step on this page.
                            </span>
                            <span class="text-muted small">
                                Cost of goods sold is not touched — these goods were returned, not sold.
                            </span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form action="{{ route('debit-notes.post', $debitNote) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i> Post Debit Note
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

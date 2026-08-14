@props([
    'entry'    => null,
    'title'    => 'Journal Entry',
    'subtitle' => null,
    'icon'     => 'bi-journal-text',
    'empty'    => 'Not created yet.',
])

@php
    // Passing :entry="$x" where $x is null yields an empty Collection here,
    // not null - the @props default only applies when the attribute is
    // absent entirely. Normalise before touching it.
    $entry = $entry instanceof \App\Models\JournalEntry ? $entry : null;

    // Sum the already-loaded lines. JournalEntry::totalDebit()/totalCredit()
    // run a fresh query each call, which would undo the eager loading.
    $lines       = $entry?->lines ?? collect();
    $totalDebit  = $lines->sum('debit');
    $totalCredit = $lines->sum('credit');
    $balanced    = round($totalDebit, 2) === round($totalCredit, 2);

    $statusBadge = [
        'draft'    => 'secondary',
        'posted'   => 'success',
        'approved' => 'primary',
        'rejected' => 'danger',
    ][$entry?->status] ?? 'secondary';
@endphp

<div class="detail-card h-100">
    <div class="detail-card__header">
        <span class="detail-card__step"><i class="bi {{ $icon }}"></i></span>
        <div>
            <h2 class="detail-card__title">{{ $title }}</h2>
            @if($subtitle)
                <p class="detail-card__subtitle">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    <div class="detail-card__body">
        @if(! $entry)
            <p class="text-muted mb-0">{{ $empty }}</p>
        @else
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <a href="{{ route('journal-entries.show', $entry) }}" class="fw-semibold">
                    {{ $entry->formatted_id ?? '#' . $entry->id }}
                </a>
                <span class="badge bg-{{ $statusBadge }}">{{ ucfirst($entry->status ?? 'draft') }}</span>
                <span class="text-muted small">
                    {{ optional($entry->entry_date)->format('M d, Y') ?: '—' }}
                </span>
            </div>

            <div class="table-responsive">
                <table class="detail-ledger">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Debit</th>
                            <th>Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lines as $line)
                            <tr>
                                <td>
                                    {{ $line->account->name ?? 'Unknown account' }}
                                    @if($line->account && $line->account->code)
                                        <span class="detail-ledger__code">{{ $line->account->code }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($line->debit > 0)
                                        ${{ number_format($line->debit, 2) }}
                                    @else
                                        <span class="detail-ledger__muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($line->credit > 0)
                                        ${{ number_format($line->credit, 2) }}
                                    @else
                                        <span class="detail-ledger__muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="detail-ledger__muted">No lines recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($lines->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td>{{ $balanced ? 'Balanced' : 'Out of balance' }}</td>
                                <td>${{ number_format($totalDebit, 2) }}</td>
                                <td>${{ number_format($totalCredit, 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @unless($balanced)
                <p class="text-danger small mb-0 mt-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Debits and credits do not match.
                </p>
            @endunless
        @endif
    </div>
</div>

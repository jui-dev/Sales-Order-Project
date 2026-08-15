@extends('layouts.app')

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Trial Balance</h1>
            <p class="text-muted mb-0">
                As at {{ $endDate ? date('M j, Y', strtotime($endDate)) : date('M j, Y') }}
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <a href="{{ route('reports.trial-balance', array_merge(request()->query(), ['export' => 'pdf'])) }}"
               class="btn btn-outline-primary">
                <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
            </a>
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print
            </button>
        </div>
    </div>
@endsection

@section('content')
    <x-statement-tabs active="trial-balance" />

    <form method="get" action="{{ route('reports.trial-balance') }}" class="statement-period mb-4 d-print-none">
        <div class="statement-period__field">
            <label for="end_date" class="statement-period__label">As at</label>
            <input type="date" id="end_date" name="end_date" class="form-control" value="{{ $endDate }}">
        </div>
        <button type="submit" class="btn btn-primary">Apply</button>

        <div class="statement-period__presets">
            <a href="{{ route('reports.trial-balance', ['end_date' => now()->endOfMonth()->toDateString()]) }}"
               class="btn btn-sm btn-outline-secondary">End of this month</a>
            <a href="{{ route('reports.trial-balance', ['end_date' => now()->toDateString()]) }}"
               class="btn btn-sm btn-outline-secondary">Today</a>
        </div>
    </form>

    <x-statement-basis :basis="$basis ?? []" class="mb-3" />

    {{-- A trial balance has exactly one job: prove the ledger is square. That
         verdict belongs above the figures, not underneath them. --}}
    <div class="statement-verdict {{ $isBalanced ? 'statement-verdict--ok' : 'statement-verdict--off' }} mb-4">
        <i class="bi {{ $isBalanced ? 'bi-check-circle' : 'bi-exclamation-octagon' }}" aria-hidden="true"></i>
        <strong>{{ $isBalanced ? 'The ledger is in balance.' : 'The ledger is out of balance.' }}</strong>
        <span class="statement-verdict__equation">
            Debits ${{ number_format($totalDebit, 2) }} · Credits ${{ number_format($totalCredit, 2) }}
            @if(! $isBalanced)
                · Difference ${{ number_format(abs($difference ?? 0), 2) }}
            @endif
        </span>
    </div>

    <div class="detail-card">
        <div class="detail-card__body">
            @if($balances->isEmpty())
                <div class="statement__empty">
                    <p class="mb-2">No account carries a balance as at this date.</p>
                    <p class="text-muted mb-0">
                        Only posted entries reach the ledger. Approve and post the entries waiting
                        in the journal to see them here.
                    </p>
                </div>
            @else
                <div class="statement statement--wide">
                    <div class="statement__row" style="border-bottom: 1px solid var(--border-color);">
                        <span class="statement__label statement__group-title mb-0">Account</span>
                        <span class="statement__amount statement__group-title">Debit</span>
                        <span class="statement__amount statement__group-title">Credit</span>
                    </div>

                    @foreach($balances as $row)
                        <div class="statement__row">
                            <span class="statement__label">
                                <span class="statement__code">{{ $row['account']->code }}</span>
                                {{ $row['account']->name }}
                            </span>
                            <span class="statement__amount {{ $row['debit'] > 0 ? '' : 'statement__amount--muted' }}">
                                {{ $row['debit'] > 0 ? '$'.number_format($row['debit'], 2) : '—' }}
                            </span>
                            <span class="statement__amount {{ $row['credit'] > 0 ? '' : 'statement__amount--muted' }}">
                                {{ $row['credit'] > 0 ? '$'.number_format($row['credit'], 2) : '—' }}
                            </span>
                        </div>
                    @endforeach

                    <div class="statement__row statement__row--total">
                        <span class="statement__label">Totals</span>
                        <span class="statement__amount">${{ number_format($totalDebit, 2) }}</span>
                        <span class="statement__amount">${{ number_format($totalCredit, 2) }}</span>
                    </div>
                </div>

                @if(($emptyAccountCount ?? 0) > 0)
                    <p class="statement__meta justify-content-start mt-3 mb-0">
                        {{ $emptyAccountCount }} of {{ $accountCount }} accounts carry no balance and are not listed.
                    </p>
                @endif
            @endif
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Accounting Health</h1>
            <p class="text-muted mb-0">
                The general ledger against everything else that knows the same numbers,
                as at {{ date('M j, Y', strtotime($asOf)) }}.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print
            </button>
        </div>
    </div>
@endsection

@section('content')
    <form method="get" action="{{ route('accounting.health') }}" class="statement-period mb-4 d-print-none">
        <div class="statement-period__field">
            <label for="as_of" class="statement-period__label">As at</label>
            <input type="date" id="as_of" name="as_of" class="form-control" value="{{ $asOf }}">
        </div>
        <button type="submit" class="btn btn-primary">Apply</button>
    </form>

    {{-- The headline: one line telling you whether to read further. --}}
    <div class="alert {{ $allPassed ? 'alert-success' : 'alert-danger' }} d-flex align-items-start gap-2">
        <i class="bi {{ $allPassed ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' }} mt-1"></i>
        <div>
            <strong>{{ $allPassed ? 'Everything ties out.' : 'Something does not tie out.' }}</strong>
            <div class="small">
                @if($allPassed)
                    Every control account agrees with the ledger behind it, and the books balance.
                @else
                    One or more checks below disagree. Each names what the ledger says and what the
                    documents say, so the difference can be traced rather than guessed at.
                @endif
            </div>
        </div>
    </div>

    @if($unresolvableRoles)
        <div class="alert alert-warning">
            <strong>The chart of accounts cannot serve every posting rule.</strong>
            <div class="small">
                These roles have no postable account behind them, so any document that needs one
                will fail when somebody tries to use it:
                <code>{{ implode('</code>, <code>', $unresolvableRoles) }}</code>.
                Run <code>php artisan accounting:rebuild</code> or reseed the chart to restore them.
            </div>
        </div>
    @endif

    @if($pendingManual > 0)
        <div class="alert alert-info small mb-4">
            {{ $pendingManual }} manual {{ Str::plural('entry', $pendingManual) }}
            {{ $pendingManual === 1 ? 'is' : 'are' }} still awaiting review, so
            {{ $pendingManual === 1 ? 'it does' : 'they do' }} not appear in any figure below.
            <a href="{{ route('journal-entries.index', ['origin' => 'manual', 'status' => 'draft']) }}">Review them</a>.
        </div>
    @endif

    {{-- The checks themselves. --}}
    @foreach($checks as $check)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    @if(! $check->wasMade())
                        <span class="badge bg-secondary">N/A</span>
                    @else
                        <span class="badge bg-{{ $check->passed ? 'success' : 'danger' }}">
                            {{ $check->passed ? 'OK' : 'Out' }}
                        </span>
                    @endif
                    <strong>{{ $check->title }}</strong>
                </div>
                @unless($check->passed)
                    <span class="text-danger fw-semibold">
                        Out by {{ number_format((float) $check->difference->toDecimal(), 2) }}
                    </span>
                @endunless
            </div>

            <div class="card-body">
                <p class="text-muted small mb-3">{{ $check->explanation }}</p>

                {{-- A check that could not be answered says so, rather than
                     showing two zeroes that read as agreement. --}}
                @unless($check->wasMade())
                    <p class="text-muted small mb-0"><em>{{ $check->unavailable }}</em></p>
                @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th></th>
                                <th class="text-end">Ledger</th>
                                <th class="text-end">Expected</th>
                                <th class="text-end">Difference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="fw-semibold">
                                <td>Total</td>
                                <td class="text-end">{{ number_format((float) $check->ledger->toDecimal(), 2) }}</td>
                                <td class="text-end">{{ number_format((float) $check->expected->toDecimal(), 2) }}</td>
                                <td class="text-end {{ $check->passed ? '' : 'text-danger' }}">
                                    {{ number_format((float) $check->difference->toDecimal(), 2) }}
                                </td>
                            </tr>

                            {{-- Only the rows that disagree: a passing check has
                                 nothing anyone needs to read line by line. --}}
                            @foreach($check->discrepancies() as $row)
                                <tr>
                                    <td class="ps-4 text-muted">{{ $row['label'] }}</td>
                                    <td class="text-end">{{ number_format((float) $row['ledger']->toDecimal(), 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $row['expected']->toDecimal(), 2) }}</td>
                                    <td class="text-end text-danger">
                                        {{ number_format((float) $row['difference']->toDecimal(), 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endunless
            </div>
        </div>
    @endforeach

    {{-- Periods, because a closed one is the usual reason a posting was refused. --}}
    @if($periods->isNotEmpty())
        <div class="card">
            <div class="card-header"><strong>Fiscal periods</strong></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Status</th>
                            <th>Closing entry</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($periods as $period)
                            <tr>
                                <td>{{ $period->label() }}</td>
                                <td>{{ $period->starts_on?->format('M j, Y') }}</td>
                                <td>{{ $period->ends_on?->format('M j, Y') }}</td>
                                <td>
                                    <span class="badge bg-{{ $period->isOpen() ? 'success' : ($period->isLocked() ? 'dark' : 'secondary') }}">
                                        {{ ucfirst($period->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if($period->closingEntry)
                                        <a href="{{ route('journal-entries.show', $period->closingEntry) }}">
                                            {{ $period->closingEntry->formatted_id }}
                                        </a>
                                    @else
                                        <span class="text-muted">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

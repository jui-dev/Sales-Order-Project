@extends('layouts.app')

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Balance Sheet</h1>
            <p class="text-muted mb-0">
                As at {{ $endDate ? date('M j, Y', strtotime($endDate)) : date('M j, Y') }}
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <a href="{{ route('reports.balance-sheet', array_merge(request()->query(), ['export' => 'pdf'])) }}"
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
    <x-statement-tabs active="balance-sheet" />

    <form method="get" action="{{ route('reports.balance-sheet') }}" class="statement-period mb-4 d-print-none">
        <div class="statement-period__field">
            <label for="end_date" class="statement-period__label">As at</label>
            <input type="date" id="end_date" name="end_date" class="form-control" value="{{ $endDate }}">
        </div>
        <button type="submit" class="btn btn-primary">Apply</button>

        <div class="statement-period__presets">
            <a href="{{ route('reports.balance-sheet', ['end_date' => now()->endOfMonth()->toDateString()]) }}"
               class="btn btn-sm btn-outline-secondary">End of this month</a>
            <a href="{{ route('reports.balance-sheet', ['end_date' => now()->toDateString()]) }}"
               class="btn btn-sm btn-outline-secondary">Today</a>
        </div>
    </form>

    <x-statement-basis :basis="$basis ?? []" class="mb-3" />

    {{-- What a balance sheet asserts, stated as the assertion it is. --}}
    <div class="statement-verdict {{ $isBalanced ? 'statement-verdict--ok' : 'statement-verdict--off' }} mb-4">
        <i class="bi {{ $isBalanced ? 'bi-check-circle' : 'bi-exclamation-octagon' }}" aria-hidden="true"></i>
        <strong>{{ $isBalanced ? 'Assets equal liabilities plus equity.' : 'Assets do not equal liabilities plus equity.' }}</strong>
        <span class="statement-verdict__equation">
            ${{ number_format($totalAssets, 2) }} = ${{ number_format($totalLiabilities, 2) }}
            + ${{ number_format($totalEquity, 2) }}
            @if(! $isBalanced)
                · Out by ${{ number_format(abs($difference ?? 0), 2) }}
            @endif
        </span>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-box-seam"></i></span>
                    <div>
                        <h2 class="detail-card__title">Assets</h2>
                        <p class="detail-card__subtitle">What the business owns</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="statement">
                        @forelse($assets as $row)
                            <x-statement-row
                                :code="$row['account']->code"
                                :label="$row['account']->name"
                                :amount="$row['balance']" />
                        @empty
                            <div class="statement__empty">No asset account carries a balance.</div>
                        @endforelse

                        <x-statement-row variant="total" label="Total assets" :amount="$totalAssets" />
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="detail-card h-100">
                <div class="detail-card__header">
                    <span class="detail-card__step"><i class="bi bi-scales"></i></span>
                    <div>
                        <h2 class="detail-card__title">Liabilities &amp; Equity</h2>
                        <p class="detail-card__subtitle">Who has a claim on it</p>
                    </div>
                </div>
                <div class="detail-card__body">
                    <div class="statement">
                        <div class="statement__group">
                            <p class="statement__group-title">Liabilities</p>

                            @forelse($liabilities as $row)
                                <x-statement-row
                                    :code="$row['account']->code"
                                    :label="$row['account']->name"
                                    :amount="$row['balance']" />
                            @empty
                                <div class="statement__empty">No liability account carries a balance.</div>
                            @endforelse

                            <x-statement-row variant="subtotal" label="Total liabilities" :amount="$totalLiabilities" />
                        </div>

                        <div class="statement__group">
                            <p class="statement__group-title">Equity</p>

                            @foreach($equity as $row)
                                <x-statement-row
                                    :code="$row['account']->code"
                                    :label="$row['account']->name"
                                    :amount="$row['balance']" />
                            @endforeach

                            {{-- Profit belongs to the owners the moment it is earned, but it
                                 only reaches an equity account when the books are closed and
                                 nothing here posts a closing entry. Deriving it keeps the
                                 statement honest instead of leaving the value out. --}}
                            <x-statement-row
                                label="Current period earnings"
                                :amount="$currentPeriodEarnings"
                                note="Not yet closed to retained earnings" />

                            <x-statement-row variant="subtotal" label="Total equity" :amount="$totalEquity" />
                        </div>

                        <x-statement-row
                            variant="total"
                            label="Total liabilities &amp; equity"
                            :amount="$liabEqTotal" />
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

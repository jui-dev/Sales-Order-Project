@extends('layouts.app')

@php
    // AppServiceProvider registers a global composer that swaps any null view
    // variable for an empty Collection. A withheld margin is deliberately null,
    // so it arrives here as an object and a `=== null` test would miss it.
    $grossMargin = is_numeric($grossMargin) ? (float) $grossMargin : null;
    $netMargin = is_numeric($netMargin) ? (float) $netMargin : null;

    $hasActivity = $grossRevenue->isNotEmpty()
        || $revenueDeductions->isNotEmpty()
        || $costOfSales->isNotEmpty()
        || $operatingExpenses->isNotEmpty();

    $periodLabel = trim(
        ($startDate ? date('M j, Y', strtotime($startDate)) : '')
        . ($startDate && $endDate ? ' – ' : '')
        . ($endDate ? date('M j, Y', strtotime($endDate)) : '')
    );
@endphp

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Income Statement</h1>
            <p class="text-muted mb-0">
                @if($periodLabel)
                    For the period {{ $periodLabel }}
                @else
                    All periods
                @endif
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <a href="{{ route('reports.income-statement', array_merge(request()->query(), ['export' => 'pdf'])) }}"
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
    <x-statement-tabs active="income-statement" />

    {{-- Period control. Two dates never warranted a titled card. --}}
    <form method="get" action="{{ route('reports.income-statement') }}" class="statement-period mb-4 d-print-none">
        <div class="statement-period__field">
            <label for="start_date" class="statement-period__label">From</label>
            <input type="date" id="start_date" name="start_date" class="form-control" value="{{ $startDate }}">
        </div>
        <div class="statement-period__field">
            <label for="end_date" class="statement-period__label">To</label>
            <input type="date" id="end_date" name="end_date" class="form-control" value="{{ $endDate }}">
        </div>
        <button type="submit" class="btn btn-primary">Apply</button>

        <div class="statement-period__presets">
            <a href="{{ route('reports.income-statement', ['start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->toDateString()]) }}"
               class="btn btn-sm btn-outline-secondary">This month</a>
            <a href="{{ route('reports.income-statement', ['start_date' => now()->startOfQuarter()->toDateString(), 'end_date' => now()->toDateString()]) }}"
               class="btn btn-sm btn-outline-secondary">This quarter</a>
            <a href="{{ route('reports.income-statement', ['start_date' => now()->startOfYear()->toDateString(), 'end_date' => now()->toDateString()]) }}"
               class="btn btn-sm btn-outline-secondary">Year to date</a>
        </div>
    </form>

    <x-statement-basis :basis="$basis ?? []" class="mb-4" />

    <div class="detail-card">
        <div class="detail-card__body">
            @if(! $hasActivity)
                {{-- An empty statement should say what would fill it. --}}
                <div class="statement__empty">
                    <p class="mb-2">No posted revenue or costs fall in this period.</p>
                    <p class="text-muted mb-0">
                        Widen the dates, or post the entries waiting on the ledger — an entry counts
                        here only once it has been approved and posted.
                    </p>
                </div>
            @else
                <div class="statement">
                    {{-- Revenue, net of what was given back --}}
                    <div class="statement__group">
                        <p class="statement__group-title">Revenue</p>

                        @forelse($grossRevenue as $row)
                            <x-statement-row
                                :code="$row['account']->code"
                                :label="$row['account']->name"
                                :amount="$row['amount']" />
                        @empty
                            <div class="statement__empty">No revenue posted in this period.</div>
                        @endforelse

                        @foreach($revenueDeductions as $row)
                            <x-statement-row
                                variant="deduction"
                                :code="$row['account']->code"
                                :label="$row['account']->name"
                                :amount="$row['amount']" />
                        @endforeach

                        <x-statement-row variant="subtotal" label="Net revenue" :amount="$netRevenue" />
                    </div>

                    {{-- What those sales cost to fulfil --}}
                    <div class="statement__group">
                        <p class="statement__group-title">Cost of sales</p>

                        @forelse($costOfSales as $row)
                            <x-statement-row
                                :variant="$row['account']->is_contra ? 'deduction' : null"
                                :code="$row['account']->code"
                                :label="$row['account']->name"
                                :amount="$row['amount']" />
                        @empty
                            <div class="statement__empty">No cost of sales posted in this period.</div>
                        @endforelse

                        <x-statement-row variant="subtotal" label="Total cost of sales" :amount="$totalCostOfSales" />

                        {{-- The line the statement exists to produce. --}}
                        <x-statement-row
                            variant="result"
                            label="Gross profit"
                            :amount="$grossProfit"
                            :note="$grossMargin === null ? 'Margin not meaningful without positive revenue' : 'Gross margin ' . number_format($grossMargin, 1) . '%'" />
                    </div>

                    {{-- Everything below the gross profit line --}}
                    <div class="statement__group">
                        <p class="statement__group-title">Operating expenses</p>

                        @forelse($operatingExpenses as $row)
                            <x-statement-row
                                :variant="$row['account']->is_contra ? 'deduction' : null"
                                :code="$row['account']->code"
                                :label="$row['account']->name"
                                :amount="$row['amount']" />
                        @empty
                            <div class="statement__empty">No operating expenses posted in this period.</div>
                        @endforelse

                        <x-statement-row
                            variant="subtotal"
                            label="Total operating expenses"
                            :amount="$totalOperatingExpenses" />
                    </div>

                    <x-statement-row
                        variant="total"
                        label="Net income"
                        :amount="$netIncome"
                        :note="$netMargin === null ? null : 'Net margin ' . number_format($netMargin, 1) . '%'" />
                </div>
            @endif
        </div>
    </div>
@endsection

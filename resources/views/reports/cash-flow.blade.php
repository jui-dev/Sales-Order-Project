@extends('layouts.app')

@php
    $periodLabel = trim(
        ($startDate ? date('M j, Y', strtotime($startDate)) : '')
        . ($startDate && $endDate ? ' – ' : '')
        . ($endDate ? date('M j, Y', strtotime($endDate)) : '')
    );

    $sections = [
        [
            'title' => 'Operating activities',
            'blurb' => 'Cash from trading — customers paying, suppliers being paid',
            'rows' => $operating,
            'total' => $operatingTotal,
            'empty' => 'No cash moved through trading in this period.',
        ],
        [
            'title' => 'Investing activities',
            'blurb' => 'Cash spent on or raised from long-lived assets',
            'rows' => $investing,
            'total' => $investingTotal,
            'empty' => 'No cash moved through investing in this period.',
        ],
        [
            'title' => 'Financing activities',
            'blurb' => 'Owner capital and borrowing',
            'rows' => $financing,
            'total' => $financingTotal,
            'empty' => 'No cash moved through financing in this period.',
        ],
    ];
@endphp

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Cash Flow</h1>
            <p class="text-muted mb-0">
                @if($periodLabel)
                    For the period {{ $periodLabel }}
                @else
                    All periods
                @endif
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <a href="{{ route('reports.cash-flow', array_merge(request()->query(), ['export' => 'pdf'])) }}"
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
    <x-statement-tabs active="cash-flow" />

    <form method="get" action="{{ route('reports.cash-flow') }}" class="statement-period mb-4 d-print-none">
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
            <a href="{{ route('reports.cash-flow', ['start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->toDateString()]) }}"
               class="btn btn-sm btn-outline-secondary">This month</a>
            <a href="{{ route('reports.cash-flow', ['start_date' => now()->startOfQuarter()->toDateString(), 'end_date' => now()->toDateString()]) }}"
               class="btn btn-sm btn-outline-secondary">This quarter</a>
            <a href="{{ route('reports.cash-flow', ['start_date' => now()->startOfYear()->toDateString(), 'end_date' => now()->toDateString()]) }}"
               class="btn btn-sm btn-outline-secondary">Year to date</a>
        </div>
    </form>

    <x-statement-basis :basis="$basis ?? []" class="mb-3" />

    {{-- Movement is only believable if it lands on the closing balance. --}}
    <div class="statement-verdict {{ $reconciles ? 'statement-verdict--ok' : 'statement-verdict--off' }} mb-4">
        <i class="bi {{ $reconciles ? 'bi-check-circle' : 'bi-exclamation-octagon' }}" aria-hidden="true"></i>
        <strong>
            {{ $reconciles ? 'Movement reconciles to the cash balance.' : 'Movement does not reconcile to the cash balance.' }}
        </strong>
        <span class="statement-verdict__equation">
            Opening ${{ number_format($openingCash, 2) }}
            {{ $netChange < 0 ? '−' : '+' }} ${{ number_format(abs($netChange), 2) }}
            = ${{ number_format($closingCash, 2) }}
        </span>
    </div>

    <div class="detail-card">
        <div class="detail-card__body">
            <div class="statement">
                <x-statement-row label="Cash at start of period" :amount="$openingCash" />

                @foreach($sections as $section)
                    <div class="statement__group">
                        <p class="statement__group-title">{{ $section['title'] }}</p>

                        @forelse($section['rows'] as $row)
                            <x-statement-row
                                :label="$row['description']"
                                :code="optional($row['date'])->format('M j')"
                                :amount="$row['amount']" />
                        @empty
                            <div class="statement__empty">{{ $section['empty'] }}</div>
                        @endforelse

                        <x-statement-row
                            variant="subtotal"
                            :label="'Net cash from ' . lcfirst($section['title'])"
                            :amount="$section['total']" />
                    </div>
                @endforeach

                <x-statement-row variant="result" label="Net movement in cash" :amount="$netChange" />
                <x-statement-row variant="total" label="Cash at end of period" :amount="$closingCash" />
            </div>
        </div>
    </div>
@endsection

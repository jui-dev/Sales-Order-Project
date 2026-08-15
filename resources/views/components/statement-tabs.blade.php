@props([
    'active' => null,
])

@php
    // The four statements answer different questions about the same ledger, so
    // moving between them is a normal part of reading any one of them. Dates
    // carry across because re-entering the period every time is the whole
    // friction being removed.
    $carry = array_filter(request()->only(['start_date', 'end_date']));

    $statements = [
        'trial-balance'    => ['label' => 'Trial Balance',    'route' => 'reports.trial-balance'],
        'income-statement' => ['label' => 'Income Statement', 'route' => 'reports.income-statement'],
        'balance-sheet'    => ['label' => 'Balance Sheet',    'route' => 'reports.balance-sheet'],
        'cash-flow'        => ['label' => 'Cash Flow',        'route' => 'reports.cash-flow'],
    ];
@endphp

<nav class="report-tabs d-print-none" aria-label="Financial statements">
    @foreach($statements as $key => $statement)
        <a href="{{ route($statement['route'], $carry) }}"
           class="report-tabs__link {{ $active === $key ? 'report-tabs__link--active' : '' }}"
           @if($active === $key) aria-current="page" @endif>
            {{ $statement['label'] }}
        </a>
    @endforeach
</nav>

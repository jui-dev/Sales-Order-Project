@extends('layouts.app')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Daily Profit Report</h1>
    <button class="btn btn-primary" onclick="window.print()">
        <i class="bi bi-printer me-1"></i> Print Report
    </button>
</div>
@endsection

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12 mb-4">
        <p class="text-muted">
            Showing profit data from {{ date('M d, Y', strtotime($startDate)) }} 
            to {{ date('M d, Y', strtotime($endDate)) }}
        </p>
        {{-- What the figures are, stated plainly. This page used to sum order
             lines, so a sale that had been returned and credited kept its
             profit for ever and a pending order booked profit before it
             shipped. It now reads the ledger, which is why it agrees with the
             income statement. --}}
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <strong>How these figures are built:</strong> from posted journal entries only, so every
            total here is <strong>net of returns</strong> and agrees with the Income Statement for the
            same range. An order that has not been invoiced is not yet revenue and does not appear.
            <ul class="mb-0 mt-2">
                <li>Warehouse sales: direct sales from a warehouse to customers</li>
                <li>Retailer sales: sales through retail locations</li>
                <li>Revenue is dated to the invoice and cost to the shipment, so a single day can
                    show one without the other. The period total is unaffected.</li>
            </ul>
        </div>

        @if(!($basis['is_complete'] ?? true))
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                {{ $basis['pending_count'] }} journal
                {{ Str::plural('entry', $basis['pending_count']) }}
                totalling ${{ number_format($basis['pending_total'], 2) }} in this range
                {{ $basis['pending_count'] === 1 ? 'is' : 'are' }} not posted yet, so
                {{ $basis['pending_count'] === 1 ? 'it is' : 'they are' }} not counted below.
            </div>
        @endif
    </div>
</div>

@if($dailyProfits->isEmpty())
    {{-- The old wording said "completed transactions" while the query behind
         it counted everything that was not cancelled. Both halves are now
         true: nothing is posted in this range. --}}
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Nothing has been posted to the ledger in this date range, so there is no profit to report.
        @if(!($basis['is_complete'] ?? true))
            {{ $basis['pending_count'] }} unposted
            {{ Str::plural('entry', $basis['pending_count']) }}
            totalling ${{ number_format($basis['pending_total'], 2) }}
            {{ $basis['pending_count'] === 1 ? 'is' : 'are' }} waiting.
        @endif
    </div>
@else
    @if(isset($summary))
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Overall Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Net Revenue</div>
                                <div class="h4">${{ number_format($summary['total_revenue'], 2) }}</div>
                                <div class="text-muted small">
                                    ${{ number_format($summary['gross_revenue'], 2) }} gross
                                </div>
                            </div>
                        </div>
                        {{-- Returns are shown rather than netted away silently: a month
                             with none and a month whose sales all came back are not the
                             same month, and the old page could not tell them apart. --}}
                        <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Returns</div>
                                <div class="h4 {{ $summary['total_returns'] > 0 ? 'text-danger' : '' }}">
                                    @if($summary['total_returns'] > 0)-@endif${{ number_format($summary['total_returns'], 2) }}
                                </div>
                                @if($summary['total_discounts'] > 0)
                                    <div class="text-muted small">
                                        ${{ number_format($summary['total_discounts'], 2) }} discounts
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Total Profit</div>
                                <div class="h4">${{ number_format($summary['total_profit'], 2) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Margin</div>
                                <div class="h4">{{ number_format($summary['average_margin'], 1) }}%</div>
                                <div class="text-muted small">
                                    {{ $summary['products_count'] }}
                                    {{ Str::plural('product', $summary['products_count']) }}
                                    over {{ $summary['days_count'] }}
                                    {{ Str::plural('day', $summary['days_count']) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Revenue posted without a line breakdown carries no product, so
                         it counts in the totals but appears in none of the rows below.
                         Stating the gap is what stops the two from silently disagreeing. --}}
                    @if(abs($summary['unattributed']) >= 0.01)
                        <p class="text-muted small mb-0 mt-3">
                            ${{ number_format(abs($summary['unattributed']), 2) }} of profit is not
                            attributed to any product - it was posted without a line breakdown - so it
                            is counted in the totals above but not in the tables below.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Warehouse Sales</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <div class="text-muted small">Products</div>
                            <div class="h5">{{ number_format($summary['warehouse_products']) }}</div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="text-muted small">Revenue</div>
                            <div class="h5">${{ number_format($summary['warehouse_revenue'], 2) }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Profit</div>
                            <div class="h5">${{ number_format($summary['warehouse_profit'], 2) }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Margin</div>
                            <div class="h5">{{ number_format($summary['warehouse_margin'], 1) }}%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Retailer Sales</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <div class="text-muted small">Products</div>
                            <div class="h5">{{ number_format($summary['retailer_products']) }}</div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="text-muted small">Revenue</div>
                            <div class="h5">${{ number_format($summary['retailer_revenue'], 2) }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Profit</div>
                            <div class="h5">${{ number_format($summary['retailer_profit'], 2) }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Margin</div>
                            <div class="h5">{{ number_format($summary['retailer_margin'], 1) }}%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Filter Report</h5>
                </div>
                <div class="card-body">
                    <form method="get" action="{{ route('reports.daily-profit') }}" class="row g-3">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $startDate }}">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $endDate }}">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-filter me-1"></i> Apply Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Daily Profit Summary</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Products</th>
                                    <th class="text-end">Gross Revenue</th>
                                    <th class="text-end">Returns</th>
                                    <th class="text-end">Net Revenue</th>
                                    <th class="text-end">Total Cost</th>
                                    <th class="text-end">Total Profit</th>
                                    <th class="text-end">Margin %</th>
                                    <th class="text-end">Warehouse Profit</th>
                                    <th class="text-end">Retailer Profit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dailyTotals as $day)
                                    <tr>
                                        <td>{{ date('M d, Y', strtotime($day['date'])) }}</td>
                                        <td>{{ $day['products_count'] }}</td>
                                        <td class="text-end">${{ number_format($day['gross_revenue'], 2) }}</td>
                                        <td class="text-end {{ $day['total_returns'] > 0 ? 'text-danger' : 'text-muted' }}">
                                            @if($day['total_returns'] > 0)-@endif${{ number_format($day['total_returns'], 2) }}
                                        </td>
                                        <td class="text-end">${{ number_format($day['total_revenue'], 2) }}</td>
                                        <td class="text-end">${{ number_format($day['total_cost'], 2) }}</td>
                                        <td class="text-end">${{ number_format($day['total_profit'], 2) }}</td>
                                        <td class="text-end">
                                            @if($day['total_revenue'] > 0)
                                                {{ number_format(($day['total_profit'] / $day['total_revenue']) * 100, 1) }}%
                                            @else
                                                0.0%
                                            @endif
                                        </td>
                                        <td class="text-end">${{ number_format($day['warehouse_profit'], 2) }}</td>
                                        <td class="text-end">${{ number_format($day['retailer_profit'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-group-divider">
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    {{-- A product sold on two days is one product, so the
                                         column is counted over the period rather than summed
                                         down it. --}}
                                    <td>{{ $summary['products_count'] }}</td>
                                    <td class="text-end">${{ number_format($dailyTotals->sum('gross_revenue'), 2) }}</td>
                                    <td class="text-end {{ $summary['total_returns'] > 0 ? 'text-danger' : 'text-muted' }}">
                                        @if($summary['total_returns'] > 0)-@endif${{ number_format($summary['total_returns'], 2) }}
                                    </td>
                                    <td class="text-end">${{ number_format($dailyTotals->sum('total_revenue'), 2) }}</td>
                                    <td class="text-end">${{ number_format($dailyTotals->sum('total_cost'), 2) }}</td>
                                    <td class="text-end">${{ number_format($dailyTotals->sum('total_profit'), 2) }}</td>
                                    <td class="text-end">
                                        @php
                                            $totalRevenue = $dailyTotals->sum('total_revenue');
                                            $totalProfit = $dailyTotals->sum('total_profit');
                                        @endphp
                                        @if($totalRevenue > 0)
                                            {{ number_format(($totalProfit / $totalRevenue) * 100, 1) }}%
                                        @else
                                            0.0%
                                        @endif
                                    </td>
                                    <td class="text-end">${{ number_format($dailyTotals->sum('warehouse_profit'), 2) }}</td>
                                    <td class="text-end">${{ number_format($dailyTotals->sum('retailer_profit'), 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Detailed Daily Product Profit</h5>
                    <small class="text-muted">
                        Revenue net of anything returned that day, against the cost of the goods
                        that shipped. Quantities are not shown: the ledger records value, not units.
                    </small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Product</th>
                                    <th>Location</th>
                                    <th class="text-end">Revenue</th>
                                    <th class="text-end">Cost</th>
                                    <th class="text-end">Profit</th>
                                    <th class="text-end">Margin %</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $currentDate = null;
                                @endphp
                                
                                @foreach($dailyProfits as $item)
                                    @php
                                        $formattedDate = date('M d, Y', strtotime($item['order_date']));
                                        $showDate = $currentDate !== $formattedDate;
                                        $currentDate = $formattedDate;
                                        $margin = $item['revenue'] > 0 ? ($item['profit'] / $item['revenue']) * 100 : 0;
                                    @endphp
                                    
                                    <tr>
                                        <td>{{ $showDate ? $formattedDate : '' }}</td>
                                        <td>{{ $item['product_name'] }}</td>
                                        <td>
                                            <span class="badge bg-{{ $item['location_type'] === 'warehouse' ? 'primary' : 'success' }}">
                                                {{ $item['location_name'] }}
                                            </span>
                                        </td>
                                        <td class="text-end">${{ number_format($item['revenue'], 2) }}</td>
                                        <td class="text-end">${{ number_format($item['cost'], 2) }}</td>
                                        <td class="text-end">${{ number_format($item['profit'], 2) }}</td>
                                        <td class="text-end">{{ number_format($margin, 1) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<style>
    @media print {
        nav, footer, .card-header, form, .btn {
            display: none !important;
        }
        
        .card {
            border: none !important;
        }
        
        .card-body {
            padding: 0 !important;
        }
        
        body {
            padding: 20px !important;
            font-size: 14px !important;
        }
        
        h1 {
            font-size: 18px !important;
            margin-bottom: 20px !important;
        }
        
        .container {
            width: 100% !important;
            max-width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
        }
    }
</style>
</div>
@endsection 
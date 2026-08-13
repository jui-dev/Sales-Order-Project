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
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Note:</strong> This report shows profits from both warehouse and retailer sales:
            <ul class="mb-0 mt-2">
                <li>Warehouse sales: Direct sales from warehouse to customers</li>
                <li>Retailer sales: Sales through retail locations</li>
            </ul>
        </div>
    </div>
</div>

@if($dailyProfits->isEmpty())
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        No completed transactions found for the selected date range.
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
                                <div class="text-muted small">Total Products Sold</div>
                                <div class="h4">{{ number_format($summary['total_products_sold']) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Total Revenue</div>
                                <div class="h4">${{ number_format($summary['total_revenue'], 2) }}</div>
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
                                <div class="text-muted small">Average Margin</div>
                                <div class="h4">{{ number_format($summary['average_margin'], 1) }}%</div>
                            </div>
                        </div>
                    </div>
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
                            <div class="text-muted small">Products Sold</div>
                            <div class="h5">{{ number_format($summary['warehouse_products_sold']) }}</div>
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
                            <div class="text-muted small">Products Sold</div>
                            <div class="h5">{{ number_format($summary['retailer_products_sold']) }}</div>
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
                                    <th>Products Sold</th>
                                    <th class="text-end">Total Revenue</th>
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
                                    <td>{{ $dailyTotals->sum('products_count') }}</td>
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
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Product</th>
                                    <th>Location</th>
                                    <th>Quantity Sold</th>
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
                                        <td>{{ $item['quantity_sold'] }}</td>
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
@extends('layouts.app')
@section('page-header')
<div class="row">
    <div class="col-12 mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-0">Cash Flow Statement</h1>
            @if($startDate || $endDate)
                <p class="text-muted mb-0">
                    @if($startDate) From {{ date('M d, Y', strtotime($startDate)) }} @endif
                    @if($startDate && $endDate) – @endif
                    @if($endDate) To {{ date('M d, Y', strtotime($endDate)) }} @endif
                </p>
            @endif
        </div>
        <div class="btn-group">
            <a href="{{ route('reports.cash-flow', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
            <a href="{{ route('reports.cash-flow', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv me-1"></i> CSV</a>
            <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
        </div>
    </div>
</div>
@endsection

@section('content')


<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Filter</h5>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('reports.cash-flow') }}" class="row g-3">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" value="{{ $startDate }}">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" value="{{ $endDate }}">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-filter me-1"></i> Apply
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@php
    $format = function($amount) {
        return ($amount === 0) ? '' : '$'.number_format($amount, 2);
    };
@endphp

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Cash Flow Summary</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Activity</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-secondary">
                                <td colspan="2" class="fw-bold">Operating Activities</td>
                            </tr>
                            @forelse($operating as $row)
                                <tr>
                                    <td>{{ $row['description'] ?? 'Journal Entry #'.$row['entry']->id }}</td>
                                    <td class="text-end">{{ $format($row['amount']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center py-2">No operating cash flows.</td></tr>
                            @endforelse
                            <tr class="fw-bold">
                                <td class="text-end">Net Cash from Operating Activities</td>
                                <td class="text-end">{{ $format($operatingTotal) }}</td>
                            </tr>

                            <tr class="table-secondary">
                                <td colspan="2" class="fw-bold">Investing Activities</td>
                            </tr>
                            @forelse($investing as $row)
                                <tr>
                                    <td>{{ $row['description'] ?? 'Journal Entry #'.$row['entry']->id }}</td>
                                    <td class="text-end">{{ $format($row['amount']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center py-2">No investing cash flows.</td></tr>
                            @endforelse
                            <tr class="fw-bold">
                                <td class="text-end">Net Cash from Investing Activities</td>
                                <td class="text-end">{{ $format($investingTotal) }}</td>
                            </tr>

                            <tr class="table-secondary">
                                <td colspan="2" class="fw-bold">Financing Activities</td>
                            </tr>
                            @forelse($financing as $row)
                                <tr>
                                    <td>{{ $row['description'] ?? 'Journal Entry #'.$row['entry']->id }}</td>
                                    <td class="text-end">{{ $format($row['amount']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center py-2">No financing cash flows.</td></tr>
                            @endforelse
                            <tr class="fw-bold">
                                <td class="text-end">Net Cash from Financing Activities</td>
                                <td class="text-end">{{ $format($financingTotal) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-group-divider">
                            <tr class="table-primary fw-bold">
                                <td class="text-end">Net Increase / (Decrease) in Cash</td>
                                <td class="text-end">{{ $format($netChange) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
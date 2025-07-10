@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12 mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-0">Income Statement</h1>
            @if($startDate || $endDate)
                <p class="text-muted mb-0">
                    @if($startDate) From {{ date('M d, Y', strtotime($startDate)) }} @endif
                    @if($startDate && $endDate) – @endif
                    @if($endDate) To {{ date('M d, Y', strtotime($endDate)) }} @endif
                </p>
            @endif
        </div>
        <div class="btn-group">
            <a href="{{ route('reports.income-statement', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
            <a href="{{ route('reports.income-statement', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv me-1"></i> CSV</a>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print
            </button>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Filter</h5>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('reports.income-statement') }}" class="row g-3">
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

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Income Statement Summary</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Account Code</th>
                                <th>Account Name</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Revenues -->
                            <tr class="table-secondary">
                                <td colspan="3" class="fw-bold">Revenue</td>
                            </tr>
                            @forelse($revenues as $row)
                                <tr>
                                    <td>{{ $row['account']->code }}</td>
                                    <td>{{ $row['account']->name }}</td>
                                    <td class="text-end">{{ $row['amount'] > 0 ? '$'.number_format($row['amount'], 2) : '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-2">No revenue accounts.</td>
                                </tr>
                            @endforelse
                            <tr class="fw-bold">
                                <td colspan="2" class="text-end">Total Revenue</td>
                                <td class="text-end">${{ number_format($totalRevenue, 2) }}</td>
                            </tr>
                            <!-- Expenses -->
                            <tr class="table-secondary">
                                <td colspan="3" class="fw-bold">Expenses</td>
                            </tr>
                            @forelse($expenses as $row)
                                <tr>
                                    <td>{{ $row['account']->code }}</td>
                                    <td>{{ $row['account']->name }}</td>
                                    <td class="text-end">{{ $row['amount'] > 0 ? '$'.number_format($row['amount'], 2) : '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-2">No expense accounts.</td>
                                </tr>
                            @endforelse
                            <tr class="fw-bold">
                                <td colspan="2" class="text-end">Total Expenses</td>
                                <td class="text-end">${{ number_format($totalExpense, 2) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-group-divider">
                            <tr class="table-primary fw-bold">
                                <td colspan="2" class="text-end">Net Income</td>
                                <td class="text-end">${{ number_format($netIncome, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
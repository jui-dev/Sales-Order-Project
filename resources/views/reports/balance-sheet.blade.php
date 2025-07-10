@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12 mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-0">Balance Sheet</h1>
            @if($endDate)
                <p class="text-muted mb-0">As at {{ date('M d, Y', strtotime($endDate)) }}</p>
            @endif
        </div>
        <div class="btn-group">
            <a href="{{ route('reports.balance-sheet', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
            <a href="{{ route('reports.balance-sheet', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv me-1"></i> CSV</a>
            <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
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
                <form method="get" action="{{ route('reports.balance-sheet') }}" class="row g-3">
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">Up To Date</label>
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
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Assets</h5>
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
                            @forelse($assets as $row)
                                <tr>
                                    <td>{{ $row['account']->code }}</td>
                                    <td>{{ $row['account']->name }}</td>
                                    <td class="text-end">${{ number_format($row['balance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-2">No asset accounts.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-group-divider">
                            <tr class="fw-bold">
                                <td colspan="2" class="text-end">Total Assets</td>
                                <td class="text-end">${{ number_format($totalAssets, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Liabilities</h5>
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
                            @forelse($liabilities as $row)
                                <tr>
                                    <td>{{ $row['account']->code }}</td>
                                    <td>{{ $row['account']->name }}</td>
                                    <td class="text-end">${{ number_format($row['balance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-2">No liability accounts.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-group-divider">
                            <tr class="fw-bold">
                                <td colspan="2" class="text-end">Total Liabilities</td>
                                <td class="text-end">${{ number_format($totalLiabilities, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Equity</h5>
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
                            @forelse($equity as $row)
                                <tr>
                                    <td>{{ $row['account']->code }}</td>
                                    <td>{{ $row['account']->name }}</td>
                                    <td class="text-end">${{ number_format($row['balance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-2">No equity accounts.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-group-divider">
                            <tr class="fw-bold">
                                <td colspan="2" class="text-end">Total Equity</td>
                                <td class="text-end">${{ number_format($totalEquity, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Totals Check</h5>
                <p>Total Assets: <strong>${{ number_format($totalAssets, 2) }}</strong></p>
                <p>Total Liabilities + Equity: <strong>${{ number_format($liabEqTotal, 2) }}</strong></p>
                @if(abs($totalAssets - $liabEqTotal) < 0.01)
                    <p class="text-success mb-0"><i class="bi bi-check-circle me-1"></i> Balance Sheet balances.</p>
                @else
                    <p class="text-danger mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Not balanced – please review.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection 
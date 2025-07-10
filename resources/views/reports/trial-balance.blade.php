@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12 mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-0">Trial Balance</h1>
            @if($endDate)
                <p class="text-muted mb-0">As at {{ date('M d, Y', strtotime($endDate)) }}</p>
            @endif
        </div>
        <div class="btn-group">
            <a href="{{ route('reports.trial-balance', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
            <a href="{{ route('reports.trial-balance', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv me-1"></i> CSV</a>
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
                <form method="get" action="{{ route('reports.trial-balance') }}" class="row g-3">
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
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Trial Balance Summary</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Account Code</th>
                                <th>Account Name</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($balances as $row)
                                <tr>
                                    <td>{{ $row['account']->code }}</td>
                                    <td>{{ $row['account']->name }}</td>
                                    <td class="text-end">{{ $row['debit'] > 0 ? '$'.number_format($row['debit'], 2) : '' }}</td>
                                    <td class="text-end">{{ $row['credit'] > 0 ? '$'.number_format($row['credit'], 2) : '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-3">No journal entries found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-group-divider">
                            <tr class="fw-bold">
                                <td colspan="2" class="text-end">Totals</td>
                                <td class="text-end">${{ number_format($totalDebit, 2) }}</td>
                                <td class="text-end">${{ number_format($totalCredit, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
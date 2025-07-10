<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Income Statement</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; }
        th { background-color: #f2f2f2; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
    </style>
</head>
<body>
    <h2 style="margin-bottom: 5px;">Income Statement</h2>
    @if($startDate || $endDate)
        <p style="margin-top: 0;">
            @if($startDate) From {{ date('M d, Y', strtotime($startDate)) }} @endif
            @if($startDate && $endDate) – @endif
            @if($endDate) To {{ date('M d, Y', strtotime($endDate)) }} @endif
        </p>
    @endif

    <table style="margin-top: 10px;">
        <thead>
            <tr>
                <th>Account Code</th>
                <th>Account Name</th>
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="3" class="fw-bold">Revenue</td></tr>
            @foreach($revenues as $row)
                <tr>
                    <td>{{ $row['account']->code }}</td>
                    <td>{{ $row['account']->name }}</td>
                    <td class="text-end">{{ number_format($row['amount'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="fw-bold"><td colspan="2" class="text-end">Total Revenue</td><td class="text-end">{{ number_format($totalRevenue, 2) }}</td></tr>
            <tr><td colspan="3" class="fw-bold">Expenses</td></tr>
            @foreach($expenses as $row)
                <tr>
                    <td>{{ $row['account']->code }}</td>
                    <td>{{ $row['account']->name }}</td>
                    <td class="text-end">{{ number_format($row['amount'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="fw-bold"><td colspan="2" class="text-end">Total Expenses</td><td class="text-end">{{ number_format($totalExpense, 2) }}</td></tr>
        </tbody>
        <tfoot>
            <tr class="fw-bold"><td colspan="2" class="text-end">Net Income</td><td class="text-end">{{ number_format($netIncome, 2) }}</td></tr>
        </tfoot>
    </table>
</body>
</html> 
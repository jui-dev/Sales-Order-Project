<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Balance Sheet</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #ddd; padding: 6px; }
        th { background-color: #f2f2f2; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
    </style>
</head>
<body>
    <h2 style="margin-bottom: 5px;">Balance Sheet</h2>
    @if($endDate)
        <p style="margin-top: 0;">As at {{ date('M d, Y', strtotime($endDate)) }}</p>
    @endif

    @php
        $renderSection = function($title, $rows) {
            echo '<h4 style="margin-bottom:4px;">'.$title.'</h4>';
            echo '<table><thead><tr><th>Account Code</th><th>Account Name</th><th class="text-end">Amount</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                echo '<tr><td>'.$row['account']->code.'</td><td>'.$row['account']->name.'</td><td class="text-end">'.number_format($row['balance'], 2).'</td></tr>';
            }
            if ($rows->isEmpty()) {
                echo '<tr><td colspan="3" style="text-align:center;padding:6px;">No accounts.</td></tr>';
            }
            echo '</tbody></table>';
        };
    @endphp

    {!! $renderSection('Assets', $assets) !!}
    {!! $renderSection('Liabilities', $liabilities) !!}
    {!! $renderSection('Equity', $equity) !!}

    <table>
        <tr class="fw-bold"><td style="text-align:right;" colspan="2">Total Assets</td><td class="text-end">{{ number_format($totalAssets, 2) }}</td></tr>
        <tr class="fw-bold"><td style="text-align:right;" colspan="2">Total Liabilities</td><td class="text-end">{{ number_format($totalLiabilities, 2) }}</td></tr>
        <tr class="fw-bold"><td style="text-align:right;" colspan="2">Total Equity</td><td class="text-end">{{ number_format($totalEquity, 2) }}</td></tr>
        <tr class="fw-bold"><td style="text-align:right;" colspan="2">Liabilities + Equity</td><td class="text-end">{{ number_format($liabEqTotal, 2) }}</td></tr>
    </table>

    @if(abs($totalAssets - $liabEqTotal) < 0.01)
        <p><strong>Balance Sheet balances.</strong></p>
    @else
        <p><strong style="color:red;">Not balanced – please review.</strong></p>
    @endif
</body>
</html> 
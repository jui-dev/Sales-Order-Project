<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cash Flow Statement</title>
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
    <h2 style="margin-bottom: 5px;">Cash Flow Statement</h2>
    @if($startDate || $endDate)
        <p style="margin-top: 0;">
            @if($startDate) From {{ date('M d, Y', strtotime($startDate)) }} @endif
            @if($startDate && $endDate) – @endif
            @if($endDate) To {{ date('M d, Y', strtotime($endDate)) }} @endif
        </p>
    @endif

    @php
        $renderSection = function($title, $rows, $total) {
            echo '<h4 style="margin-bottom:4px;">'.$title.'</h4>';
            echo '<table><thead><tr><th>Description</th><th class="text-end">Amount</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $desc = $row['description'] ?? 'Entry #'.$row['entry']->id;
                echo '<tr><td>'.$desc.'</td><td class="text-end">'.number_format($row['amount'], 2).'</td></tr>';
            }
            if ($rows->isEmpty()) {
                echo '<tr><td colspan="2" style="text-align:center;padding:6px;">No cash flows.</td></tr>';
            }
            echo '<tr class="fw-bold"><td class="text-end">Net Cash from '.$title.'</td><td class="text-end">'.number_format($total, 2).'</td></tr>';
            echo '</tbody></table>';
        };
    @endphp

    {!! $renderSection('Operating Activities', $operating, $operatingTotal) !!}
    {!! $renderSection('Investing Activities', $investing, $investingTotal) !!}
    {!! $renderSection('Financing Activities', $financing, $financingTotal) !!}

    <table>
        <tr class="fw-bold"><td class="text-end">Net Increase / (Decrease) in Cash</td><td class="text-end">{{ number_format($netChange, 2) }}</td></tr>
    </table>
</body>
</html> 
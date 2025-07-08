<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f2f2f2; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <h2>Invoice {{ $invoice->invoice_number }}</h2>
    <p><strong>Date:</strong> {{ optional($invoice->invoice_date)->format('M d, Y') }}</p>

    <table style="margin-bottom: 20px;">
        <tr>
            <td style="width:50%;">
                <strong>Bill To:</strong><br>
                {{ optional($invoice->customer)->name }}<br>
                {{ optional($invoice->customer)->email }}<br>
            </td>
            <td style="width:50%;">
                <strong>Order Ref:</strong> #{{ $invoice->order->id }}<br>
                <strong>Payment Status:</strong> {{ ucfirst($invoice->payment_status) }}
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Unit Price</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="text-center">{{ $item->quantity }}</td>
                <td class="text-end">${{ number_format($item->unit_price, 2) }}</td>
                <td class="text-end">${{ number_format($item->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3" class="text-end">Subtotal</th>
                <th class="text-end">${{ number_format($invoice->subtotal, 2) }}</th>
            </tr>
            <tr>
                <th colspan="3" class="text-end">Tax</th>
                <th class="text-end">${{ number_format($invoice->tax, 2) }}</th>
            </tr>
            @if($invoice->discount > 0)
            <tr>
                <th colspan="3" class="text-end">Discount</th>
                <th class="text-end">-${{ number_format($invoice->discount, 2) }}</th>
            </tr>
            @endif
            <tr>
                <th colspan="3" class="text-end"><strong>Grand Total</strong></th>
                <th class="text-end"><strong>${{ number_format($invoice->total, 2) }}</strong></th>
            </tr>
        </tfoot>
    </table>
</body>
</html> 
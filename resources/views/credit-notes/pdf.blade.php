<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Note #{{ $creditNote->credit_note_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .document-title {
            font-size: 18px;
            color: #666;
        }
        .credit-note-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .info-section {
            flex: 1;
        }
        .info-section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .info-row {
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .items-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        .amount {
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">Sales Order System</div>
        <div class="document-title">CREDIT NOTE</div>
    </div>

    <div class="credit-note-info">
        <div class="info-section">
            <h3>Credit Note Details</h3>
            <div class="info-row">
                <span class="info-label">Credit Note #:</span>
                <span>{{ $creditNote->credit_note_number }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Issue Date:</span>
                <span>{{ $creditNote->issue_date ? $creditNote->issue_date->format('M d, Y') : 'Not issued' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span>{{ ucfirst($creditNote->status) }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Total Amount:</span>
                <span class="amount">${{ number_format($creditNote->total_amount, 2) }}</span>
            </div>
        </div>

        <div class="info-section">
            <h3>Customer Information</h3>
            <div class="info-row">
                <span class="info-label">Name:</span>
                <span>{{ $creditNote->customer->name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span>{{ $creditNote->customer->email }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Phone:</span>
                <span>{{ $creditNote->customer->phone ?? 'Not provided' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Address:</span>
                <span>{{ $creditNote->customer->address ?? 'Not provided' }}</span>
            </div>
        </div>

        <div class="info-section">
            <h3>Related Information</h3>
            <div class="info-row">
                <span class="info-label">Invoice #:</span>
                <span>{{ $creditNote->invoice->invoice_number }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Return #:</span>
                <span>{{ $creditNote->returnTransaction->formatted_id }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Invoice Date:</span>
                <span>{{ $creditNote->invoice->invoice_date->format('M d, Y') }}</span>
            </div>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($creditNote->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->product->sku ?? '-' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>${{ number_format($item->unit_price, 2) }}</td>
                    <td class="amount">${{ number_format($item->total_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4" style="text-align: right;"><strong>Total Credit Amount:</strong></td>
                <td class="amount"><strong>${{ number_format($creditNote->total_amount, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    @if($creditNote->notes)
        <div style="margin-bottom: 30px;">
            <h3>Notes</h3>
            <p>{{ $creditNote->notes }}</p>
        </div>
    @endif

    <div class="footer">
        <p>This credit note was automatically generated for return transaction #{{ $creditNote->returnTransaction->formatted_id }}</p>
        <p>Generated on {{ $creditNote->created_at->format('M d, Y H:i:s') }}</p>
    </div>
</body>
</html> 
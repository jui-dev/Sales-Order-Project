<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debit Note #{{ $debitNote->debit_note_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .company-info {
            text-align: center;
            margin-bottom: 30px;
        }
        .debit-note-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .vendor-info {
            margin-bottom: 30px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .items-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .total-row {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .amount {
            text-align: right;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-issued {
            background-color: #28a745;
            color: white;
        }
        .status-pending {
            background-color: #ffc107;
            color: black;
        }
        .status-cancelled {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>DEBIT NOTE</h1>
    </div>

    <div class="company-info">
        <h2>Your Company Name</h2>
        <p>123 Business Street<br>
        City, State 12345<br>
        Phone: (555) 123-4567<br>
        Email: info@yourcompany.com</p>
    </div>

    <div class="debit-note-info">
        <div>
            <strong>Debit Note Number:</strong> {{ $debitNote->debit_note_number }}<br>
            <strong>Issue Date:</strong> {{ $debitNote->issue_date ? $debitNote->issue_date->format('M d, Y') : 'N/A' }}<br>
            <strong>Status:</strong> 
            <span class="status-badge status-{{ $debitNote->status }}">
                {{ ucfirst($debitNote->status) }}
            </span>
        </div>
        <div>
            <strong>Vendor:</strong><br>
            {{ $debitNote->vendor->name }}<br>
            {{ $debitNote->vendor->email }}<br>
            {{ $debitNote->vendor->phone ?? '' }}<br>
            {{ $debitNote->vendor->address ?? '' }}
        </div>
    </div>

    <div class="vendor-info">
        <h3>Vendor Information</h3>
        <p>
            <strong>Name:</strong> {{ $debitNote->vendor->name }}<br>
            <strong>Email:</strong> {{ $debitNote->vendor->email }}<br>
            <strong>Phone:</strong> {{ $debitNote->vendor->phone ?? 'N/A' }}<br>
            <strong>Address:</strong> {{ $debitNote->vendor->address ?? 'N/A' }}
        </p>
    </div>

    <h3>Items</h3>
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
            @if($debitNote->items && $debitNote->items->count() > 0)
                @foreach($debitNote->items as $item)
                    <tr>
                        <td>{{ $item->product_name ?? $item->product->name }}</td>
                        <td>{{ $item->sku ?? '-' }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>${{ number_format($item->unit_price, 2) }}</td>
                        <td class="amount">${{ number_format($item->total_amount, 2) }}</td>
                    </tr>
                @endforeach
            @elseif($debitNote->metadata && isset($debitNote->metadata['product_name']))
                <tr>
                    <td>{{ $debitNote->metadata['product_name'] }}</td>
                    <td>{{ $debitNote->metadata['product_sku'] ?? '-' }}</td>
                    <td>{{ $debitNote->metadata['quantity_returned'] ?? 0 }}</td>
                    <td>${{ number_format($debitNote->metadata['original_unit_price'] ?? 0, 2) }}</td>
                    <td class="amount">${{ number_format($debitNote->total_amount, 2) }}</td>
                </tr>
            @else
                <tr>
                    <td colspan="5" style="text-align: center;">No items found</td>
                </tr>
            @endif
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4" style="text-align: right;"><strong>Total Debit Amount:</strong></td>
                <td class="amount"><strong>${{ number_format($debitNote->total_amount, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    @if($debitNote->notes)
        <div style="margin-bottom: 30px;">
            <h3>Notes</h3>
            <p>{{ $debitNote->notes }}</p>
        </div>
    @endif

    <div class="footer">
        <p>This debit note was automatically generated for return transaction #{{ $debitNote->returnTransaction->formatted_id }}</p>
        <p>Generated on {{ $debitNote->created_at->format('M d, Y H:i:s') }}</p>
    </div>
</body>
</html> 
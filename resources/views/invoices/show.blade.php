@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Invoice {{ $invoice->invoice_number }}</h1>
    <div>
        <a href="{{ route('invoices.download', $invoice) }}" class="btn btn-success">Download PDF</a>
        <a href="{{ route('invoices.index') }}" class="btn btn-secondary">Back</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h5>Bill To:</h5>
                <p>
                    {{ optional($invoice->customer)->name }}<br>
                    {{ optional($invoice->customer)->email }}<br>
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <h5>Invoice Details</h5>
                <p>
                    <strong>Invoice No:</strong> {{ $invoice->invoice_number }}<br>
                    <strong>Date:</strong> {{ optional($invoice->invoice_date)->format('M d, Y') }}<br>
                    <strong>Order Ref:</strong> <a href="{{ route('orders.show', $invoice->order) }}">#{{ $invoice->order->id }}</a>
                </p>
            </div>
        </div>

        <div class="table-responsive mt-4">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-center">Quantity</th>
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
                        <th colspan="3" class="text-end">Grand Total</th>
                        <th class="text-end">${{ number_format($invoice->total, 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="mt-3"><strong>Payment Status:</strong> {{ ucfirst($invoice->payment_status) }}</p>
    </div>
</div>
@endsection 
@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Invoice {{ $invoice->invoice_number }}</h1>
    <div>
        <a href="{{ route('invoices.download', $invoice) }}" class="btn btn-success">Download PDF</a>
        <a href="{{ route('invoices.index') }}" class="btn btn-secondary">Back</a>
    </div>
</div>
@endsection

@section('content')


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

<!-- Payment Form -->
@if($invoice->payment_status === 'unpaid')
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-credit-card me-2"></i>Record Payment
        </h5>
    </div>
    <div class="card-body">
        <form action="{{ route('invoices.pay', $invoice) }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="amount" class="form-label">Payment Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="amount" name="amount" 
                               value="{{ $invoice->total }}" required>
                        <div class="form-text">Full invoice amount: ${{ number_format($invoice->total, 2) }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-select" id="method" name="method" required>
                            <option value="cash">Cash</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="check">Check</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="reference" class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="reference" name="reference" 
                               placeholder="Transaction ID, Check #, etc.">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="mb-3">
                        <label for="notes" class="form-label">Payment Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" 
                                  placeholder="Additional payment details..."></textarea>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i>Record Payment
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- Payment History -->
<div class="card">
    <div class="card-header">
        Payment History
    </div>
    <div class="card-body table-responsive">
        @if($invoice->payments->isEmpty())
            <p class="text-muted mb-0">No payments recorded.</p>
        @else
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Paid At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->payments as $payment)
                    <tr>
                        <td>{{ $payment->id }}</td>
                        <td>${{ number_format($payment->amount, 2) }}</td>
                        <td>{{ ucfirst(str_replace('_',' ', $payment->method)) }}</td>
                        <td>{{ optional($payment->payment_date)->format('M d, Y h:i A') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection 
@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Invoices</h1>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>#</th>
                <th>Invoice No.</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Total</th>
                <th>Status</th>
                <th class="text-end">Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->id }}</td>
                    <td>{{ $invoice->invoice_number }}</td>
                    <td>{{ optional($invoice->invoice_date)->format('M d, Y') }}</td>
                    <td>{{ optional($invoice->customer)->name }}</td>
                    <td>${{ number_format($invoice->total, 2) }}</td>
                    <td><span class="badge bg-secondary">{{ ucfirst($invoice->payment_status) }}</span></td>
                    <td class="text-end">
                        <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-primary">View</a>
                        <a href="{{ route('invoices.download', $invoice) }}" class="btn btn-sm btn-outline-secondary">PDF</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center">No invoices found</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $invoices->links() }}
    </div>
</div>
@endsection 
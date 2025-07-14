@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Invoices</h1>
    <form method="GET" class="d-flex gap-2">
        <select name="status" class="form-select form-select-sm" style="width:auto;">
            <option value="">All Statuses</option>
            @foreach(['unpaid','partially_paid','paid'] as $status)
                <option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst(str_replace('_',' ', $status)) }}</option>
            @endforeach
        </select>
        <select name="customer_id" class="form-select form-select-sm" style="width:auto;">
            <option value="">All Customers</option>
            @foreach($customers as $customer)
                <option value="{{ $customer->id }}" @selected(request('customer_id')==$customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" />
        <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" />
        <button class="btn btn-sm btn-outline-secondary" type="submit">Filter</button>
    </form>
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
                        @if($invoice->payment_status === 'unpaid')
                            <div class="dropdown d-inline">
                                <button class="btn btn-sm btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Record Payment
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <form action="{{ route('invoices.pay', $invoice) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="amount" value="{{ $invoice->total }}">
                                            <input type="hidden" name="method" value="cash">
                                            <button type="submit" class="dropdown-item">Mark as Paid</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        @endif
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
@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Supplier Bill Payment {{ $supplierBillPayment->formatted_id }}</h1>
        <div>
            <a href="{{ route('supplier-bill-payments.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
            <a href="{{ route('supplier-bills.payment-info', $supplierBillPayment->supplierBill) }}" class="btn btn-primary">
                <i class="bi bi-credit-card me-1"></i> Payment Information
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    

    <!-- Payment Status Alert -->
    @if($supplierBillPayment->payment_status === 'unpaid')
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Payment Pending</strong> 
            This supplier bill payment is currently unpaid.
        </div>
    @elseif($supplierBillPayment->payment_status === 'paid')
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            <strong>Payment Completed</strong> 
            This supplier bill payment has been marked as paid.
        </div>
    @endif

    <!-- Payment Information Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong><i class="bi bi-truck me-1"></i> Vendor Information</strong>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ $supplierBillPayment->vendor->name }}</strong></p>
                    <small class="text-muted">{{ $supplierBillPayment->vendor->address }}</small>
                    @if($supplierBillPayment->vendor->contact_person)
                        <br><small class="text-muted">Contact: {{ $supplierBillPayment->vendor->contact_person }}</small>
                    @endif
                    @if($supplierBillPayment->vendor->phone)
                        <br><small class="text-muted">Phone: {{ $supplierBillPayment->vendor->phone }}</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong><i class="bi bi-credit-card me-1"></i> Payment Details</strong>
                </div>
                <div class="card-body">
                    @php
                        $paymentBadge = $supplierBillPayment->payment_status === 'paid' ? 'success' : 'warning';
                    @endphp
                    <p class="mb-1">Payment Status: <span class="badge bg-{{ $paymentBadge }}">{{ ucfirst($supplierBillPayment->payment_status) }}</span></p>
                    <p class="mb-1">Payment Amount: <strong>${{ number_format($supplierBillPayment->payment_amount, 2) }}</strong></p>
                    <p class="mb-1">Created: {{ $supplierBillPayment->created_at->format('M d, Y H:i') }}</p>
                    @if($supplierBillPayment->paid_at)
                        <p class="mb-1">Paid At: {{ $supplierBillPayment->paid_at->format('M d, Y H:i') }}</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong><i class="bi bi-receipt me-1"></i> Supplier Bill Reference</strong>
                </div>
                <div class="card-body">
                    <p class="mb-1">Supplier Bill: <a href="{{ route('supplier-bills.show', $supplierBillPayment->supplier_bill_id) }}">{{ $supplierBillPayment->supplierBill->formatted_id }}</a></p>
                    <p class="mb-1">Bill Amount: <strong>${{ number_format($supplierBillPayment->supplierBill->total_amount, 2) }}</strong></p>
                    <p class="mb-1">Bill Status: <span class="badge bg-success">{{ ucfirst($supplierBillPayment->supplierBill->status) }}</span></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Journal Entry Information -->
    @if($supplierBillPayment->paymentJournal)
        <div class="card">
            <div class="card-header">
                <strong><i class="bi bi-journal-text me-1"></i> Payment Journal Entry</strong>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-2">Journal Entry ID: <strong>{{ $supplierBillPayment->paymentJournal->formatted_id }}</strong></p>
                        <p class="mb-2">Status: <span class="badge bg-{{ $supplierBillPayment->paymentJournal->status === 'draft' ? 'secondary' : ($supplierBillPayment->paymentJournal->status === 'posted' ? 'success' : 'primary') }}">{{ ucfirst($supplierBillPayment->paymentJournal->status) }}</span></p>
                        <p class="mb-2">Description: {{ $supplierBillPayment->paymentJournal->description }}</p>
                        <a href="{{ route('journal-entries.index', ['reference' => $supplierBillPayment->paymentJournal->formatted_id]) }}" class="btn btn-sm btn-outline-secondary">View Journal Entry</a>
                    </div>
                    <div class="col-md-6">
                        <h6>Journal Entry Lines:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Account</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Credit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($supplierBillPayment->paymentJournal->lines as $line)
                                        <tr>
                                            <td>{{ $line->account->code }} – {{ $line->account->name }}</td>
                                            <td class="text-end">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                                            <td class="text-end">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection 
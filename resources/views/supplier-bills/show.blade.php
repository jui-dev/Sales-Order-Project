@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Supplier Bill {{ $supplierBill->formatted_id }}</h1>
        <div>
            <a href="{{ route('supplier-bills.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
            @if($supplierBill->status === 'draft')
                <form action="{{ route('supplier-bills.post', $supplierBill) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Post Supplier Bill
                    </button>
                </form>
            @elseif($supplierBill->status === 'posted')
                <a href="{{ route('supplier-bills.payment-info', $supplierBill) }}" class="btn btn-success">
                    <i class="bi bi-credit-card me-1"></i> Payment Information
                </a>
                @if($supplierBill->payment && $supplierBill->payment->payment_status === 'unpaid')
                    <form action="{{ route('supplier-bills.pay', $supplierBill) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-cash-coin me-1"></i> Mark as Paid
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    <!-- Bill Status Alert -->
    @if($supplierBill->status === 'draft')
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Draft Supplier Bill</strong> 
            This supplier bill is in draft status. Click "Post Supplier Bill" to create the purchase journal entry and proceed to payment.
        </div>
    @elseif($supplierBill->status === 'posted')
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            <strong>Supplier Bill Posted</strong> 
            This supplier bill has been posted and the purchase journal entry has been created with Draft status. You can now proceed to payment.
        </div>
    @endif

    @if($supplierBill->payment && $supplierBill->payment->payment_status === 'paid')
        <div class="alert alert-primary">
            <i class="bi bi-credit-card me-2"></i>
            <strong>Supplier Bill Paid</strong> 
            This supplier bill has been marked as paid and the payment journal entry has been created with Draft status.
        </div>
    @endif

    <!-- Bill Information Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong><i class="bi bi-truck me-1"></i> Vendor Information</strong>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ $supplierBill->vendor->name }}</strong></p>
                    <small class="text-muted">{{ $supplierBill->vendor->address }}</small>
                    @if($supplierBill->vendor->contact_person)
                        <br><small class="text-muted">Contact: {{ $supplierBill->vendor->contact_person }}</small>
                    @endif
                    @if($supplierBill->vendor->phone)
                        <br><small class="text-muted">Phone: {{ $supplierBill->vendor->phone }}</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong><i class="bi bi-receipt me-1"></i> Bill Details</strong>
                </div>
                <div class="card-body">
                    @php
                        $badge = [
                            'draft'  => 'secondary',
                            'posted' => 'success',
                        ][$supplierBill->status] ?? 'secondary';
                    @endphp
                    <p class="mb-1">Status: <span class="badge bg-{{ $badge }}">{{ ucfirst($supplierBill->status) }}</span></p>
                    @if($supplierBill->payment)
                        <p class="mb-1">Payment: 
                            @php
                                $paymentBadge = $supplierBill->payment->payment_status === 'paid' ? 'success' : 'warning';
                            @endphp
                            <span class="badge bg-{{ $paymentBadge }}">{{ ucfirst($supplierBill->payment->payment_status) }}</span>
                        </p>
                    @endif
                    <p class="mb-1">Bill Date: {{ optional($supplierBill->bill_date)->format('M d, Y') }}</p>
                    <p class="mb-1">GRN Reference: <a href="{{ route('grns.show', $supplierBill->grn_id) }}">#{{ $supplierBill->grn_id }}</a></p>
                    @if($supplierBill->posted_at)
                        <p class="mb-1">Posted At: {{ $supplierBill->posted_at->format('M d, Y H:i') }}</p>
                    @endif
                    @if($supplierBill->payment && $supplierBill->payment->paid_at)
                        <p class="mb-1">Paid At: {{ $supplierBill->payment->paid_at->format('M d, Y H:i') }}</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong><i class="bi bi-currency-dollar me-1"></i> Financial Summary</strong>
                </div>
                <div class="card-body">
                    <p class="mb-1">Total Amount: <strong>${{ number_format($supplierBill->total_amount, 2) }}</strong></p>
                    <p class="mb-1">Total Items: {{ $supplierBill->items->sum('quantity') }}</p>
                    @if($supplierBill->description)
                        <p class="mb-1">Description: {{ $supplierBill->description }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Bill Items -->
    <div class="card">
        <div class="card-header">
            <strong><i class="bi bi-box me-1"></i> Bill Items</strong>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-center">Unit Cost</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($supplierBill->items as $index => $item)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <strong>{{ $item->product->name }}</strong>
                                    @if($item->product->description)
                                        <br><small class="text-muted">{{ $item->product->description }}</small>
                                    @endif
                                </td>
                                <td>{{ $item->product->sku ?? 'N/A' }}</td>
                                <td class="text-center">
                                    <span class="badge bg-primary">{{ number_format($item->quantity) }}</span>
                                </td>
                                <td class="text-center">${{ number_format($item->unit_cost, 2) }}</td>
                                <td class="text-end">${{ number_format($item->subtotal, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">No items found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="table-active">
                            <td colspan="5" class="text-end"><strong>Total Amount:</strong></td>
                            <td class="text-end"><strong>${{ number_format($supplierBill->total_amount, 2) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>


</div>
@endsection 
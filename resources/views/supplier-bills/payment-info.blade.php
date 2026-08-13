@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Payment Information - {{ $supplierBill->formatted_id }}</h1>
        <div>
            <a href="{{ route('supplier-bills.show', $supplierBill) }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Bill
            </a>
            @if($supplierBill->status === 'posted' && $supplierBill->payment && $supplierBill->payment->payment_status === 'unpaid')
                <form action="{{ route('supplier-bills.pay', $supplierBill) }}" method="POST" class="d-inline" id="markAsPaidForm">
                    @csrf
                    <button type="submit" class="btn btn-success" id="markAsPaidBtn">
                        <i class="bi bi-credit-card me-1"></i> Mark as Paid
                    </button>
                </form>
            @elseif($supplierBill->payment && $supplierBill->payment->payment_status === 'paid')
                <a href="{{ route('supplier-bills.show', $supplierBill) }}" class="btn btn-primary">
                    <i class="bi bi-receipt me-1"></i> View Bill Details
                </a>
            @endif
        </div>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    

    <!-- Payment Status Alert -->
    @if($supplierBill->status === 'posted' && $supplierBill->payment && $supplierBill->payment->payment_status === 'unpaid')
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Payment Pending</strong> 
            This supplier bill has been posted but payment is still pending. Click "Mark as Paid" to complete the payment process.
        </div>
    @elseif($supplierBill->payment && $supplierBill->payment->payment_status === 'paid')
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            <strong>Payment Completed</strong> 
            This supplier bill has been marked as paid and the payment journal entry has been created with Draft status.
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
                    <strong><i class="bi bi-credit-card me-1"></i> Payment Status</strong>
                </div>
                <div class="card-body">
                    @php
                        $paymentStatus = $supplierBill->payment && $supplierBill->payment->payment_status === 'paid' ? 'Paid' : 'Unpaid';
                        $paymentBadge = $supplierBill->payment && $supplierBill->payment->payment_status === 'paid' ? 'success' : 'warning';
                    @endphp
                    <p class="mb-1">Payment Status: <span class="badge bg-{{ $paymentBadge }}">{{ $paymentStatus }}</span></p>
                    <p class="mb-1">Bill Date: {{ optional($supplierBill->bill_date)->format('M d, Y') }}</p>
                    <p class="mb-1">Posted At: {{ optional($supplierBill->posted_at)->format('M d, Y H:i') }}</p>
                    @if($supplierBill->payment && $supplierBill->payment->paid_at)
                        <p class="mb-1">Paid At: {{ $supplierBill->payment->paid_at->format('M d, Y H:i') }}</p>
                    @endif
                    <p class="mb-1">GRN Reference: <a href="{{ route('grns.show', $supplierBill->grn_id) }}">#{{ $supplierBill->grn_id }}</a></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong><i class="bi bi-currency-dollar me-1"></i> Payment Amount</strong>
                </div>
                <div class="card-body">
                    <p class="mb-1">Total Amount: <strong>${{ number_format($supplierBill->total_amount, 2) }}</strong></p>
                    <p class="mb-1">Total Items: {{ $supplierBill->items ? $supplierBill->items->sum('quantity') : 0 }}</p>
                    @if($supplierBill->description)
                        <p class="mb-1">Description: {{ $supplierBill->description }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Details -->
    <div class="card">
        <div class="card-header">
            <strong><i class="bi bi-receipt me-1"></i> Payment Details</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Purchase Information</h6>
                    <p class="mb-2">This payment is for goods received from <strong>{{ $supplierBill->vendor->name }}</strong>.</p>
                    <p class="mb-2">The purchase journal entry has been created with Draft status to record the inventory increase and accounts payable liability.</p>
                    @if($supplierBill->purchaseJournal)
                        <p class="mb-2">Purchase Journal Entry: <strong>{{ $supplierBill->purchaseJournal->formatted_id }}</strong></p>
                    @endif
                </div>
                <div class="col-md-6">
                    <h6>Payment Information</h6>
                    @if($supplierBill->status === 'posted' && $supplierBill->payment && $supplierBill->payment->payment_status === 'unpaid')
                        <p class="mb-2">Payment is currently <span class="badge bg-warning">Unpaid</span>.</p>
                        <p class="mb-2">Click "Mark as Paid" to create the payment journal entry with Draft status and complete the payment process.</p>
                        <p class="mb-2">This will debit Accounts Payable and credit Cash.</p>
                    @elseif($supplierBill->payment && $supplierBill->payment->payment_status === 'paid')
                        <p class="mb-2">Payment has been <span class="badge bg-success">Completed</span>.</p>
                        <p class="mb-2">The payment journal entry has been created with Draft status to record the cash payment.</p>
                        @if($supplierBill->payment && $supplierBill->payment->payment_journal_id)
                            <p class="mb-2">Payment Journal Entry: <strong>{{ $supplierBill->payment->paymentJournal->formatted_id ?? 'N/A' }}</strong></p>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Items Table -->
    <div class="card mt-4">
        <div class="card-header">
            <strong><i class="bi bi-list-ul me-1"></i> Payment Items</strong>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($supplierBill->items as $item)
                            <tr>
                                <td>{{ $item->product->name ?? 'N/A' }}</td>
                                <td>{{ $item->description ?? 'No description' }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>${{ number_format($item->unit_price, 2) }}</td>
                                <td>${{ number_format($item->subtotal, 2) }}</td>
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
                            <td colspan="5" class="text-end"><strong>Total Payment Amount:</strong></td>
                            <td class="text-end"><strong>${{ number_format($supplierBill->total_amount, 2) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>


</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let isProcessing = false;
    
    // Handle Mark as Paid button - prevent double submission only
    const markAsPaidForm = document.getElementById('markAsPaidForm');
    const markAsPaidBtn = document.getElementById('markAsPaidBtn');
    
    if (markAsPaidForm && markAsPaidBtn) {
        markAsPaidForm.addEventListener('submit', function(e) {
            // Only prevent double submission, no visual feedback
            if (isProcessing) {
                e.preventDefault();
                return false;
            }
            
            // Set processing flag to prevent double clicks
            isProcessing = true;
            
            // Disable the button to prevent multiple clicks
            markAsPaidBtn.disabled = true;
            
            // Allow form to submit normally without any processing overlay
        });
    }
});
</script>
@endpush
@endsection 
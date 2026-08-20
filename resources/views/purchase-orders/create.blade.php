@extends('layouts.app')

@section('page-header')
<div class="mb-4">
    <h1 class="h2 mb-1">New Purchase Order</h1>
    <p class="text-muted mb-0">Ask a vendor to send goods to one of your warehouses.</p>
</div>
@endsection

@section('content')
<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    Start by choosing a vendor. That decides which products you can order and what they cost.
    A purchase order records what you have <strong>asked for</strong>: nothing enters inventory and no price
    changes until the goods arrive and the GRN is posted.
</div>

<form action="{{ route('purchase-orders.store') }}" method="POST">
    @csrf

    @include('purchase-orders._form', ['order' => null])

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">Cancel</a>
        {{-- Enabled by the form script once a vendor with orderable products is chosen --}}
        <button type="submit" class="btn btn-primary" data-order-submit disabled>
            <i class="bi bi-check2-circle me-1"></i>Create Purchase Order
        </button>
    </div>
</form>
@endsection

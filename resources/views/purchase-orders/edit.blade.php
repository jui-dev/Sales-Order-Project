@extends('layouts.app')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Edit {{ $order->code }}</h1>
        <p class="text-muted mb-0">Draft orders can still be changed.</p>
    </div>
    <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-outline-secondary">Back to Order</a>
</div>
@endsection

@section('content')
<form action="{{ route('purchase-orders.update', $order) }}" method="POST">
    @csrf
    @method('PUT')

    @include('purchase-orders._form')

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" data-order-submit disabled>
            <i class="bi bi-check2-circle me-1"></i>Save Changes
        </button>
    </div>
</form>
@endsection

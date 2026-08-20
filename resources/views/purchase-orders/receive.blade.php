@extends('layouts.app')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Record Supply for {{ $order->code }}</h1>
        <p class="text-muted mb-0">
            From {{ $order->vendor->name ?? 'Unknown vendor' }}
            into {{ $order->warehouse->name ?? 'Unknown warehouse' }}
        </p>
    </div>
    <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-outline-secondary">Back to Order</a>
</div>
@endsection

@section('content')
<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    Record what <strong>actually arrived</strong>. Quantities are prefilled with what is still outstanding —
    change them if the delivery was short. The stock still does not enter the warehouse here: that happens when
    the GRN is posted.
</div>

<form action="{{ route('purchase-orders.record-supply', $order) }}" method="POST">
    @csrf

    <div class="card mb-4">
        <div class="card-header"><h2 class="h5 mb-0">Delivery Details</h2></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="supply_date" class="form-label">Date Received <span class="text-danger">*</span></label>
                    <input type="date" name="supply_date" id="supply_date"
                           class="form-control @error('supply_date') is-invalid @enderror"
                           value="{{ old('supply_date', now()->format('Y-m-d')) }}" required>
                    @error('supply_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label for="notes" class="form-label">Notes</label>
                    <input type="text" name="notes" id="notes" class="form-control" value="{{ old('notes') }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h2 class="h5 mb-0">What Arrived</h2></div>
        <div class="card-body">
            @error('products')<div class="alert alert-danger">{{ $message }}</div>@enderror

            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-end">Ordered</th>
                        <th class="text-end">Already Received</th>
                        <th style="width: 9rem;">Receiving Now</th>
                        <th style="width: 10rem;">Unit Cost</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $index => $item)
                        <tr>
                            <td>
                                {{ $item->product->name ?? 'Unknown product' }}
                                <input type="hidden" name="products[{{ $index }}][product_id]" value="{{ $item->product_id }}">
                            </td>
                            <td class="text-end">{{ $item->quantity_ordered }}</td>
                            <td class="text-end">{{ $item->quantity_received }}</td>
                            <td>
                                {{-- Prefilled with the outstanding amount; 0 means this line did not turn up --}}
                                <input type="number" name="products[{{ $index }}][quantity]"
                                       class="form-control @error("products.{$index}.quantity") is-invalid @enderror"
                                       min="0" value="{{ old("products.{$index}.quantity", $item->outstanding()) }}" required>
                                @error("products.{$index}.quantity")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </td>
                            <td>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="products[{{ $index }}][unit_cost]"
                                           class="form-control" min="0" step="0.01"
                                           value="{{ old("products.{$index}.unit_cost", $item->unit_cost) }}" required>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-box-seam me-1"></i>Record Supply
        </button>
    </div>
</form>
@endsection

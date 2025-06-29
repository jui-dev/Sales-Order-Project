@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-plus-circle me-2"></i>Create Picking List</h1>
    <a href="{{ route('picking.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Picking Lists
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-md-10">
        <form action="{{ route('picking.store') }}" method="POST" id="pickingForm">
            @csrf
            
            <!-- Picking Information Section -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header py-3" style="background-color: #f0fdf4;">
                    <div class="d-flex align-items-center">
                        <h5 class="mb-0 flex-grow-1 text-dark">
                            <i class="bi bi-info-circle me-2 text-secondary"></i>
                            <span class="fw-semibold">Picking Information</span>
                            <span class="badge bg-white text-dark border ms-2 px-3">Step 1</span>
                        </h5>
                    </div>
                </div>
                <div class="card-body bg-white">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary">Picking Type</label>
                            <select name="picking_type" class="form-select shadow-sm" required onchange="updateToLocation(this.value)">
                                <option value="">Select Type</option>
                                <option value="warehouse_to_customer">Warehouse to Customer</option>
                                <option value="retailer_to_customer">Retailer to Customer</option>
                                <option value="warehouse_to_retailer">Warehouse to Retailer</option>
                                <option value="retailer_to_warehouse">Retailer to Warehouse</option>
                                <option value="vendor_to_warehouse">Vendor to Warehouse</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3" id="orderSelectContainer" style="display: none;">
                            <label class="form-label text-secondary">Related Order</label>
                            <select name="reference_id" class="form-select shadow-sm">
                                <option value="">Select Order</option>
                                @foreach($orders as $order)
                                    <option value="{{ $order->id }}">Order #{{ $order->id }} - {{ $order->customer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary">From Location</label>
                            <select name="from_location_id" id="from_location_id" class="form-select shadow-sm" required>
                                <option value="">Select Location</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary">To Location</label>
                            <select name="to_location_id" id="to_location_id" class="form-select shadow-sm" required>
                                <option value="">Select Location</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary">Notes</label>
                        <textarea name="notes" class="form-control shadow-sm" rows="3" placeholder="Add any additional notes or instructions"></textarea>
                    </div>
                </div>
            </div>

            <!-- Items to Pick Section -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header py-3" style="background-color: #f0fdf4;">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 text-dark">
                            <i class="bi bi-box-seam me-2 text-secondary"></i>
                            <span class="fw-semibold">Items to Pick</span>
                            <span class="badge bg-white text-dark border ms-2 px-3">Step 2</span>
                        </h5>
                        <button type="button" class="btn btn-dark btn-sm px-3" onclick="addItem()">
                            <i class="bi bi-plus-lg me-1"></i> Add Item
                        </button>
                    </div>
                </div>
                <div class="card-body bg-white">
                    <div id="items-container">
                        <!-- Items will be added here -->
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('picking.index') }}" class="btn btn-light border shadow-sm">Cancel</a>
                <button type="submit" class="btn btn-primary shadow-sm">
                    <i class="bi bi-check-circle me-1"></i> Create Picking List
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Item Template -->
<template id="item-template">
    <div class="row mb-3 item-row align-items-end">
        <div class="col-md-6">
            <label class="form-label text-secondary">Product</label>
            <select class="form-select shadow-sm" name="items[INDEX][product_id]" required>
                <option value="">Select Product</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label text-secondary">Quantity</label>
            <input type="number" class="form-control shadow-sm" name="items[INDEX][quantity]" min="1" required>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-outline-danger w-100 shadow-sm" onclick="removeItem(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>
</template>

@endsection

@section('scripts')
<script>
let itemIndex = 0;

function addItem() {
    const template = document.getElementById('item-template');
    const container = document.getElementById('items-container');
    
    const clone = template.content.cloneNode(true);
    const html = clone.querySelector('.item-row').outerHTML.replace(/INDEX/g, itemIndex);
    
    container.insertAdjacentHTML('beforeend', html);
    itemIndex++;
}

function removeItem(button) {
    button.closest('.item-row').remove();
}

function updateToLocation(pickingType) {
    const fromLocationSelect = document.getElementById('from_location_id');
    const toLocationSelect = document.getElementById('to_location_id');
    const orderSelectContainer = document.getElementById('orderSelectContainer');
    
    // Reset both selects
    fromLocationSelect.innerHTML = '<option value="">Select Location</option>';
    toLocationSelect.innerHTML = '<option value="">Select Location</option>';
    
    // Hide order select by default
    orderSelectContainer.style.display = 'none';
    
    // Update options based on picking type
    switch(pickingType) {
        case 'warehouse_to_customer':
            // From: Warehouses only
            @foreach($warehouses as $warehouse)
                fromLocationSelect.innerHTML += `<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>`;
            @endforeach
            // To: Customer (no location needed)
            toLocationSelect.innerHTML = '<option value="">Customer Delivery</option>';
            toLocationSelect.disabled = true;
            orderSelectContainer.style.display = 'block';
            break;
            
        case 'retailer_to_customer':
            // From: Retailers only
            @foreach($retailers as $retailer)
                fromLocationSelect.innerHTML += `<option value="{{ $retailer->id }}">{{ $retailer->name }}</option>`;
            @endforeach
            // To: Customer (no location needed)
            toLocationSelect.innerHTML = '<option value="">Customer Delivery</option>';
            toLocationSelect.disabled = true;
            orderSelectContainer.style.display = 'block';
            break;
            
        case 'warehouse_to_retailer':
            // From: Warehouses only
            @foreach($warehouses as $warehouse)
                fromLocationSelect.innerHTML += `<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>`;
            @endforeach
            // To: Retailers only
            @foreach($retailers as $retailer)
                toLocationSelect.innerHTML += `<option value="{{ $retailer->id }}">{{ $retailer->name }}</option>`;
            @endforeach
            toLocationSelect.disabled = false;
            break;
            
        case 'retailer_to_warehouse':
            // From: Retailers only
            @foreach($retailers as $retailer)
                fromLocationSelect.innerHTML += `<option value="{{ $retailer->id }}">{{ $retailer->name }}</option>`;
            @endforeach
            // To: Warehouses only
            @foreach($warehouses as $warehouse)
                toLocationSelect.innerHTML += `<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>`;
            @endforeach
            toLocationSelect.disabled = false;
            break;
            
        case 'vendor_to_warehouse':
            // From: Vendor (no location needed)
            fromLocationSelect.innerHTML = '<option value="">Vendor Delivery</option>';
            fromLocationSelect.disabled = true;
            // To: Warehouses only
            @foreach($warehouses as $warehouse)
                toLocationSelect.innerHTML += `<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>`;
            @endforeach
            toLocationSelect.disabled = false;
            break;
            
        default:
            fromLocationSelect.innerHTML = '<option value="">Select Location</option>';
            toLocationSelect.innerHTML = '<option value="">Select Location</option>';
            toLocationSelect.disabled = false;
            break;
    }
}

// Add first item on page load
document.addEventListener('DOMContentLoaded', function() {
    addItem();
});
</script>
@endsection 
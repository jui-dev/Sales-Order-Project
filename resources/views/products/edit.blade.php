@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Edit Product</h1>
    <div>
        <a href="{{ route('products.show', $product) }}" class="btn btn-info">View Product</a>
        <a href="{{ route('products.index') }}" class="btn btn-secondary">Back to Products</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('products.update', $product) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $product->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="selling_price" class="form-label">Selling Price</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control @error('selling_price') is-invalid @enderror" id="selling_price" name="selling_price" value="{{ old('selling_price', $product->selling_price) }}" min="0" step="0.01" required>
                    </div>
                    @error('selling_price')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label for="warehouse_stock" class="form-label">Warehouse Stock</label>
                    <input type="number" class="form-control @error('warehouse_stock') is-invalid @enderror" id="warehouse_stock" name="warehouse_stock" value="{{ old('warehouse_stock', $product->warehouse_stock ?? 0) }}" min="0">
                    @error('warehouse_stock')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label for="retailer_stock" class="form-label">Retailer Stock</label>
                    <input type="number" class="form-control @error('retailer_stock') is-invalid @enderror" id="retailer_stock" name="retailer_stock" value="{{ old('retailer_stock', $product->retailer_stock ?? 0) }}" min="0">
                    @error('retailer_stock')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <!-- Stock Transfer Helper -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card border-secondary">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Quick Stock Transfer</h6>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-end">
                                <div class="col-md-3">
                                    <label for="transfer_quantity" class="form-label">Quantity to Transfer</label>
                                    <input type="number" class="form-control" id="transfer_quantity" min="1">
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-outline-primary" onclick="transferStock('warehouse_to_retailer')">
                                        Warehouse → Retailer
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-outline-secondary" onclick="transferStock('retailer_to_warehouse')">
                                        Retailer → Warehouse
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">
                                        Total Stock: <span id="total_stock">{{ ($product->warehouse_stock ?? 0) + ($product->retailer_stock ?? 0) }}</span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info mb-4">
                <i class="bi bi-info-circle"></i>
                Product purchase prices, GP%, and stock levels are automatically updated through supply and order transactions.
            </div>
            
            <div class="text-end">
                <button type="submit" class="btn btn-primary">Update Product</button>
            </div>
        </form>
    </div>
</div>
@endsection

<script>
function transferStock(direction) {
    const quantity = parseInt(document.getElementById('transfer_quantity').value);
    const warehouseInput = document.getElementById('warehouse_stock');
    const retailerInput = document.getElementById('retailer_stock');
    const totalDisplay = document.getElementById('total_stock');
    
    if (!quantity || quantity <= 0) {
        alert('Please enter a valid quantity to transfer');
        return;
    }
    
    let warehouseStock = parseInt(warehouseInput.value) || 0;
    let retailerStock = parseInt(retailerInput.value) || 0;
    
    if (direction === 'warehouse_to_retailer') {
        if (warehouseStock < quantity) {
            alert('Not enough warehouse stock to transfer');
            return;
        }
        warehouseStock -= quantity;
        retailerStock += quantity;
    } else if (direction === 'retailer_to_warehouse') {
        if (retailerStock < quantity) {
            alert('Not enough retailer stock to transfer');
            return;
        }
        retailerStock -= quantity;
        warehouseStock += quantity;
    }
    
    // Update the input fields
    warehouseInput.value = warehouseStock;
    retailerInput.value = retailerStock;
    totalDisplay.textContent = warehouseStock + retailerStock;
    
    // Clear transfer quantity
    document.getElementById('transfer_quantity').value = '';
}

// Update total stock when individual stock fields change
document.addEventListener('DOMContentLoaded', function() {
    const warehouseInput = document.getElementById('warehouse_stock');
    const retailerInput = document.getElementById('retailer_stock');
    const totalDisplay = document.getElementById('total_stock');
    
    function updateTotal() {
        const warehouse = parseInt(warehouseInput.value) || 0;
        const retailer = parseInt(retailerInput.value) || 0;
        totalDisplay.textContent = warehouse + retailer;
    }
    
    warehouseInput.addEventListener('input', updateTotal);
    retailerInput.addEventListener('input', updateTotal);
});
</script> 
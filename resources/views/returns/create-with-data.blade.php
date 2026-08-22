@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Create Return Transaction</h1>
        <a href="{{ route('returns.create') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Selection
        </a>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    
    

    <form action="{{ route('returns.store') }}" method="POST" id="returnForm">
        @csrf
        
        <div class="row">
            <!-- Return Type Selection -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-arrow-return-left me-2"></i>Return Type
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Return Type <span class="text-danger">*</span></label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check border rounded p-3 h-100">
                                        <input class="form-check-input" type="radio" name="return_type" id="customer_return" value="customer_return" {{ $returnType === 'customer_return' ? 'checked' : '' }} required>
                                        <label class="form-check-label" for="customer_return">
                                            <i class="bi bi-arrow-return-left text-danger me-2"></i>
                                            <strong>Customer Return</strong>
                                            <br><small class="text-muted">Customer returning items from invoice</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check border rounded p-3 h-100">
                                        <input class="form-check-input" type="radio" name="return_type" id="vendor_return" value="vendor_return" {{ $returnType === 'vendor_return' ? 'checked' : '' }} required>
                                        <label class="form-check-label" for="vendor_return">
                                            <i class="bi bi-arrow-return-right text-info me-2"></i>
                                            <strong>Vendor Return</strong>
                                            <br><small class="text-muted">Returning items to vendor from supplier bill</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check border rounded p-3 h-100">
                                        <input class="form-check-input" type="radio" name="return_type" id="retailer_return" value="retailer_return" {{ $returnType === 'retailer_return' ? 'checked' : '' }} required>
                                        <label class="form-check-label" for="retailer_return">
                                            <i class="bi bi-arrow-return-left text-warning me-2"></i>
                                            <strong>Retailer Return</strong>
                                            <br><small class="text-muted">Retailer returning items from stock transfer</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Source Information -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-person me-2"></i>Source Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Customer Return Section -->
                        <div id="customer-section" class="source-section" style="display: none;">
                            <div class="mb-3">
                                <label for="customer_id" class="form-label">Customer <span class="text-danger">*</span></label>
                                <select class="form-select" id="customer_id" name="customer_id">
                                    <option value="">Choose a customer...</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" {{ $sourceId == $customer->id ? 'selected' : '' }}>
                                            {{ $customer->name }} ({{ $customer->email }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Vendor Return Section -->
                        <div id="vendor-section" class="source-section" style="display: none;">
                            <div class="mb-3">
                                <label for="vendor_id" class="form-label">Vendor <span class="text-danger">*</span></label>
                                <select class="form-select" id="vendor_id" name="vendor_id">
                                    <option value="">Choose a vendor...</option>
                                    @foreach($vendors as $vendor)
                                        <option value="{{ $vendor->id }}" {{ $sourceId == $vendor->id ? 'selected' : '' }}>
                                            {{ $vendor->name }} ({{ $vendor->email }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Retailer Return Section -->
                        <div id="retailer-section" class="source-section" style="display: none;">
                            <div class="mb-3">
                                <label for="retailer_id" class="form-label">Retailer <span class="text-danger">*</span></label>
                                <select class="form-select" id="retailer_id" name="retailer_id">
                                    <option value="">Choose a retailer...</option>
                                    @foreach($retailers as $retailer)
                                        <option value="{{ $retailer->id }}" {{ $sourceId == $retailer->id ? 'selected' : '' }}>
                                            {{ $retailer->name }} ({{ $retailer->email }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Default message when no return type is selected -->
                        <div id="no-selection-message" class="text-muted text-center py-3">
                            <i class="bi bi-info-circle me-2"></i>Please select a return type to see source information
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reference Document -->
        @if($referenceData)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-file-text me-2"></i>Reference Document
                </h5>
            </div>
            <div class="card-body">
                @if($returnType === 'customer_return')
                    <div class="mb-3">
                        <label for="invoice_id" class="form-label">Select Invoice <span class="text-danger">*</span></label>
                        <select class="form-select" id="invoice_id" name="invoice_id" required>
                            <option value="">Choose an invoice...</option>
                            @foreach($referenceData as $invoice)
                                <option value="{{ $invoice['id'] }}" {{ $referenceId == $invoice['id'] ? 'selected' : '' }}>
                                    {{ $invoice['invoice_number'] }} - {{ $invoice['invoice_date'] }} - ${{ $invoice['total'] }} - {{ $invoice['items_count'] }} items
                                </option>
                            @endforeach
                        </select>
                    </div>
                @elseif($returnType === 'vendor_return')
                    <div class="mb-3">
                        <label for="supplier_bill_id" class="form-label">Select Supplier Bill <span class="text-danger">*</span></label>
                        <select class="form-select" id="supplier_bill_id" name="supplier_bill_id" required>
                            <option value="">Choose a supplier bill...</option>
                            @foreach($referenceData as $bill)
                                <option value="{{ $bill['id'] }}" {{ $referenceId == $bill['id'] ? 'selected' : '' }}>
                                    {{ $bill['bill_number'] }} - {{ $bill['bill_date'] }} - ${{ $bill['total'] }} - {{ $bill['items_count'] }} items
                                </option>
                            @endforeach
                        </select>
                    </div>
                @elseif($returnType === 'retailer_return')
                    <div class="mb-3">
                        <label for="stock_transfer_id" class="form-label">Select Stock Transfer <span class="text-danger">*</span></label>
                        <select class="form-select" id="stock_transfer_id" name="stock_transfer_id" required>
                            <option value="">Choose a stock transfer...</option>
                            @foreach($referenceData as $transfer)
                                <option value="{{ $transfer['id'] }}" {{ $referenceId == $transfer['id'] ? 'selected' : '' }}>
                                    {{ $transfer['transfer_number'] }} - {{ $transfer['transfer_date'] }} - {{ $transfer['items_count'] }} items
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Product Details -->
        @if($productItems)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-box-seam me-2"></i>Product Details & Return Quantities
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle me-2"></i>Select the products you want to return and enter the return quantities.
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Original Quantity</th>
                                <th>Already Returned</th>
                                <th>Available for Return</th>
                                <th>Return Quantity</th>
                                <th>Unit Price</th>
                                <th>Total Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($productItems as $index => $item)
                                @if($item['quantity_available_for_return'] > 0)
                                    @php
                                        $unitPrice = $returnType === 'customer_return' ? $item['unit_price'] : 
                                                   ($returnType === 'vendor_return' ? $item['unit_cost'] : 0);
                                        $totalValue = $unitPrice * $item['quantity_available_for_return'];
                                    @endphp
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input product-checkbox" 
                                                   data-index="{{ $index }}" data-product-id="{{ $item['product_id'] }}">
                                        </td>
                                        <td>{{ $item['product_name'] }}</td>
                                        <td>{{ $item['product_sku'] }}</td>
                                        <td>
                                            @if($returnType === 'customer_return')
                                                {{ $item['quantity_sold'] }}
                                            @elseif($returnType === 'vendor_return')
                                                {{ $item['quantity_purchased'] }}
                                            @else
                                                {{ $item['quantity_transferred'] }}
                                            @endif
                                        </td>
                                        <td>{{ $item['already_returned'] }}</td>
                                        <td>{{ $item['quantity_available_for_return'] }}</td>
                                        <td>
                                            <input type="number" class="form-control return-quantity" 
                                                   data-index="{{ $index }}" 
                                                   min="1" max="{{ $item['quantity_available_for_return'] }}" 
                                                   value="0" disabled>
                                        </td>
                                        <td>${{ number_format($unitPrice, 2) }}</td>
                                        <td>${{ number_format($totalValue, 2) }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <!-- Return Destination -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-geo-alt me-2"></i>Return Destination
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="return_location_id" class="form-label">Select Destination <span class="text-danger">*</span></label>
                    <select class="form-select" id="return_location_id" name="return_location_id" required>
                        <option value="">Choose a destination...</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" id="return_location_type" name="return_location_type" value="App\Models\Warehouse">
                </div>
            </div>
        </div>

        <!-- Return Details -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-file-text me-2"></i>Return Details
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="return_date" class="form-label">Return Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="return_date" name="return_date" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Additional notes (optional)..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hidden Fields for Form Submission -->
        <div id="hiddenInputs" style="display: none;"></div>

        <!-- Submit Button -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <a href="{{ route('returns.create') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back to Selection
                    </a>
                    <button type="submit" class="btn btn-primary" id="submitBtn" {{ !$productItems ? 'disabled' : '' }}>
                        <i class="bi bi-check-circle me-1"></i> Create Return
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
console.log('Script loaded successfully');
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded successfully');
    try {
        console.log('Try block entered');
        
        // Simple test to see if basic functionality works
        const returnTypeRadios = document.querySelectorAll('input[name="return_type"]');
        console.log('Found return type radios:', returnTypeRadios.length);
        
        // Test the updateSourceInformation function
        function updateSourceInformation(returnType) {
            console.log('Updating source information for:', returnType);
            // Hide all source sections
            document.querySelectorAll('.source-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Hide default message
            const noSelectionMessage = document.getElementById('no-selection-message');
            if (noSelectionMessage) {
                noSelectionMessage.style.display = 'none';
            }

            // Show appropriate section based on return type
            if (returnType === 'customer_return') {
                const customerSection = document.getElementById('customer-section');
                if (customerSection) {
                    customerSection.style.display = 'block';
                }
                const customerSelect = document.getElementById('customer_id');
                if (customerSelect) {
                    customerSelect.required = true;
                }
            } else if (returnType === 'vendor_return') {
                const vendorSection = document.getElementById('vendor-section');
                if (vendorSection) {
                    vendorSection.style.display = 'block';
                }
                const vendorSelect = document.getElementById('vendor_id');
                if (vendorSelect) {
                    vendorSelect.required = true;
                }
            } else if (returnType === 'retailer_return') {
                const retailerSection = document.getElementById('retailer-section');
                if (retailerSection) {
                    retailerSection.style.display = 'block';
                }
                const retailerSelect = document.getElementById('retailer_id');
                if (retailerSelect) {
                    retailerSelect.required = true;
                }
            } else {
                // Show default message if no return type is selected
                if (noSelectionMessage) {
                    noSelectionMessage.style.display = 'block';
                }
            }
        }

        // Return type selection functionality
        returnTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                console.log('Return type changed to:', this.value);
                updateSourceInformation(this.value);
            });
        });

        // Initialize source information based on pre-selected return type
        const selectedReturnType = document.querySelector('input[name="return_type"]:checked');
        if (selectedReturnType) {
            console.log('Initializing with selected return type:', selectedReturnType.value);
            updateSourceInformation(selectedReturnType.value);
        }
        
        console.log('Script initialization completed successfully');
        
    } catch (error) {
        console.error('Error in return form initialization:', error);
    }
});
</script>
@endpush 
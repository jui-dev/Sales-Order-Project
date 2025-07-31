@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <x-breadcrumb :items="[
        ['label' => 'Returns', 'url' => route('returns.index')],
        ['label' => 'Create Return', 'url' => '#']
    ]" />
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-plus-circle me-2"></i>Create Return Transaction</h1>
        <div>
            <a href="{{ route('returns.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Returns
            </a>
        </div>
    </div>

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
                            <label class="form-label">Select Return Type <span class="text-danger">*</span></label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check border rounded p-3 h-100">
                                        <input class="form-check-input" type="radio" name="return_type" id="customer_return" value="customer_return" required>
                                        <label class="form-check-label" for="customer_return">
                                            <i class="bi bi-arrow-return-left text-danger me-2"></i>
                                            <strong>Customer Return</strong>
                                            <br><small class="text-muted">Customer returning items from invoice</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check border rounded p-3 h-100">
                                        <input class="form-check-input" type="radio" name="return_type" id="vendor_return" value="vendor_return" required>
                                        <label class="form-check-label" for="vendor_return">
                                            <i class="bi bi-arrow-return-right text-info me-2"></i>
                                            <strong>Vendor Return</strong>
                                            <br><small class="text-muted">Returning items to vendor from supplier bill</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check border rounded p-3 h-100">
                                        <input class="form-check-input" type="radio" name="return_type" id="retailer_return" value="retailer_return" required>
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

            <!-- Source Selection -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-person me-2"></i>Source Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Customer Selection (for Customer Returns) -->
                        <div id="customerSection" style="display: none;">
                            <div class="mb-3">
                                <label for="customer_id" class="form-label">Select Customer <span class="text-danger">*</span></label>
                                <select class="form-select" id="customer_id" name="customer_id">
                                    <option value="">Choose a customer...</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->email }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Vendor Selection (for Vendor Returns) -->
                        <div id="vendorSection" style="display: none;">
                            <div class="mb-3">
                                <label for="vendor_id" class="form-label">Select Vendor <span class="text-danger">*</span></label>
                                <select class="form-select" id="vendor_id" name="vendor_id">
                                    <option value="">Choose a vendor...</option>
                                    @foreach($vendors as $vendor)
                                        <option value="{{ $vendor->id }}">{{ $vendor->name }} ({{ $vendor->email }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Retailer Selection (for Retailer Returns) -->
                        <div id="retailerSection" style="display: none;">
                            <div class="mb-3">
                                <label for="retailer_id" class="form-label">Select Retailer <span class="text-danger">*</span></label>
                                <select class="form-select" id="retailer_id" name="retailer_id">
                                    <option value="">Choose a retailer...</option>
                                    @foreach($retailers as $retailer)
                                        <option value="{{ $retailer->id }}">{{ $retailer->name }} ({{ $retailer->email }})</option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Only retailers with completed stock transfers can be selected for returns.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reference Selection -->
        <div class="card mb-4" id="referenceSection" style="display: none;">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-file-text me-2"></i>Reference Document
                </h5>
            </div>
            <div class="card-body">
                <!-- Invoice Selection (for Customer Returns) -->
                <div id="invoiceSection" style="display: none;">
                    <div class="mb-3">
                        <label for="invoice_select" class="form-label">Select Invoice <span class="text-danger">*</span></label>
                        <select class="form-select" id="invoice_select" name="invoice_id">
                            <option value="">Choose an invoice...</option>
                        </select>
                    </div>
                </div>

                <!-- Supplier Bill Selection (for Vendor Returns) -->
                <div id="supplierBillSection" style="display: none;">
                    <div class="mb-3">
                        <label for="supplier_bill_select" class="form-label">Select Supplier Bill <span class="text-danger">*</span></label>
                        <select class="form-select" id="supplier_bill_select" name="supplier_bill_id">
                            <option value="">Choose a supplier bill...</option>
                        </select>
                    </div>
                </div>

                <!-- Stock Transfer Selection (for Retailer Returns) -->
                <div id="stockTransferSection" style="display: none;">
                    <div class="mb-3">
                        <label for="stock_transfer_select" class="form-label">Select Stock Transfer <span class="text-danger">*</span></label>
                        <select class="form-select" id="stock_transfer_select" name="stock_transfer_id">
                            <option value="">Choose a stock transfer...</option>
                        </select>
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>
                            Only completed stock transfers are available for retailer returns. Pending or cancelled transfers cannot be used.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dynamic Product Details Section -->
        <div class="card mb-4" id="productSection" style="display: none;">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-box-seam me-2"></i>Product Details & Return Quantities
                </h5>
            </div>
            <div class="card-body">
                <div id="productDetailsContainer">
                    <!-- Product details will be dynamically loaded here -->
                </div>
            </div>
        </div>

        <!-- Return Summary -->
        <div class="card mb-4" id="summarySection" style="display: none;">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-calculator me-2"></i>Return Summary
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-6">Total Items Selected:</dt>
                            <dd class="col-sm-6" id="totalItemsCount">0</dd>
                            
                            <dt class="col-sm-6">Total Quantity:</dt>
                            <dd class="col-sm-6" id="totalQuantity">0</dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-6">Total Return Value:</dt>
                            <dd class="col-sm-6" id="totalReturnValue">$0.00</dd>
                            
                            <dt class="col-sm-6">Status:</dt>
                            <dd class="col-sm-6" id="summaryStatus">
                                <span class="badge bg-warning">Pending</span>
                            </dd>
                        </dl>
                    </div>
                </div>
                
                <!-- Dynamic Validation Summary -->
                <div id="validationSummary" class="mt-3" style="display: none;">
                    <hr>
                    <h6 class="text-muted mb-2">
                        <i class="bi bi-shield-check me-2"></i>Validation Summary
                    </h6>
                    <div id="validationSummaryContent"></div>
                </div>
            </div>
        </div>

        <!-- Hidden destination fields for form submission -->
        <input type="hidden" id="return_location_id" name="return_location_id" required>
        <input type="hidden" id="return_location_type" name="return_location_type">

        <!-- Return Reason Section -->
        <div class="card mb-4" id="returnReasonSection" style="display: none;">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>Return Reason
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="return_reason" class="form-label">Return Reason <span class="text-danger">*</span></label>
                            <select class="form-select" id="return_reason" name="return_reason" required>
                                <option value="">Select a return reason...</option>
                                <option value="defective_product">Defective Product</option>
                                <option value="wrong_item_received">Wrong Item Received</option>
                                <option value="damaged_during_shipping">Damaged During Shipping</option>
                                <option value="quality_issues">Quality Issues</option>
                                <option value="size_fit_issues">Size/Fit Issues</option>
                                <option value="customer_change_of_mind">Customer Change of Mind</option>
                                <option value="duplicate_order">Duplicate Order</option>
                                <option value="expired_product">Expired Product</option>
                                <option value="incorrect_quantity">Incorrect Quantity</option>
                                <option value="other">Other</option>
                            </select>
                            <div class="form-text">Please select the primary reason for this return.</div>
                        </div>
                    </div>
                </div>
                <div class="row" id="otherReasonRow" style="display: none;">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="other_reason_details" class="form-label">Additional Details</label>
                            <textarea class="form-control" id="other_reason_details" name="other_reason_details" rows="3" placeholder="Please provide additional details about the return reason..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Return Details -->
        <div class="card mb-4" id="detailsSection" style="display: none;">
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
        <input type="hidden" id="reference_id" name="reference_id" required>
        <input type="hidden" id="invoice_id" name="invoice_id">
        <input type="hidden" id="supplier_bill_id" name="supplier_bill_id">
        <input type="hidden" id="stock_transfer_id" name="stock_transfer_id">
        <input type="hidden" id="warehouse_id" name="warehouse_id">

        <!-- Hidden container for dynamic inputs -->
        <div id="hiddenInputs" style="display: none;"></div>

        <!-- Action Buttons -->
        <div class="card border-0 shadow-sm mb-4 return-action-card">
            <div class="card-body py-4">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                    <i class="bi bi-arrow-return-left text-primary fs-3"></i>
                                </div>
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold text-dark">Return Transaction Ready</h6>
                                <small class="text-muted">Review your selections and submit the return request</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="d-flex flex-column gap-3">
                            <div id="validationMessage" style="display: none;">
                                <div class="alert alert-danger border-0 mb-0 py-2 px-3">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <span id="validationText" class="fw-semibold"></span>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-3 justify-content-end">
                                <a href="{{ route('returns.index') }}" class="btn btn-outline-secondary btn-lg px-4 fw-semibold">
                                    <i class="bi bi-x-circle me-2"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg px-4 fw-semibold" id="submitBtn" disabled>
                                    <i class="bi bi-check-circle me-2"></i> Create Return
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get DOM elements
    const returnTypeRadios = document.querySelectorAll('input[name="return_type"]');
    const customerSelect = document.getElementById('customer_id');
    const vendorSelect = document.getElementById('vendor_id');
    const retailerSelect = document.getElementById('retailer_id');
    const invoiceSelect = document.getElementById('invoice_select');
    const supplierBillSelect = document.getElementById('supplier_bill_select');
    const stockTransferSelect = document.getElementById('stock_transfer_select');
    const productDetailsContainer = document.getElementById('productDetailsContainer');
    const destinationTypeInput = document.getElementById('return_location_type');
    const submitBtn = document.getElementById('submitBtn');
    
    const warehouses = @json($warehouses);
    let selectedItems = [];
    
    // Debug: Check if radio buttons exist
    console.log('Radio buttons found:', returnTypeRadios.length);
    returnTypeRadios.forEach((radio, index) => {
        console.log(`Radio ${index}:`, {
            value: radio.value,
            checked: radio.checked,
            id: radio.id,
            name: radio.name
        });
    });

    // Return type selection handler - Multiple approaches
    console.log('Setting up return type radio handlers for', returnTypeRadios.length, 'radios');
    
    returnTypeRadios.forEach((radio, index) => {
        console.log(`Setting up radio ${index}:`, radio.value, radio.checked);
        
        radio.addEventListener('change', function() {
            console.log('Return type changed:', this.value, 'checked:', this.checked);
            if (this.checked) {
                console.log('Showing source section for:', this.value);
                showSourceSection(this.value);
                hideAllReferenceSections();
                hideProductSection();
                hideReturnReasonSection();
                hideDetailsSection();
                selectedItems = [];
                updateSubmitButton(); // Update button state
            }
        });
        
        // Also add click event as backup
        radio.addEventListener('click', function() {
            console.log('Radio clicked:', this.value, this.checked);
            setTimeout(() => {
                if (this.checked) {
                    console.log('Click backup - showing source section for:', this.value);
                    showSourceSection(this.value);
                    hideAllReferenceSections();
                    hideProductSection();
                    hideReturnReasonSection();
                    hideDetailsSection();
                    selectedItems = [];
                    updateSubmitButton(); // Update button state
                }
            }, 10);
        });
        
        // Add input event as additional backup
        radio.addEventListener('input', function() {
            console.log('Radio input event:', this.value, this.checked);
            if (this.checked) {
                console.log('Input event - showing source section for:', this.value);
                showSourceSection(this.value);
                hideAllReferenceSections();
                hideProductSection();
                hideReturnReasonSection();
                hideDetailsSection();
                selectedItems = [];
                updateSubmitButton(); // Update button state
            }
        });
    });
    
    // Event delegation on form as additional backup
    document.getElementById('returnForm').addEventListener('change', function(e) {
        console.log('Form change event:', e.target.name, e.target.value, e.target.checked);
        if (e.target.name === 'return_type' && e.target.checked) {
            console.log('Form delegation - showing source section for:', e.target.value);
            showSourceSection(e.target.value);
            hideAllReferenceSections();
            hideProductSection();
            hideReturnReasonSection();
            hideDetailsSection();
            selectedItems = [];
            updateSubmitButton(); // Update button state
        }
    });
    
    // Additional event listener for radio button changes
    document.addEventListener('change', function(e) {
        if (e.target.name === 'return_type' && e.target.checked) {
            console.log('Global change event - showing source section for:', e.target.value);
            showSourceSection(e.target.value);
            hideAllReferenceSections();
            hideProductSection();
            hideReturnReasonSection();
            hideDetailsSection();
            selectedItems = [];
            updateSubmitButton(); // Update button state
        }
    });
    


    // Source selection handlers
    customerSelect.addEventListener('change', function() {
        if (this.value) {
            fetchCustomerInvoices(this.value);
        } else {
            hideReferenceSection();
        }
    });

    vendorSelect.addEventListener('change', function() {
        if (this.value) {
            fetchVendorSupplierBills(this.value);
        } else {
            hideReferenceSection();
        }
    });

    retailerSelect.addEventListener('change', function() {
        if (this.value) {
            fetchRetailerStockTransfers(this.value);
        } else {
            hideReferenceSection();
        }
    });

    // Reference selection handlers
    invoiceSelect.addEventListener('change', function() {
        if (this.value) {
            document.getElementById('invoice_id').value = this.value;
            fetchInvoiceItems(this.value);
        } else {
            document.getElementById('invoice_id').value = '';
            hideProductSection();
            hideDestinationSection();
            hideDetailsSection();
        }
    });

    supplierBillSelect.addEventListener('change', function() {
        if (this.value) {
            document.getElementById('supplier_bill_id').value = this.value;
            fetchSupplierBillItems(this.value);
        } else {
            document.getElementById('supplier_bill_id').value = '';
            hideProductSection();
            hideDestinationSection();
            hideDetailsSection();
        }
    });

    stockTransferSelect.addEventListener('change', function() {
        if (this.value) {
            document.getElementById('stock_transfer_id').value = this.value;
            fetchStockTransferItems(this.value);
        } else {
            document.getElementById('stock_transfer_id').value = '';
            hideProductSection();
            hideDestinationSection();
            hideDetailsSection();
        }
    });

    function showSourceSection(returnType) {
        console.log('showSourceSection called with:', returnType);
        hideAllSourceSections();
        
        let sectionId = null;
        switch (returnType) {
            case 'customer_return':
                sectionId = 'customerSection';
                break;
            case 'vendor_return':
                sectionId = 'vendorSection';
                break;
            case 'retailer_return':
                sectionId = 'retailerSection';
                break;
        }
        
        console.log('Section ID:', sectionId);
        
        if (sectionId) {
            const section = document.getElementById(sectionId);
            console.log('Found section:', section);
            if (section) {
                // Use a more reliable method to show the section
                section.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; height: auto !important; overflow: visible !important;';
                console.log('Section should now be visible');
                
                // Additional debugging
                console.log('Section display style after setting:', section.style.display);
                console.log('Section visibility after setting:', section.style.visibility);
                console.log('Section opacity after setting:', section.style.opacity);
                console.log('Section computed style:', window.getComputedStyle(section).display);
                
                // Force a reflow
                section.offsetHeight;
            } else {
                console.error('Section not found:', sectionId);
            }
        }
        
        hideReferenceSection();
    }

    function hideAllSourceSections() {
        const customerSection = document.getElementById('customerSection');
        const vendorSection = document.getElementById('vendorSection');
        const retailerSection = document.getElementById('retailerSection');
        
        console.log('hideAllSourceSections - sections found:', {
            customerSection: !!customerSection,
            vendorSection: !!vendorSection,
            retailerSection: !!retailerSection
        });
        
        [customerSection, vendorSection, retailerSection].forEach(section => {
            if (section) {
                section.style.display = 'none';
                console.log('Hidden section:', section.id);
            }
        });
    }

    function showReferenceSection() {
        document.getElementById('referenceSection').style.display = 'block';
    }

    function hideReferenceSection() {
        document.getElementById('referenceSection').style.display = 'none';
        hideProductSection();
    }

    function hideAllReferenceSections() {
        document.getElementById('invoiceSection').style.display = 'none';
        document.getElementById('supplierBillSection').style.display = 'none';
        document.getElementById('stockTransferSection').style.display = 'none';
    }

    function showReferenceSectionByType(returnType) {
        hideAllReferenceSections();
        
        switch (returnType) {
            case 'customer_return':
                document.getElementById('invoiceSection').style.display = 'block';
                break;
            case 'vendor_return':
                document.getElementById('supplierBillSection').style.display = 'block';
                break;
            case 'retailer_return':
                document.getElementById('stockTransferSection').style.display = 'block';
                break;
        }
    }

    function fetchCustomerInvoices(customerId) {
        showReferenceSectionByType('customer_return');
        showReferenceSection();
        
        // Simple fetch with error handling
        fetch(`/returns/ajax/customer-invoices/${customerId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.invoices && Array.isArray(data.invoices)) {
                    populateInvoices(data.invoices);
                } else {
                    console.error('Invalid customer invoices data structure:', data);
                    alert('Invalid response format. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error fetching invoices:', error);
                alert('Error loading invoices. Please try again.');
            });
    }

    function fetchVendorSupplierBills(vendorId) {
        showReferenceSectionByType('vendor_return');
        showReferenceSection();
        
        fetch(`/returns/ajax/vendor-supplier-bills/${vendorId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                populateSupplierBills(data.supplier_bills);
            })
            .catch(error => {
                console.error('Error fetching supplier bills:', error);
                alert('Error loading supplier bills. Please try again.');
            });
    }

    function fetchRetailerStockTransfers(retailerId) {
        showReferenceSectionByType('retailer_return');
        showReferenceSection();
        
        fetch(`/returns/ajax/retailer-stock-transfers/${retailerId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                populateStockTransfers(data.stock_transfers);
            })
            .catch(error => {
                console.error('Error fetching stock transfers:', error);
                alert('Error loading stock transfers. Please try again.');
            });
    }

    function populateInvoices(invoices) {
        invoiceSelect.innerHTML = '<option value="">Choose an invoice...</option>';
        
        invoices.forEach(invoice => {
            const option = document.createElement('option');
            option.value = invoice.id;
            option.textContent = `${invoice.invoice_number} - ${invoice.invoice_date} - $${invoice.total} - ${invoice.items_count} items`;
            invoiceSelect.appendChild(option);
        });
    }

    function populateSupplierBills(supplierBills) {
        supplierBillSelect.innerHTML = '<option value="">Choose a supplier bill...</option>';
        
        supplierBills.forEach(bill => {
            const option = document.createElement('option');
            option.value = bill.id;
            option.textContent = `${bill.bill_number} - ${bill.bill_date} - $${bill.total} - ${bill.items_count} items`;
            supplierBillSelect.appendChild(option);
        });
    }

    function populateStockTransfers(stockTransfers) {
        stockTransferSelect.innerHTML = '<option value="">Choose a stock transfer...</option>';
        
        if (stockTransfers.length === 0) {
            const option = document.createElement('option');
            option.value = "";
            option.textContent = "No completed stock transfers available for this retailer";
            option.disabled = true;
            stockTransferSelect.appendChild(option);
            return;
        }
        
        stockTransfers.forEach(transfer => {
            const option = document.createElement('option');
            option.value = transfer.id;
            option.textContent = `${transfer.transfer_number} - ${transfer.transfer_date} - ${transfer.items_count} items (${transfer.status})`;
            stockTransferSelect.appendChild(option);
        });
    }

    function fetchInvoiceItems(invoiceId) {
        // Add loading indicator
        productDetailsContainer.innerHTML = '<div class="text-center"><i class="bi bi-arrow-clockwise spin"></i> Loading products...</div>';
        showProductSection();
        
        fetch(`/returns/ajax/invoice-items/${invoiceId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.items && Array.isArray(data.items)) {
                    renderProductDetails(data.items, 'customer_return');
                    showProductSection();
                    
                    // Destination will be fetched per product when selected
                } else {
                    console.error('Invalid data structure:', data);
                    productDetailsContainer.innerHTML = '<div class="alert alert-danger">Invalid response format. Please try again.</div>';
                }
            })
            .catch(error => {
                console.error('Error fetching invoice items:', error);
                productDetailsContainer.innerHTML = '<div class="alert alert-danger">Error loading invoice items. Please try again.</div>';
            });
    }

    function fetchSupplierBillItems(supplierBillId) {
        // Add loading indicator
        productDetailsContainer.innerHTML = '<div class="text-center"><i class="bi bi-arrow-clockwise spin"></i> Loading products...</div>';
        showProductSection();
        
        fetch(`/returns/ajax/supplier-bill-items/${supplierBillId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.items && Array.isArray(data.items)) {
                    renderProductDetails(data.items, 'vendor_return');
                    showProductSection();
                } else {
                    console.error('Invalid data structure:', data);
                    productDetailsContainer.innerHTML = '<div class="alert alert-danger">Invalid response format. Please try again.</div>';
                }
            })
            .catch(error => {
                console.error('Error fetching supplier bill items:', error);
                productDetailsContainer.innerHTML = '<div class="alert alert-danger">Error loading supplier bill items. Please try again.</div>';
            });
    }

    function fetchStockTransferItems(stockTransferId) {
        // Add loading indicator
        productDetailsContainer.innerHTML = '<div class="text-center"><i class="bi bi-arrow-clockwise spin"></i> Loading products...</div>';
        showProductSection();
        
        fetch(`/returns/ajax/stock-transfer-items/${stockTransferId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.items && Array.isArray(data.items)) {
                    renderProductDetails(data.items, 'retailer_return');
                    showProductSection();
                } else {
                    console.error('Invalid data structure:', data);
                    productDetailsContainer.innerHTML = '<div class="alert alert-danger">Invalid response format. Please try again.</div>';
                }
            })
            .catch(error => {
                console.error('Error fetching stock transfer items:', error);
                let errorMessage = 'Error loading stock transfer items. Please try again.';
                
                // Try to parse error response for more specific messages
                if (error.message && error.message.includes('Only completed stock transfers')) {
                    errorMessage = 'Only completed stock transfers can be used for retailer returns. Please select a different stock transfer.';
                }
                
                // Try to get error from response if it's a fetch error
                if (error.response) {
                    error.response.json().then(data => {
                        if (data.error) {
                            errorMessage = data.error;
                        }
                        productDetailsContainer.innerHTML = `<div class="alert alert-danger">${errorMessage}</div>`;
                    }).catch(() => {
                        productDetailsContainer.innerHTML = `<div class="alert alert-danger">${errorMessage}</div>`;
                    });
                } else {
                    productDetailsContainer.innerHTML = `<div class="alert alert-danger">${errorMessage}</div>`;
                }
            });
    }

    function renderProductDetails(items, returnType) {
        selectedItems = [];
        
        if (items.length === 0) {
            productDetailsContainer.innerHTML = '<div class="alert alert-info">No products available for return.</div>';
            return;
        }

        let html = '<div class="alert alert-info mb-3"><i class="bi bi-info-circle me-2"></i>Select the products you want to return and enter the return quantities.</div>';
        
        html += '<div class="table-responsive">';
        html += '<table class="table table-bordered">';
        html += '<thead class="table-light">';
        html += '<tr>';
        html += '<th style="width: 50px;"><input type="checkbox" id="selectAll" class="form-check-input"></th>';
        html += '<th>Product</th>';
        html += '<th>SKU</th>';
        html += '<th>Original Quantity</th>';
        html += '<th>Already Returned</th>';
        html += '<th>Available for Return</th>';
        html += '<th>Return Quantity</th>';
        html += '<th>Unit Price</th>';
        html += '<th>Return Value</th>';
        html += '<th>Return Destination</th>';
        html += '<th>Validation</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';

        items.forEach((item, index) => {
            if (item.quantity_available_for_return > 0) {
                const unitPrice = returnType === 'customer_return' ? item.unit_price : 
                                 returnType === 'vendor_return' ? item.unit_cost : 0;
                const totalValue = unitPrice * item.quantity_available_for_return;
                
                html += '<tr>';
                html += `<td><input type="checkbox" class="form-check-input product-checkbox" data-index="${index}" data-product-id="${item.product_id}"></td>`;
                html += `<td>${item.product_name}</td>`;
                html += `<td>${item.product_sku}</td>`;
                html += `<td>${returnType === 'customer_return' ? item.quantity_sold : returnType === 'vendor_return' ? item.quantity_purchased : item.quantity_transferred}</td>`;
                html += `<td>${item.already_returned}</td>`;
                html += `<td>${item.quantity_available_for_return}</td>`;
                html += `<td><input type="number" class="form-control return-quantity" data-index="${index}" data-product-id="${item.product_id}" data-max="${item.quantity_available_for_return}" data-unit-price="${unitPrice}" min="1" max="${item.quantity_available_for_return}" value="0" disabled></td>`;
                html += `<td>$${parseFloat(unitPrice).toFixed(2)}</td>`;
                html += `<td><span class="return-value" id="return-value-${index}">$0.00</span></td>`;
                html += `<td><div class="return-destination" id="return-destination-${index}"><small class="text-muted">Loading...</small></div></td>`;
                html += `<td><div class="validation-message" id="validation-${index}"></div></td>`;
                html += '</tr>';
            }
        });

        html += '</tbody>';
        html += '</table>';
        html += '</div>';

        productDetailsContainer.innerHTML = html;

        // Add event listeners for checkboxes and quantity inputs
        document.querySelectorAll('.product-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const index = this.dataset.index;
                const productId = this.dataset.productId;
                const quantityInput = document.querySelector(`.return-quantity[data-index="${index}"]`);
                const validationDiv = document.getElementById(`validation-${index}`);
                const destinationDiv = document.getElementById(`return-destination-${index}`);
                
                quantityInput.disabled = !this.checked;
                if (!this.checked) {
                    quantityInput.value = 0;
                    validationDiv.innerHTML = '';
                    destinationDiv.innerHTML = '<small class="text-muted">Loading...</small>';
                } else {
                    // Fetch destination for this product when selected
                    fetchProductReturnDestination(productId, index);
                }
                updateSelectedItems();
                
                // Force update hidden inputs immediately
                updateHiddenInputs();
                
                // Additional check for button state after checkbox change
                setTimeout(() => {
                    updateSubmitButton();
                    forceEnableButton();
                }, 100);
            });
        });

        document.querySelectorAll('.return-quantity').forEach(input => {
            input.addEventListener('input', function() {
                validateQuantityInput(this);
                updateReturnValue(this);
            });
            input.addEventListener('change', function() {
                validateQuantityInput(this);
                updateReturnValue(this);
                updateSelectedItems();
                
                // Force update hidden inputs immediately
                updateHiddenInputs();
                
                // Additional check for button state after quantity change
                setTimeout(() => {
                    updateSubmitButton();
                    forceEnableButton();
                }, 100);
            });
        });

        // Select all checkbox
        const selectAllCheckbox = document.getElementById('selectAll');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.product-checkbox');
                const quantityInputs = document.querySelectorAll('.return-quantity');
                
                checkboxes.forEach((checkbox, index) => {
                    checkbox.checked = this.checked;
                    quantityInputs[index].disabled = !this.checked;
                    if (!this.checked) {
                        quantityInputs[index].value = 0;
                        document.getElementById(`validation-${index}`).innerHTML = '';
                    }
                });
                
                updateSelectedItems();
            });
        }
    }

    function updateSelectedItems() {
        selectedItems = [];
        let totalQuantity = 0;
        let totalValue = 0;
        let validationErrors = [];
        let validationWarnings = [];
        let validationSuccess = [];
        
        document.querySelectorAll('.product-checkbox:checked').forEach(checkbox => {
            const index = checkbox.dataset.index;
            const productId = checkbox.dataset.productId;
            const quantityInput = document.querySelector(`.return-quantity[data-index="${index}"]`);
            
            if (quantityInput) {
                const quantity = parseInt(quantityInput.value) || 0;
                const unitPrice = parseFloat(quantityInput.dataset.unitPrice) || 0;
                
                if (quantity > 0) {
                    selectedItems.push({
                        product_id: productId,
                        quantity: quantity
                    });
                    totalQuantity += quantity;
                    totalValue += quantity * unitPrice;
                    
                    // Collect validation messages
                    const validationDiv = document.getElementById(`validation-${index}`);
                    if (validationDiv) {
                        const errorElements = validationDiv.querySelectorAll('.text-danger');
                        const warningElements = validationDiv.querySelectorAll('.text-warning');
                        const successElements = validationDiv.querySelectorAll('.text-success');
                        
                        errorElements.forEach(el => validationErrors.push(el.textContent.trim()));
                        warningElements.forEach(el => validationWarnings.push(el.textContent.trim()));
                        successElements.forEach(el => validationSuccess.push(el.textContent.trim()));
                    }
                }
            }
        });
        
        // Update summary (with error handling)
        const totalItemsElement = document.getElementById('totalItemsCount');
        const totalQuantityElement = document.getElementById('totalQuantity');
        const totalReturnValueElement = document.getElementById('totalReturnValue');
        const statusElement = document.getElementById('summaryStatus');
        
        if (totalItemsElement) totalItemsElement.textContent = selectedItems.length;
        if (totalQuantityElement) totalQuantityElement.textContent = totalQuantity;
        if (totalReturnValueElement) totalReturnValueElement.textContent = `$${totalValue.toFixed(2)}`;
        
        // Update status based on validation
        const hasErrors = validationErrors.length > 0;
        
        if (statusElement) {
            if (hasErrors) {
                statusElement.innerHTML = '<span class="badge bg-danger">Validation Errors</span>';
            } else if (selectedItems.length > 0) {
                statusElement.innerHTML = '<span class="badge bg-success">Ready to Submit</span>';
            } else {
                statusElement.innerHTML = '<span class="badge bg-warning">Pending</span>';
            }
        }
        
        // Update validation summary
        updateValidationSummary(validationErrors, validationWarnings, validationSuccess);
        
        updateHiddenInputs();
        updateSubmitButton();
        
        // For customer returns, update button again after a delay to ensure destination is populated
        const currentReturnType = document.querySelector('input[name="return_type"]:checked')?.value;
        if (currentReturnType === 'customer_return' && selectedItems.length > 0) {
            setTimeout(updateSubmitButton, 500); // Give time for destination to be auto-populated
        }
        
        // Show return reason and details sections when valid items are selected
        if (selectedItems.length > 0) {
            showReturnReasonSection();
            showDetailsSection();
        } else {
            hideReturnReasonSection();
            hideDetailsSection();
        }
        
        // Debug logging
        console.log('updateSelectedItems called - selectedItems:', selectedItems);
    }

    function validateQuantityInput(input) {
        const index = input.dataset.index;
        const productId = input.dataset.productId;
        const maxQuantity = parseInt(input.dataset.max);
        const enteredQuantity = parseInt(input.value) || 0;
        const validationDiv = document.getElementById(`validation-${index}`);
        const selectedReturnType = document.querySelector('input[name="return_type"]:checked').value;
        
        // Clear previous validation message
        validationDiv.innerHTML = '';
        
        // Basic validation
        if (enteredQuantity < 0) {
            input.value = 0;
            validationDiv.innerHTML = '<small class="text-danger"><i class="bi bi-exclamation-triangle"></i> Quantity cannot be negative</small>';
            updateSubmitButton();
            return false;
        }
        
        if (enteredQuantity > maxQuantity) {
            input.value = maxQuantity;
            validationDiv.innerHTML = `<small class="text-danger"><i class="bi bi-exclamation-triangle"></i> Cannot exceed available quantity (${maxQuantity})</small>`;
            updateSubmitButton();
            return false;
        }
        
        if (enteredQuantity === 0) {
            validationDiv.innerHTML = '<small class="text-warning"><i class="bi bi-info-circle"></i> Enter quantity to return</small>';
            updateSubmitButton();
            return false;
        }
        
        // Add visual feedback to the input field
        input.classList.remove('is-invalid', 'is-valid');
        if (enteredQuantity > 0 && enteredQuantity <= maxQuantity) {
            input.classList.add('is-valid');
        }
        
        // Server-side validation for more complex rules
        const referenceId = getCurrentReferenceId();
        if (referenceId && enteredQuantity > 0) {
            validateQuantityWithServer(selectedReturnType, referenceId, productId, enteredQuantity, validationDiv);
        } else {
            validationDiv.innerHTML = '<small class="text-success"><i class="bi bi-check-circle"></i> Valid quantity</small>';
            updateSubmitButton();
        }
        
        return true;
    }

    function getCurrentReferenceId() {
        const currentReturnType = document.querySelector('input[name="return_type"]:checked').value;
        
        switch (currentReturnType) {
            case 'customer_return':
                return document.getElementById('invoice_id').value;
            case 'vendor_return':
                return document.getElementById('supplier_bill_id').value;
            case 'retailer_return':
                return document.getElementById('stock_transfer_id').value;
            default:
                return null;
        }
    }

    function validateQuantityWithServer(returnType, referenceId, productId, quantity, validationDiv) {
        // Show loading indicator
        validationDiv.innerHTML = '<small class="text-info"><i class="bi bi-arrow-clockwise spin"></i> Validating...</small>';
        
        // Add timeout to prevent hanging requests
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
        
        fetch('/returns/ajax/validate-quantity', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                return_type: returnType,
                reference_id: referenceId,
                product_id: productId,
                quantity: quantity
            }),
            signal: controller.signal
        })
        .then(response => {
            clearTimeout(timeoutId);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.valid) {
                validationDiv.innerHTML = '<small class="text-success"><i class="bi bi-check-circle"></i> Valid quantity</small>';
            } else {
                // Handle case where data.errors might not be an array
                let errorMessage = 'Validation failed';
                if (data.errors && Array.isArray(data.errors)) {
                    errorMessage = data.errors.join(', ');
                } else if (data.errors && typeof data.errors === 'string') {
                    errorMessage = data.errors;
                } else if (data.error && typeof data.error === 'string') {
                    errorMessage = data.error;
                }
                validationDiv.innerHTML = `<small class="text-danger"><i class="bi bi-exclamation-triangle"></i> ${errorMessage}</small>`;
            }
            updateSubmitButton();
        })
        .catch(error => {
            clearTimeout(timeoutId);
            console.error('Validation error:', error);
            if (error.name === 'AbortError') {
                validationDiv.innerHTML = '<small class="text-warning"><i class="bi bi-exclamation-triangle"></i> Validation timeout</small>';
            } else {
                validationDiv.innerHTML = '<small class="text-warning"><i class="bi bi-exclamation-triangle"></i> Validation error</small>';
            }
        });
    }

    function updateReturnValue(input) {
        const index = input.dataset.index;
        const quantity = parseInt(input.value) || 0;
        const unitPrice = parseFloat(input.dataset.unitPrice) || 0;
        const returnValueSpan = document.getElementById(`return-value-${index}`);
        
        const totalValue = quantity * unitPrice;
        returnValueSpan.textContent = `$${totalValue.toFixed(2)}`;
    }

    function updateHiddenInputs() {
        const hiddenInputsContainer = document.getElementById('hiddenInputs');
        hiddenInputsContainer.innerHTML = '';

        console.log('updateHiddenInputs called with selectedItems:', selectedItems);

        selectedItems.forEach((item, index) => {
            const productInput = `<input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">`;
            const quantityInput = `<input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">`;
            
            hiddenInputsContainer.innerHTML += productInput + quantityInput;
            
            console.log(`Created hidden input for item ${index}:`, {
                product_id: item.product_id,
                quantity: item.quantity
            });
        });
        
        console.log('Hidden inputs container HTML:', hiddenInputsContainer.innerHTML);
    }

    function ensureFormHasItems() {
        console.log('ensureFormHasItems called');
        
        // Recalculate selected items from DOM
        const currentSelectedItems = [];
        document.querySelectorAll('.product-checkbox:checked').forEach(checkbox => {
            const index = checkbox.dataset.index;
            const productId = checkbox.dataset.productId;
            const quantityInput = document.querySelector(`.return-quantity[data-index="${index}"]`);
            
            if (quantityInput && parseInt(quantityInput.value) > 0) {
                currentSelectedItems.push({
                    product_id: productId,
                    quantity: parseInt(quantityInput.value)
                });
            }
        });
        
        console.log('Current selected items from DOM:', currentSelectedItems);
        
        // Update the global selectedItems array
        selectedItems = currentSelectedItems;
        
        // Force update hidden inputs
        updateHiddenInputs();
        
        // Verify hidden inputs exist
        const hiddenInputsContainer = document.getElementById('hiddenInputs');
        const itemInputs = hiddenInputsContainer.querySelectorAll('input[name^="items["]');
        console.log('Hidden item inputs found:', itemInputs.length);
        
        if (itemInputs.length === 0 && selectedItems.length > 0) {
            console.error('No hidden inputs found but items are selected!');
            // Create them manually as a fallback
            selectedItems.forEach((item, index) => {
                const productInput = document.createElement('input');
                productInput.type = 'hidden';
                productInput.name = `items[${index}][product_id]`;
                productInput.value = item.product_id;
                
                const quantityInput = document.createElement('input');
                quantityInput.type = 'hidden';
                quantityInput.name = `items[${index}][quantity]`;
                quantityInput.value = item.quantity;
                
                hiddenInputsContainer.appendChild(productInput);
                hiddenInputsContainer.appendChild(quantityInput);
            });
            console.log('Created fallback hidden inputs');
        }
    }

    function showProductSection() {
        document.getElementById('productSection').style.display = 'block';
        showSummarySection();
    }

    function hideProductSection() {
        document.getElementById('productSection').style.display = 'none';
        hideSummarySection();
        hideDestinationSection();
        hideDetailsSection();
    }

    function showSummarySection() {
        document.getElementById('summarySection').style.display = 'block';
    }

    function hideSummarySection() {
        document.getElementById('summarySection').style.display = 'none';
    }

    // Destination is now handled per product in the table

    function showReturnReasonSection() {
        document.getElementById('returnReasonSection').style.display = 'block';
    }

    function hideReturnReasonSection() {
        document.getElementById('returnReasonSection').style.display = 'none';
    }

    function showDetailsSection() {
        document.getElementById('detailsSection').style.display = 'block';
    }

    function hideDetailsSection() {
        document.getElementById('detailsSection').style.display = 'none';
    }

    function hideDestinationSection() {
        // Destination is now handled per product in the table, so this function is a no-op
        // but we keep it to prevent JavaScript errors
        console.log('hideDestinationSection called - destination is now handled per product');
    }

    function forceEnableButton() {
        // Force enable button function (for testing and fallback)
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            const currentReturnType = document.querySelector('input[name="return_type"]:checked')?.value;
            const selectedCheckboxes = document.querySelectorAll('.product-checkbox:checked');
            
            if (currentReturnType && selectedCheckboxes.length > 0) {
                // Check if any selected items have valid quantities
                let hasValidSelectedItems = false;
                selectedCheckboxes.forEach(checkbox => {
                    const index = checkbox.dataset.index;
                    const quantityInput = document.querySelector(`.return-quantity[data-index="${index}"]`);
                    if (quantityInput && parseInt(quantityInput.value) > 0) {
                        hasValidSelectedItems = true;
                    }
                });
                
                if (hasValidSelectedItems) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i> Create Return';
                    submitBtn.className = 'btn btn-primary btn-lg px-4 fw-semibold';
                    console.log('Button force enabled');
                }
            }
        }
    }

    function updateSubmitButton() {
        const currentReturnType = document.querySelector('input[name="return_type"]:checked')?.value;
        const hasValidationErrors = document.querySelectorAll('.validation-message .text-danger').length > 0;
        const selectedCheckboxes = document.querySelectorAll('.product-checkbox:checked');
        let hasInvalidSelectedItems = false;
        
        // Recalculate selectedItems to ensure it's accurate
        const currentSelectedItems = [];
        selectedCheckboxes.forEach(checkbox => {
            const index = checkbox.dataset.index;
            const productId = checkbox.dataset.productId;
            const quantityInput = document.querySelector(`.return-quantity[data-index="${index}"]`);
            
            if (quantityInput && parseInt(quantityInput.value) > 0) {
                currentSelectedItems.push({
                    product_id: productId,
                    quantity: parseInt(quantityInput.value)
                });
            }
        });
        
        // Use the recalculated selectedItems for validation
        const effectiveSelectedItems = currentSelectedItems.length > 0 ? currentSelectedItems : selectedItems;
        
        // Debug logging
        console.log('updateSubmitButton called:', {
            returnType: currentReturnType,
            selectedItemsLength: selectedItems.length,
            currentSelectedItemsLength: currentSelectedItems.length,
            effectiveSelectedItemsLength: effectiveSelectedItems.length,
            hasValidationErrors: hasValidationErrors
        });

        selectedCheckboxes.forEach(checkbox => {
            const index = checkbox.dataset.index;
            const quantityInput = document.querySelector(`.return-quantity[data-index="${index}"]`);
            if (quantityInput) {
                const quantity = parseInt(quantityInput.value) || 0;
                const maxQuantity = parseInt(quantityInput.dataset.max) || 0;
                if (quantity <= 0 || quantity > maxQuantity) {
                    hasInvalidSelectedItems = true;
                }
            }
        });

        // Check return reason
        const returnReason = document.getElementById('return_reason')?.value;
        
        // Build validation message
        let validationMessage = '';
        let isFormValid = true;
        
        console.log('Validation checks:', {
            returnType: currentReturnType,
            selectedItemsLength: selectedItems.length,
            effectiveSelectedItemsLength: effectiveSelectedItems.length,
            hasValidationErrors: hasValidationErrors,
            hasInvalidSelectedItems: hasInvalidSelectedItems,
            returnReason: returnReason
        });
        
        if (!currentReturnType) {
            validationMessage = 'Please select a return type';
            isFormValid = false;
            console.log('Failed: No return type selected');
        } else if (effectiveSelectedItems.length === 0) {
            validationMessage = 'Please select at least one product to return';
            isFormValid = false;
            console.log('Failed: No items selected');
        } else if (!returnReason) {
            validationMessage = 'Please select a return reason';
            isFormValid = false;
            console.log('Failed: No return reason selected');
        } else if (returnReason === 'other') {
            const otherDetails = document.getElementById('other_reason_details')?.value.trim();
            if (!otherDetails) {
                validationMessage = 'Please provide additional details for the "Other" return reason';
                isFormValid = false;
                console.log('Failed: No details for "Other" reason');
            }
        } else if (hasValidationErrors) {
            validationMessage = 'Please fix validation errors in the form';
            isFormValid = false;
            console.log('Failed: Validation errors');
        } else if (hasInvalidSelectedItems) {
            validationMessage = 'Please fix quantity validation errors';
            isFormValid = false;
            console.log('Failed: Invalid items');
        } else {
            console.log('All validations passed - form is valid');
        }

        // Show/hide validation message
        const validationDiv = document.getElementById('validationMessage');
        const validationText = document.getElementById('validationText');
        
        if (!isFormValid) {
            validationText.textContent = validationMessage;
            validationDiv.style.display = 'block';
        } else {
            validationDiv.style.display = 'none';
        }

        // Enable/disable button and update styling
        submitBtn.disabled = !isFormValid;
        
        if (isFormValid) {
            submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i> Create Return';
            submitBtn.className = 'btn btn-primary btn-lg px-4 fw-semibold';
            submitBtn.title = 'All required fields are completed. Click to create return.';
            console.log('Button enabled - form is valid');
        } else {
            submitBtn.innerHTML = '<i class="bi bi-lock me-2"></i> Create Return';
            submitBtn.className = 'btn btn-primary btn-lg px-4 fw-semibold';
            submitBtn.title = validationMessage;
            console.log('Button disabled - validation failed:', validationMessage);
        }
    }

    function updateValidationSummary(errors, warnings, successes) {
        const validationSummary = document.getElementById('validationSummary');
        const validationSummaryContent = document.getElementById('validationSummaryContent');
        
        if (errors.length === 0 && warnings.length === 0 && successes.length === 0) {
            validationSummary.style.display = 'none';
            return;
        }
        
        let html = '';
        
        if (errors.length > 0) {
            html += '<div class="alert alert-danger border-0 py-2 mb-2">';
            html += '<i class="bi bi-exclamation-triangle me-2"></i><strong>Errors:</strong>';
            html += '<ul class="mb-0 mt-1">';
            errors.forEach(error => {
                html += `<li>${error}</li>`;
            });
            html += '</ul></div>';
        }
        
        if (warnings.length > 0) {
            html += '<div class="alert alert-warning border-0 py-2 mb-2">';
            html += '<i class="bi bi-exclamation-triangle me-2"></i><strong>Warnings:</strong>';
            html += '<ul class="mb-0 mt-1">';
            warnings.forEach(warning => {
                html += `<li>${warning}</li>`;
            });
            html += '</ul></div>';
        }
        
        if (successes.length > 0) {
            html += '<div class="alert alert-success border-0 py-2 mb-2">';
            html += '<i class="bi bi-check-circle me-2"></i><strong>Validations:</strong>';
            html += '<ul class="mb-0 mt-1">';
            successes.forEach(success => {
                html += `<li>${success}</li>`;
            });
            html += '</ul></div>';
        }
        
        validationSummaryContent.innerHTML = html;
        validationSummary.style.display = 'block';
    }

    function fetchProductReturnDestination(productId, index) {
        console.log('fetchProductReturnDestination called with productId:', productId, 'index:', index);
        
        const destinationDiv = document.getElementById(`return-destination-${index}`);
        destinationDiv.innerHTML = '<small class="text-info"><i class="bi bi-arrow-clockwise spin me-1"></i>Loading destination...</small>';
        
        // Get the current reference ID based on return type
        const currentReturnType = document.querySelector('input[name="return_type"]:checked')?.value;
        let referenceId = null;
        
        switch (currentReturnType) {
            case 'customer_return':
                referenceId = document.getElementById('invoice_id').value;
                break;
            case 'vendor_return':
                referenceId = document.getElementById('supplier_bill_id').value;
                break;
            case 'retailer_return':
                referenceId = document.getElementById('stock_transfer_id').value;
                break;
        }
        
        console.log('Request parameters:', {
            returnType: currentReturnType,
            referenceId: referenceId,
            productId: productId
        });
        
        if (!referenceId) {
            destinationDiv.innerHTML = '<small class="text-danger">No reference document selected</small>';
            return;
        }
        
        // Add timeout to prevent hanging requests
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
        
        const fetchUrl = `/returns/ajax/product-return-destination/${referenceId}/${productId}?return_type=${currentReturnType}`;
        console.log('Fetching URL:', fetchUrl);
        
        fetch(fetchUrl, {
            signal: controller.signal
        })
            .then(response => {
                clearTimeout(timeoutId);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Product return destination response:', data);
                
                if (data.return_destination) {
                    const destination = data.return_destination;
                    
                    console.log('Destination data received:', destination);
                    
                    // Update the destination display
                    destinationDiv.innerHTML = `
                        <div class="d-flex align-items-center">
                            <i class="bi bi-geo-alt text-success me-2"></i>
                            <div>
                                <strong>${destination.name}</strong>
                                <br><small class="text-muted">${destination.type_name}</small>
                            </div>
                        </div>
                    `;
                    
                    // Set the global destination if this is the first selected item
                    const selectedCheckboxes = document.querySelectorAll('.product-checkbox:checked');
                    if (selectedCheckboxes.length === 1 && destination && destination.id) {
                        document.getElementById('return_location_id').value = destination.id;
                        
                        // Convert full class name to simple location type
                        let locationType = destination.type;
                        if (locationType.includes('Vendor')) {
                            locationType = 'vendor';
                        } else if (locationType.includes('Warehouse')) {
                            locationType = 'warehouse';
                        } else if (locationType.includes('Retailer')) {
                            locationType = 'retailer';
                        }
                        
                        document.getElementById('return_location_type').value = locationType;
                        console.log('Global destination set:', destination);
                    }
                    
                    // Update button state
                    setTimeout(() => {
                        updateSubmitButton();
                    }, 100);
                } else if (data.error) {
                    console.error('Server returned error:', data.error);
                    destinationDiv.innerHTML = `<small class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${data.error}</small>`;
                } else {
                    console.error('Unexpected response format:', data);
                    throw new Error('No return destination data received');
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                console.error('Error fetching product return destination:', error);
                
                if (error.name === 'AbortError') {
                    console.log('Request was aborted due to timeout');
                }
                
                destinationDiv.innerHTML = '<small class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Error loading destination</small>';
            });
    }



    // Add event listeners for form validation (but don't override the main functionality)
    returnTypeRadios.forEach(radio => {
        // Only add updateSubmitButton if it's not already added
        if (!radio.hasAttribute('data-submit-button-listener')) {
            radio.addEventListener('change', function() {
                updateSubmitButton();
            });
            radio.setAttribute('data-submit-button-listener', 'true');
        }
    });
    
    // Add additional event listeners to ensure button updates
    document.addEventListener('change', function(e) {
        if (e.target.matches('.product-checkbox, .return-quantity')) {
            setTimeout(updateSubmitButton, 100); // Small delay to ensure DOM updates
        }
    });
    
    // Periodic button state check
    setInterval(function() {
        const currentReturnType = document.querySelector('input[name="return_type"]:checked')?.value;
        if (currentReturnType) {
            const periodicSelectedCheckboxes = document.querySelectorAll('.product-checkbox:checked');
            
            // Check if any selected items have valid quantities
            let hasValidSelectedItems = false;
            periodicSelectedCheckboxes.forEach(checkbox => {
                const index = checkbox.dataset.index;
                const quantityInput = document.querySelector(`.return-quantity[data-index="${index}"]`);
                if (quantityInput && parseInt(quantityInput.value) > 0) {
                    hasValidSelectedItems = true;
                }
            });
            
            // If we have selected items with valid quantities, ensure button is enabled
            if (hasValidSelectedItems) {
                const submitBtn = document.getElementById('submitBtn');
                if (submitBtn && submitBtn.disabled) {
                    console.log('Periodic check: Enabling button - conditions met');
                    updateSubmitButton();
                }
            }
        }
    }, 1000); // Check every 1 second for more responsiveness

    // Add form submission validation
    document.getElementById('returnForm').addEventListener('submit', function(e) {
        console.log('Form submission attempted');
        
        // Force update hidden inputs before form submission
        updateHiddenInputs();
        
        // Set reference_id based on return type
        const currentReturnType = document.querySelector('input[name="return_type"]:checked')?.value;
        let referenceId = null;
        
        switch (currentReturnType) {
            case 'customer_return':
                referenceId = document.getElementById('invoice_id').value;
                break;
            case 'vendor_return':
                referenceId = document.getElementById('supplier_bill_id').value;
                break;
            case 'retailer_return':
                referenceId = document.getElementById('stock_transfer_id').value;
                break;
        }
        
        if (referenceId) {
            document.getElementById('reference_id').value = referenceId;
        }
        
        // Set return_location_type based on the first selected item's destination
        const selectedCheckboxes = document.querySelectorAll('.product-checkbox:checked');
        if (selectedCheckboxes.length > 0) {
            const firstCheckbox = selectedCheckboxes[0];
            const index = firstCheckbox.dataset.index;
            const destinationDiv = document.getElementById(`return-destination-${index}`);
            
            // Try to extract location type from the destination display
            if (destinationDiv) {
                const locationTypeElement = destinationDiv.querySelector('.text-muted');
                if (locationTypeElement) {
                    const locationTypeText = locationTypeElement.textContent.toLowerCase();
                    
                    if (locationTypeText.includes('warehouse')) {
                        document.getElementById('return_location_type').value = 'warehouse';
                    } else if (locationTypeText.includes('retailer')) {
                        document.getElementById('return_location_type').value = 'retailer';
                    } else if (locationTypeText.includes('vendor')) {
                        document.getElementById('return_location_type').value = 'vendor';
                    }
                }
            }
        }
        
        // Ensure hidden inputs are created and included in form
        ensureFormHasItems();
        
        // Force update submit button state before form submission
        updateSubmitButton();
        
        // Check if return type is selected
        const selectedReturnType = document.querySelector('input[name="return_type"]:checked')?.value;
        if (!selectedReturnType) {
            e.preventDefault();
            showValidationMessage('Please select a return type before proceeding.');
            return false;
        }
        
        // Ensure hidden inputs are created and included in form
        ensureFormHasItems();
        
        // Check if at least one product is selected (check both global variable and DOM)
        const submissionSelectedCheckboxes = document.querySelectorAll('.product-checkbox:checked');
        const hasSelectedItems = selectedItems.length > 0 || submissionSelectedCheckboxes.length > 0;
        
        if (!hasSelectedItems) {
            e.preventDefault();
            showValidationMessage('Please select at least one product to return.');
            return false;
        }
        
        // Additional check: ensure hidden inputs exist
        const hiddenInputsContainer = document.getElementById('hiddenInputs');
        const itemInputs = hiddenInputsContainer.querySelectorAll('input[name^="items["]');
        
        if (itemInputs.length === 0) {
            e.preventDefault();
            showValidationMessage('No items data found. Please try selecting products again.');
            return false;
        }
        
        // Check for validation errors before submission
        const validationErrors = document.querySelectorAll('.validation-message .text-danger');
        if (validationErrors.length > 0) {
            e.preventDefault();
            showValidationMessage('Please fix all validation errors before submitting the return.');
            return false;
        }
        
        // Check if return reason is selected
        const returnReason = document.getElementById('return_reason').value;
        if (!returnReason) {
            e.preventDefault();
            showValidationMessage('Please select a return reason before submitting.');
            return false;
        }
        
        // Check if "Other" reason has details
        if (returnReason === 'other') {
            const otherDetails = document.getElementById('other_reason_details').value.trim();
            if (!otherDetails) {
                e.preventDefault();
                showValidationMessage('Please provide additional details for the "Other" return reason.');
                return false;
            }
        }
        
        // Destination is now handled per product, so no global destination validation needed
        
        // Check if any selected items have invalid quantities
        const formSelectedCheckboxes = document.querySelectorAll('.product-checkbox:checked');
        let invalidItems = [];
        
        formSelectedCheckboxes.forEach(checkbox => {
            const index = checkbox.dataset.index;
            const quantityInput = document.querySelector(`.return-quantity[data-index="${index}"]`);
            if (quantityInput) {
                const quantity = parseInt(quantityInput.value) || 0;
                const maxQuantity = parseInt(quantityInput.dataset.max) || 0;
                
                if (quantity <= 0) {
                    invalidItems.push('Some items have zero or invalid quantities');
                } else if (quantity > maxQuantity) {
                    invalidItems.push('Some items exceed the maximum allowed quantity');
                }
            }
        });
        
        if (invalidItems.length > 0) {
            e.preventDefault();
            showValidationMessage('Please fix the following issues:\n' + invalidItems.join('\n'));
            return false;
        }
        
        // If all validations pass, proceed
        console.log('Form submission allowed - proceeding...');
        return true;
    });

    // Helper function to show validation messages
    function showValidationMessage(message) {
        const validationDiv = document.getElementById('validationMessage');
        const validationText = document.getElementById('validationText');
        
        validationText.textContent = message;
        validationDiv.style.display = 'block';
        
        // Scroll to validation message
        validationDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Hide message after 5 seconds
        setTimeout(() => {
            validationDiv.style.display = 'none';
        }, 5000);
    }
    


    // Return reason handling
    document.addEventListener('DOMContentLoaded', function() {
        const returnReasonSelect = document.getElementById('return_reason');
        const otherReasonRow = document.getElementById('otherReasonRow');
        
        if (returnReasonSelect) {
            returnReasonSelect.addEventListener('change', function() {
                if (this.value === 'other') {
                    otherReasonRow.style.display = 'block';
                    // Make the additional details field required when "Other" is selected
                    const otherDetailsField = document.getElementById('other_reason_details');
                    if (otherDetailsField) {
                        otherDetailsField.required = true;
                    }
                } else {
                    otherReasonRow.style.display = 'none';
                    // Remove required attribute when not "Other"
                    const otherDetailsField = document.getElementById('other_reason_details');
                    if (otherDetailsField) {
                        otherDetailsField.required = false;
                        otherDetailsField.value = '';
                    }
                }
                updateSubmitButton();
            });
        }
    });

    // Manual trigger function for testing
    window.triggerReturnTypeChange = function(returnType) {
        console.log('Manually triggering return type change to:', returnType);
        const radio = document.querySelector(`input[name="return_type"][value="${returnType}"]`);
        if (radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
            console.log('Event dispatched for:', returnType);
        } else {
            console.error('Radio button not found for:', returnType);
        }
    };
    
    // Test function to manually show sections
    window.testShowSection = function(sectionId) {
        console.log('Testing show section:', sectionId);
        const section = document.getElementById(sectionId);
        if (section) {
            section.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important;';
            console.log('Section should be visible now');
        } else {
            console.error('Section not found:', sectionId);
        }
    };

    // Comprehensive debugging function
    window.debugReturnForm = function() {
        console.log('=== RETURN FORM DEBUG ===');
        
        // Check return type
        const currentReturnType = document.querySelector('input[name="return_type"]:checked')?.value;
        console.log('Return type:', currentReturnType);
        
        // Check source selection
        const customerId = document.getElementById('customer_id')?.value;
        const vendorId = document.getElementById('vendor_id')?.value;
        const retailerId = document.getElementById('retailer_id')?.value;
        console.log('Source IDs:', { customerId, vendorId, retailerId });
        
        // Check reference selection
        const invoiceId = document.getElementById('invoice_id')?.value;
        const supplierBillId = document.getElementById('supplier_bill_id')?.value;
        const stockTransferId = document.getElementById('stock_transfer_id')?.value;
        console.log('Reference IDs:', { invoiceId, supplierBillId, stockTransferId });
        
        // Destination is now handled per product
        
        // Check selected items
        const selectedCheckboxes = document.querySelectorAll('.product-checkbox:checked');
        const validSelectedItems = [];
        selectedCheckboxes.forEach(checkbox => {
            const index = checkbox.dataset.index;
            const quantityInput = document.querySelector(`.return-quantity[data-index="${index}"]`);
            if (quantityInput && parseInt(quantityInput.value) > 0) {
                validSelectedItems.push({
                    productId: checkbox.dataset.productId,
                    quantity: parseInt(quantityInput.value)
                });
            }
        });
        console.log('Valid selected items:', validSelectedItems);
        
        // Check hidden inputs
        const hiddenInputsContainer = document.getElementById('hiddenInputs');
        const itemInputs = hiddenInputsContainer.querySelectorAll('input[name^="items["]');
        console.log('Hidden item inputs:', itemInputs.length);
        itemInputs.forEach((input, index) => {
            console.log(`Hidden input ${index}:`, input.name, input.value);
        });
        
        // Check button state
        const submitBtn = document.getElementById('submitBtn');
        console.log('Submit button:', {
            disabled: submitBtn?.disabled,
            text: submitBtn?.innerHTML,
            title: submitBtn?.title
        });
        
        // Check validation message
        const validationDiv = document.getElementById('validationMessage');
        console.log('Validation message visible:', validationDiv?.style.display !== 'none');
        
        console.log('Conditions check:', {
            hasReturnType: !!currentReturnType,
            hasValidSelectedItems: validSelectedItems.length > 0,
            hasHiddenInputs: itemInputs.length > 0,
            allConditionsMet: currentReturnType && validSelectedItems.length > 0 && itemInputs.length > 0
        });
        
        console.log('=== END DEBUG ===');
    };
    

    
    // Fix for browser extension errors
    window.addEventListener('error', function(e) {
        if (e.message && e.message.includes('message channel closed')) {
            console.log('Browser extension error detected, ignoring...');
            e.preventDefault();
            return false;
        }
    });
    
    // Fix for unhandled promise rejections
    window.addEventListener('unhandledrejection', function(e) {
        if (e.reason && e.reason.message && e.reason.message.includes('message channel closed')) {
            console.log('Browser extension promise rejection detected, ignoring...');
            e.preventDefault();
            return false;
        }
    });
});
</script>

<style>
:root {
    --primary: #2c6e49;
    --primary-light: #4c956c;
    --primary-dark: #1a472a;
    --secondary: #2a9d8f;
    --accent: #e9c46a;
    --light-bg: #faf9f8;
    --light-panel: #fff;
    --dark-text: #2d3142;
    --medium-text: #4f5d75;
    --light-text: #fafafa;
    --success: #52b788;
    --warning: #f8961e;
    --danger: #e76f51;
    --info: #3d5a80;
    --border-color: #e9ecef;
}

.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.validation-message {
    min-height: 20px;
    font-size: 0.875rem;
}

.validation-message small {
    display: block;
    margin-top: 2px;
}

.return-quantity:invalid {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.return-quantity:valid {
    border-color: #198754;
    box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
}

.table th {
    white-space: nowrap;
    vertical-align: middle;
}

.table td {
    vertical-align: middle;
}

/* Enhanced button styling */
.btn-lg {
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
}

.btn-success {
    background: linear-gradient(135deg, #198754 0%, #20c997 100%);
    border: none;
    box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3);
}

.btn-success:hover {
    background: linear-gradient(135deg, #157347 0%, #1aa085 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(25, 135, 84, 0.4);
}

.btn-outline-secondary {
    border-width: 2px;
    font-weight: 600;
}

.btn-outline-secondary:hover {
    background-color: #6c757d;
    border-color: #6c757d;
    transform: translateY(-1px);
}

/* Action buttons section styling */
.return-action-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #dee2e6;
}

.return-action-card .bg-primary {
    background-color: var(--primary) !important;
}

.return-action-card .text-primary {
    color: var(--primary) !important;
}

.btn-outline-secondary {
    border-width: 2px;
    font-weight: 600;
    transition: all 0.3s ease;
    color: var(--primary);
    border-color: var(--primary);
    background-color: transparent;
}

.btn-outline-secondary:hover {
    background-color: var(--primary);
    border-color: var(--primary);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(44, 110, 73, 0.3);
}

.btn-outline-secondary:focus {
    background-color: var(--primary);
    border-color: var(--primary);
    color: white;
    box-shadow: 0 0 0 0.2rem rgba(44, 110, 73, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    color: white;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(44, 110, 73, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--primary-dark) 0%, #1a472a 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(44, 110, 73, 0.4);
}

.btn-primary:disabled {
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
    color: rgba(255, 255, 255, 0.6);
    transform: none;
    box-shadow: none;
    cursor: not-allowed;
}

.btn-primary:disabled:hover {
    transform: none;
    box-shadow: none;
}

/* Validation summary styling */
#validationSummary .alert {
    border-radius: 8px;
    font-size: 0.875rem;
}

#validationSummary .alert ul {
    padding-left: 1.5rem;
}

#validationSummary .alert li {
    margin-bottom: 0.25rem;
}

/* Destination help text styling */
#destinationHelpText {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: 0.5rem;
}

#destinationHelpText strong {
    color: #198754;
}

/* Validation message styling */
#validationMessage {
    animation: slideIn 0.3s ease-out;
    border-radius: 8px;
    padding: 12px 16px;
    background: rgba(220, 53, 69, 0.1);
    border-left: 4px solid #dc3545;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Card styling improvements */
.card {
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
}

.card-header {
    border-radius: 12px 12px 0 0 !important;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

/* Form control improvements */
.form-control:focus, .form-select:focus {
    border-color: #198754;
    box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
}

/* Responsive improvements */
@media (max-width: 768px) {
    .btn-lg {
        font-size: 1rem;
        padding: 0.75rem 1.5rem;
    }
    
    #validationMessage {
        margin: 10px 0;
        font-size: 0.9rem;
    }
    
    .return-action-card .row {
        flex-direction: column;
    }
    
    .return-action-card .col-lg-8,
    .return-action-card .col-lg-4 {
        width: 100%;
    }
    
    .return-action-card .d-flex.justify-content-end {
        justify-content: center !important;
    }
    
    .return-action-card .d-flex.gap-3 {
        flex-direction: column;
        gap: 1rem !important;
    }
    
    .return-action-card .btn {
        width: 100%;
    }
}

/* Enhanced action card styling */
.return-action-card {
    position: relative;
    overflow: hidden;
}

.return-action-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 50%, var(--accent) 100%);
}

.return-action-card .bg-primary.bg-opacity-10 {
    background-color: rgba(44, 110, 73, 0.1) !important;
}

.return-action-card .text-primary {
    color: var(--primary) !important;
}

/* Button hover animations */
.btn-lg {
    position: relative;
    overflow: hidden;
}

.btn-lg::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
    transform: translate(-50%, -50%);
    transition: width 0.6s ease, height 0.6s ease;
    pointer-events: none;
}

.btn-lg:hover::before {
    width: 300px;
    height: 300px;
}

/* Return destination styling in table */
.return-destination {
    min-height: 40px;
    display: flex;
    align-items: center;
}

.return-destination .d-flex {
    align-items: center;
}

.return-destination .text-success {
    color: var(--success) !important;
}

.return-destination .text-muted {
    color: #6c757d;
}



/* Return reason section styling */
#returnReasonSection .form-select {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

#returnReasonSection .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(44, 110, 73, 0.25);
}

#returnReasonSection .form-control {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

#returnReasonSection .form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(44, 110, 73, 0.25);
}

#otherReasonRow {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
@endpush 
<!-- Top Row: Vendors to Warehouses -->
<div class="row text-center mb-4">
    <!-- Vendors -->
    <div class="col-md-3 offset-md-2">
        <div class="flow-step">
            <div class="flow-icon bg-primary">
                <i class="bi bi-building text-white"></i>
            </div>
            <h6 class="mt-2">Vendors</h6>
            <small class="text-muted">Supply Products</small>
        </div>
    </div>
    
    <!-- Arrow -->
    <div class="col-md-2 d-flex align-items-center justify-content-center">
        <i class="bi bi-arrow-right text-primary fs-3"></i>
    </div>
    
    <!-- Warehouses -->
    <div class="col-md-3">
        <div class="flow-step">
            <div class="flow-icon bg-success">
                <i class="bi bi-house-fill text-white"></i>
            </div>
            <h6 class="mt-2">Warehouses</h6>
            <small class="text-muted">{{ $warehouses->count() }} Active</small>
        </div>
    </div>
</div>

<!-- Flow Description -->
<div class="row mb-4">
    <div class="col-12 text-center">
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Stock Flow:</strong> Products are supplied from vendors to warehouses. From warehouses, products can be delivered directly to customers (online platform) or distributed to retailers for local customer delivery.
        </div>
    </div>
</div>

<!-- Bottom Row: Distribution from Warehouses -->
<div class="row text-center">
    <!-- Online Direct Sales -->
    <div class="col-md-3">
        <div class="flow-step">
            <div class="flow-icon bg-info">
                <i class="bi bi-globe text-white"></i>
            </div>
            <h6 class="mt-2">Online Platform</h6>
            <small class="text-muted">Direct to Customers</small>
        </div>
    </div>

    <!-- Arrow pointing up-right to Warehouses -->
    <div class="col-md-2 d-flex align-items-center justify-content-center">
        <div class="d-flex flex-column align-items-center">
            <i class="bi bi-arrow-up-right text-info fs-4"></i>
            <small class="text-muted mt-1">Direct</small>
        </div>
    </div>

    <!-- Central Warehouse (reference point) -->
    <div class="col-md-2 d-flex align-items-center justify-content-center">
        <div class="text-center">
            <div class="badge bg-success fs-6 p-2">
                <i class="bi bi-house-fill me-1"></i>Warehouse Hub
            </div>
        </div>
    </div>

    <!-- Arrow pointing down-right to Retailers -->
    <div class="col-md-2 d-flex align-items-center justify-content-center">
        <div class="d-flex flex-column align-items-center">
            <i class="bi bi-arrow-down-right text-warning fs-4"></i>
            <small class="text-muted mt-1">Distribute</small>
        </div>
    </div>

    <!-- Retail Distribution -->
    <div class="col-md-3">
        <div class="flow-step">
            <div class="flow-icon bg-warning">
                <i class="bi bi-shop text-white"></i>
            </div>
            <h6 class="mt-2">Retailers</h6>
            <small class="text-muted">{{ $retailers->count() }} Active</small>
        </div>
    </div>
</div>

<!-- Final Customer Delivery -->
<div class="row text-center mt-4">
    <div class="col-md-3">
        <!-- Spacer for alignment -->
    </div>
    
    <div class="col-md-2 d-flex align-items-center justify-content-center">
        <!-- Direct delivery arrow -->
    </div>

    <div class="col-md-2 d-flex align-items-center justify-content-center">
        <div class="flow-step">
            <div class="flow-icon bg-danger">
                <i class="bi bi-people-fill text-white"></i>
            </div>
            <h6 class="mt-2">Customers</h6>
            <small class="text-muted">End Recipients</small>
        </div>
    </div>

    <div class="col-md-2 d-flex align-items-center justify-content-center">
        <i class="bi bi-arrow-up text-warning fs-3"></i>
    </div>

    <div class="col-md-3">
        <!-- Spacer for alignment -->
    </div>
</div>

<!-- Distribution Methods Summary -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card border-info">
            <div class="card-body text-center">
                <i class="bi bi-globe text-info fs-1 mb-2"></i>
                <h6 class="text-info">Online Platform Distribution</h6>
                <p class="small text-muted mb-0">Products delivered directly from warehouse to customers via online orders</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-warning">
            <div class="card-body text-center">
                <i class="bi bi-shop text-warning fs-1 mb-2"></i>
                <h6 class="text-warning">Retail Distribution</h6>
                <p class="small text-muted mb-0">Products delivered from warehouse to retailers, then from retailers to customers</p>
            </div>
        </div>
    </div>
</div> 
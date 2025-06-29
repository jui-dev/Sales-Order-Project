@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12 mb-4 page-heading">
        <h1 class="h3">Welcome to Sales Order System</h1>
        <p>Manage your business operations efficiently</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted">Products</h6>
                        <h3>{{ App\Models\Product::count() }}</h3>
                    </div>
                    <div class="bg-subtle p-3 rounded-3">
                        <i class="bi bi-box fs-4 text-primary-custom"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted">Customers</h6>
                        <h3>{{ App\Models\Customer::count() }}</h3>
                    </div>
                    <div class="bg-subtle p-3 rounded-3">
                        <i class="bi bi-people fs-4 text-primary-custom"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted">Orders</h6>
                        <h3>{{ App\Models\Order::count() }}</h3>
                    </div>
                    <div class="bg-subtle p-3 rounded-3">
                        <i class="bi bi-cart fs-4 text-primary-custom"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted">Supplies</h6>
                        <h3>{{ App\Models\Supply::count() }}</h3>
                    </div>
                    <div class="bg-subtle p-3 rounded-3">
                        <i class="bi bi-truck fs-4 text-primary-custom"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-stack me-2"></i>Quick Actions
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card h-100 border-0 bg-subtle">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-box me-2 text-primary-custom"></i>Products
                                </h5>
                                <p class="card-text">Add, edit, or remove products from your inventory.</p>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('products.index') }}" class="btn btn-sm btn-primary">View Products</a>
                                    <a href="{{ route('products.create') }}" class="btn btn-sm btn-accent">Add Product</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 border-0 bg-subtle">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-people me-2 text-primary-custom"></i>Customers
                                </h5>
                                <p class="card-text">Add, edit, or remove customer information.</p>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('customers.index') }}" class="btn btn-sm btn-primary">View Customers</a>
                                    <a href="{{ route('customers.create') }}" class="btn btn-sm btn-accent">Add Customer</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 border-0 bg-subtle">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-building me-2 text-primary-custom"></i>Vendors
                                </h5>
                                <p class="card-text">Add, edit, or remove vendor information.</p>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('vendors.index') }}" class="btn btn-sm btn-primary">View Vendors</a>
                                    <a href="{{ route('vendors.create') }}" class="btn btn-sm btn-accent">Add Vendor</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 border-0 bg-subtle">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-cart me-2 text-primary-custom"></i>Orders
                                </h5>
                                <p class="card-text">Create new orders, view orders, and track status.</p>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('orders.index') }}" class="btn btn-sm btn-primary">View Orders</a>
                                    <a href="{{ route('orders.create') }}" class="btn btn-sm btn-accent">New Order</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 border-0 bg-subtle">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-arrow-left-right me-2 text-primary-custom"></i>Stock Transfers
                                </h5>
                                <p class="card-text">Transfer stock from warehouse to retailer locations.</p>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('stock-transfers.warehouse-to-retailer') }}" class="btn btn-sm btn-primary">View Transfers</a>
                                    <a href="{{ route('stock-transfers.warehouse-to-retailer.create') }}" class="btn btn-sm btn-accent">New Transfer</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 border-0 bg-subtle">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-inbox me-2 text-primary-custom"></i>Warehouse Receiving
                                </h5>
                                <p class="card-text">Process vendor supplies and manage warehouse receiving.</p>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('warehouse.receiving.index') }}" class="btn btn-sm btn-primary">View Receiving</a>
                                    <a href="{{ route('warehouse.receiving.pending') }}" class="btn btn-sm btn-accent">Pending Tasks</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center">
                <i class="bi bi-truck me-2"></i>
                <span>Supply Management</span>
            </div>
            <div class="card-body d-flex flex-column">
                <p class="card-text flex-grow-1">Record new supplies from vendors and manage your inventory levels efficiently. Keep track of incoming supplies and stock levels.</p>
                <div class="mt-3">
                    <a href="{{ route('supplies.index') }}" class="btn btn-primary">
                        <i class="bi bi-list me-1"></i>View Supplies
                    </a>
                    <a href="{{ route('supplies.create') }}" class="btn btn-accent ms-2">
                        <i class="bi bi-plus me-1"></i>New Supply
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card h-100">
            <div class="card-header d-flex align-items-center">
                <i class="bi bi-file-earmark-bar-graph me-2"></i>
                <span>Business Reports</span>
            </div>
            <div class="card-body d-flex flex-column">
                <p class="card-text flex-grow-1">Generate and analyze business reports to track your profitability and performance metrics.</p>
                <div class="mt-3">
                    <a href="{{ route('reports.daily-profit') }}" class="btn btn-primary">
                        <i class="bi bi-graph-up-arrow me-1"></i>Daily Profit Report
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
@extends('layouts.app')

@section('content')
<!-- Hero Section with Product Header -->
<div class="product-hero mb-4">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <div class="d-flex align-items-center mb-3">
                <div class="product-icon me-3">
                    <i class="bi bi-box-seam fs-1 text-primary"></i>
                </div>
                <div>
                    <h1 class="mb-1 fw-bold text-dark">{{ $product->name }}</h1>
                    <p class="text-muted mb-0">
                        <i class="bi bi-tag me-1"></i>
                        SKU: {{ $product->sku ?: 'Not set' }}
                        @if($product->category)
                            <span class="ms-3">
                                <i class="bi bi-folder me-1"></i>
                                {{ $product->category->full_path }}
                            </span>
                        @endif
                    </p>
                </div>
            </div>
            @if($product->description)
                <p class="text-muted mb-0">{{ $product->description }}</p>
            @endif
        </div>
        <div class="col-lg-4 text-lg-end">
            <div class="d-flex gap-2 justify-content-lg-end">
                <a href="{{ route('products.edit', $product) }}" class="btn btn-primary">
                    <i class="bi bi-pencil me-2"></i>Edit Product
                </a>
                <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Products
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Key Metrics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card h-100">
            <div class="card-body text-center">
                <div class="stat-icon mb-2">
                    <i class="bi bi-currency-dollar text-success fs-1"></i>
                </div>
                <h3 class="fw-bold text-success mb-1">${{ number_format($product->selling_price, 2) }}</h3>
                <p class="text-muted mb-0 small">Selling Price</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card h-100">
            <div class="card-body text-center">
                <div class="stat-icon mb-2">
                    <i class="bi bi-graph-up text-primary fs-1"></i>
                </div>
                <h3 class="fw-bold text-primary mb-1">
                    @if($product->gp !== null)
                        ${{ number_format($product->gp, 2) }}
                    @else
                        <span class="text-muted">N/A</span>
                    @endif
                </h3>
                <p class="text-muted mb-0 small">Gross Profit</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card h-100">
            <div class="card-body text-center">
                <div class="stat-icon mb-2">
                    <i class="bi bi-building text-info fs-1"></i>
                </div>
                <h3 class="fw-bold text-info mb-1">{{ $product->warehouse_stock }}</h3>
                <p class="text-muted mb-0 small">Warehouse Stock</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card h-100">
            <div class="card-body text-center">
                <div class="stat-icon mb-2">
                    <i class="bi bi-boxes text-warning fs-1"></i>
                </div>
                <h3 class="fw-bold text-warning mb-1">{{ $product->available_stocks }}</h3>
                <p class="text-muted mb-0 small">Available Stock</p>
            </div>
        </div>
    </div>
</div>

<!-- Product Details Section - Full Width -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0 fw-semibold">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Product Details
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Basic Information Section -->
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="detail-section">
                            <h6 class="section-title text-primary fw-semibold mb-3">
                                <i class="bi bi-box me-2"></i>Basic Information
                            </h6>
                            <div class="detail-item mb-3">
                                <label class="text-muted small fw-semibold text-uppercase">Product Name</label>
                                <div class="detail-value">
                                    <span class="fw-bold text-dark">{{ $product->name }}</span>
                                </div>
                            </div>
                            <div class="detail-item mb-3">
                                <label class="text-muted small fw-semibold text-uppercase">SKU</label>
                                <div class="detail-value">
                                    @if($product->sku)
                                        <span class="badge bg-light text-dark border fw-semibold">{{ $product->sku }}</span>
                                    @else
                                        <span class="text-muted">Not set</span>
                                    @endif
                                </div>
                            </div>
                            <div class="detail-item mb-3">
                                <label class="text-muted small fw-semibold text-uppercase">Description</label>
                                <div class="detail-value">
                                    @if($product->description)
                                        <p class="text-muted mb-0 small">{{ $product->description }}</p>
                                    @else
                                        <span class="text-muted">No description available</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing Information Section -->
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="detail-section">
                            <h6 class="section-title text-success fw-semibold mb-3">
                                <i class="bi bi-currency-dollar me-2"></i>Pricing Information
                            </h6>
                            <div class="detail-item mb-3">
                                <label class="text-muted small fw-semibold text-uppercase">Selling Price</label>
                                <div class="detail-value">
                                    <span class="fw-bold text-success fs-5">${{ number_format($product->selling_price, 2) }}</span>
                                </div>
                            </div>
                            <div class="detail-item mb-3">
                                <label class="text-muted small fw-semibold text-uppercase">Purchase Price</label>
                                <div class="detail-value">
                                    @if($product->purchase_price)
                                        <span class="fw-bold text-info fs-5">${{ number_format($product->purchase_price, 2) }}</span>
                                    @else
                                        <span class="text-muted">Not set</span>
                                    @endif
                                </div>
                            </div>
                            <div class="detail-item mb-3">
                                <label class="text-muted small fw-semibold text-uppercase">Gross Profit</label>
                                <div class="detail-value">
                                    @if($product->gp !== null)
                                        <span class="fw-bold text-primary fs-5">${{ number_format($product->gp, 2) }}</span>
                                        @php
                                            $profitMargin = $product->selling_price > 0 ? ($product->gp / $product->selling_price) * 100 : 0;
                                        @endphp
                                        <span class="badge bg-success-subtle text-success ms-2">{{ number_format($profitMargin, 1) }}%</span>
                                    @else
                                        <span class="text-muted">Not calculated</span>
                                    @endif
                                </div>
                            </div>
                            @if($product->markup)
                            <div class="detail-item mb-3">
                                <label class="text-muted small fw-semibold text-uppercase">Markup</label>
                                <div class="detail-value">
                                    <span class="badge bg-primary-subtle text-primary fw-semibold fs-6">{{ $product->markup }}%</span>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Stock Information Section -->
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="detail-section">
                            <h6 class="section-title text-info fw-semibold mb-3">
                                <i class="bi bi-boxes me-2"></i>Stock Information
                            </h6>
                            <div class="detail-item mb-3">
                                <label class="text-muted small fw-semibold text-uppercase">Warehouse Stock</label>
                                <div class="detail-value">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-{{ $product->warehouse_stock > 0 ? 'success' : 'secondary' }} fs-6 me-2">
                                            {{ $product->warehouse_stock }}
                                        </span>
                                        <small class="text-muted">units</small>
                                    </div>
                                </div>
                            </div>
                            <div class="detail-item mb-3">
                                <label class="text-muted small fw-semibold text-uppercase">Retailer Stock</label>
                                <div class="detail-value">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-{{ $product->retailer_stock > 0 ? 'success' : 'secondary' }} fs-6 me-2">
                                            {{ $product->retailer_stock }}
                                        </span>
                                        <small class="text-muted">units</small>
                                    </div>
                                </div>
                            </div>
                            <div class="detail-item mb-3">
                                <label class="text-muted small fw-semibold text-uppercase">Total Available Stock</label>
                                <div class="detail-value">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-{{ $product->available_stocks > 0 ? 'success' : 'danger' }} fs-6 me-2">
                                            {{ $product->available_stocks }}
                                        </span>
                                        <small class="text-muted">units</small>
                                    </div>
                                </div>
                            </div>
                            @if($product->available_stocks > 0)
                            <div class="detail-item mb-3">
                                <label class="text-muted small fw-semibold text-uppercase">Stock Status</label>
                                <div class="detail-value">
                                    @if($product->available_stocks >= 50)
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i>In Stock
                                        </span>
                                    @elseif($product->available_stocks >= 10)
                                        <span class="badge bg-warning">
                                            <i class="bi bi-exclamation-triangle me-1"></i>Low Stock
                                        </span>
                                    @else
                                        <span class="badge bg-danger">
                                            <i class="bi bi-x-circle me-1"></i>Critical Stock
                                        </span>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Performance Metrics Section -->
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="detail-section">
                            <h6 class="section-title text-primary fw-semibold mb-3">
                                <i class="bi bi-graph-up me-2"></i>Performance Metrics
                            </h6>
                            <div class="detail-item mb-3">
                                <label class="text-muted small fw-semibold text-uppercase">Total Supply Items</label>
                                <div class="detail-value">
                                    <span class="badge bg-info-subtle text-info fw-semibold">{{ $product->supplyItems->count() }}</span>
                                </div>
                            </div>
                            <div class="detail-item mb-3">
                                <label class="text-muted small fw-semibold text-uppercase">Total Order Items</label>
                                <div class="detail-value">
                                    <span class="badge bg-primary-subtle text-primary fw-semibold">{{ $product->orderItems->count() }}</span>
                                </div>
                            </div>
                            <div class="detail-item mb-3">
                                <label class="text-muted small fw-semibold text-uppercase">Stock Transactions</label>
                                <div class="detail-value">
                                    <span class="badge bg-warning-subtle text-warning fw-semibold">{{ $product->stockTransactions->count() }}</span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <label class="text-muted small fw-semibold text-uppercase">Stock Locations</label>
                                <div class="detail-value">
                                    <span class="badge bg-success-subtle text-success fw-semibold">{{ $product->locations_count }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configuration Section - Full Width -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="detail-section">
                            <h6 class="section-title text-warning fw-semibold mb-3">
                                <i class="bi bi-gear me-2"></i>Configuration
                            </h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="detail-item mb-3">
                                        <label class="text-muted small fw-semibold text-uppercase">Auto Pricing</label>
                                        <div class="detail-value">
                                            <span class="badge bg-{{ $product->auto_pricing_enabled ? 'success' : 'secondary' }}">
                                                <i class="bi bi-{{ $product->auto_pricing_enabled ? 'check-circle' : 'x-circle' }} me-1"></i>
                                                {{ $product->auto_pricing_enabled ? 'Enabled' : 'Disabled' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-item mb-3">
                                        <label class="text-muted small fw-semibold text-uppercase">Last Price Update</label>
                                        <div class="detail-value">
                                            @if($product->last_price_update)
                                                @if(is_string($product->last_price_update))
                                                    <span class="text-muted small">{{ \Carbon\Carbon::parse($product->last_price_update)->format('M d, Y H:i') }}</span>
                                                @else
                                                    <span class="text-muted small">{{ $product->last_price_update->format('M d, Y H:i') }}</span>
                                                @endif
                                            @else
                                                <span class="text-muted">Never updated</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Supply History Section - Full Width -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0 fw-semibold">
                    <i class="bi bi-truck me-2 text-success"></i>Supply History
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info border-0 mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <small>Product purchase prices are updated when supplies are marked as completed. GP (Gross Profit) is calculated when orders are confirmed.</small>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th class="border-0">Date</th>
                                <th class="border-0">Vendor</th>
                                <th class="border-0">Quantity</th>
                                <th class="border-0">Unit Cost</th>
                                <th class="border-0">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($product->supplyItems as $item)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-calendar3 text-muted me-2"></i>
                                        @if($item->supply->supply_date)
                                            @if(is_string($item->supply->supply_date))
                                                {{ \Carbon\Carbon::parse($item->supply->supply_date)->format('M d, Y') }}
                                            @else
                                                {{ $item->supply->supply_date->format('M d, Y') }}
                                            @endif
                                        @else
                                            <span class="text-muted">Unknown</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-building text-muted me-2"></i>
                                        {{ $item->supply->vendor->name }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-success-subtle text-success fw-semibold">
                                        {{ $item->quantity }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-success">${{ number_format($item->unit_cost, 2) }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $item->supply->status == 'completed' ? 'success' : ($item->supply->status == 'processing' ? 'primary' : 'warning') }}">
                                        <i class="bi bi-{{ $item->supply->status == 'completed' ? 'check-circle' : ($item->supply->status == 'processing' ? 'arrow-clockwise' : 'exclamation-circle') }} me-1"></i>
                                        {{ ucfirst($item->supply->status) }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox fs-1 mb-3 d-block"></i>
                                        No supply history found
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Order and Return History Row -->
<div class="row">
    <!-- Order History Card -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0 fw-semibold">
                    <i class="bi bi-cart me-2 text-primary"></i>Order History
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th class="border-0">Date</th>
                                <th class="border-0">Customer</th>
                                <th class="border-0">Qty</th>
                                <th class="border-0">Profit</th>
                                <th class="border-0">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($product->orderItems as $item)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-calendar3 text-muted me-2"></i>
                                        @if($item->order->order_date)
                                            @if(is_string($item->order->order_date))
                                                {{ \Carbon\Carbon::parse($item->order->order_date)->format('M d, Y') }}
                                            @else
                                                {{ $item->order->order_date->format('M d, Y') }}
                                            @endif
                                        @else
                                            <span class="text-muted">Unknown</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-person text-muted me-2"></i>
                                        {{ $item->order->customer->name }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary fw-semibold">
                                        {{ $item->quantity }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-success">${{ number_format($item->profit, 2) }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $item->order->status == 'completed' ? 'success' : ($item->order->status == 'processing' ? 'primary' : 'warning') }}">
                                        <i class="bi bi-{{ $item->order->status == 'completed' ? 'check-circle' : ($item->order->status == 'processing' ? 'arrow-clockwise' : 'exclamation-circle') }} me-1"></i>
                                        {{ ucfirst($item->order->status) }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-cart-x fs-1 mb-3 d-block"></i>
                                        No order history found
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Return History Card -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0 fw-semibold">
                    <i class="bi bi-arrow-return-left me-2 text-warning"></i>Return History
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info border-0 mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <small>Returns affect stock levels and are included in the available stock calculation.</small>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th class="border-0">Date</th>
                                <th class="border-0">Type</th>
                                <th class="border-0">Qty</th>
                                <th class="border-0">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $returnTransactions = $product->stockTransactions
                                    ->whereIn('transaction_type', [
                                        \App\Models\StockTransaction::TYPE_CUSTOMER_RETURN,
                                        \App\Models\StockTransaction::TYPE_VENDOR_RETURN,
                                        \App\Models\StockTransaction::TYPE_RETAILER_RETURN
                                    ])
                                    ->sortByDesc('transaction_date');
                            @endphp
                            
                            @forelse($returnTransactions as $transaction)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-calendar3 text-muted me-2"></i>
                                        @if($transaction->transaction_date)
                                            @if(is_string($transaction->transaction_date))
                                                {{ \Carbon\Carbon::parse($transaction->transaction_date)->format('M d, Y') }}
                                            @else
                                                {{ $transaction->transaction_date->format('M d, Y') }}
                                            @endif
                                        @else
                                            <span class="text-muted">Unknown</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $transaction->getReturnTypeBadgeClass() }}">
                                        <i class="bi bi-{{ $transaction->transaction_type === 'customer_return' ? 'person-check' : ($transaction->transaction_type === 'vendor_return' ? 'building-x' : 'shop') }} me-1"></i>
                                        {{ $transaction->getReturnTypeLabel() }}
                                    </span>
                                </td>
                                <td>
                                    @if(isset($transaction->direction) && isset($transaction->quantity))
                                        <span class="badge bg-{{ $transaction->direction === 'inbound' ? 'success' : 'danger' }}-subtle text-{{ $transaction->direction === 'inbound' ? 'success' : 'danger' }} fw-semibold">
                                            <i class="bi bi-{{ $transaction->direction === 'inbound' ? 'plus' : 'dash' }} me-1"></i>
                                            {{ abs($transaction->quantity) }}
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $transaction->getStatusBadgeClass() }}">
                                        <i class="bi bi-{{ $transaction->status === 'completed' ? 'check-circle' : ($transaction->status === 'approved' ? 'check' : 'clock') }} me-1"></i>
                                        {{ ucfirst($transaction->status ?? 'unknown') }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-arrow-return-left fs-1 mb-3 d-block"></i>
                                        No return history found
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stock Calculation Summary Card -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="card-title mb-0 fw-semibold">
            <i class="bi bi-calculator me-2 text-info"></i>Stock Calculation Summary
        </h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-3 col-md-6">
                <div class="calculation-card text-center p-3 rounded">
                    <div class="calculation-icon mb-2">
                        <i class="bi bi-truck text-success fs-2"></i>
                    </div>
                    <h4 class="fw-bold text-success mb-1">
                        {{ $product->supplyItems && $product->supplyItems->where('supply.status', 'completed') ? $product->supplyItems->where('supply.status', 'completed')->sum('quantity') : 0 }}
                    </h4>
                    <p class="text-muted mb-0 small fw-semibold">Total Supplied</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="calculation-card text-center p-3 rounded">
                    <div class="calculation-icon mb-2">
                        <i class="bi bi-cart-x text-danger fs-2"></i>
                    </div>
                    <h4 class="fw-bold text-danger mb-1">
                        {{ $product->orderItems && $product->orderItems->where('order.status', 'completed') ? $product->orderItems->where('order.status', 'completed')->sum('quantity') : 0 }}
                    </h4>
                    <p class="text-muted mb-0 small fw-semibold">Total Sold</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="calculation-card text-center p-3 rounded">
                    <div class="calculation-icon mb-2">
                        <i class="bi bi-arrow-return-left text-warning fs-2"></i>
                    </div>
                    <h4 class="fw-bold text-warning mb-1">
                        @php
                            $totalReturns = $product->stockTransactions
                                ->whereIn('transaction_type', [
                                    \App\Models\StockTransaction::TYPE_CUSTOMER_RETURN,
                                    \App\Models\StockTransaction::TYPE_VENDOR_RETURN,
                                    \App\Models\StockTransaction::TYPE_RETAILER_RETURN
                                ])
                                ->whereIn('status', ['pending', 'approved', 'completed'])
                                ->sum(function($transaction) {
                                    if ($transaction->transaction_type === \App\Models\StockTransaction::TYPE_VENDOR_RETURN) {
                                        return -$transaction->quantity;
                                    } else {
                                        return $transaction->quantity;
                                    }
                                });
                        @endphp
                        {{ $totalReturns }}
                    </h4>
                    <p class="text-muted mb-0 small fw-semibold">Total Returned</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="calculation-card text-center p-3 rounded bg-primary-subtle">
                    <div class="calculation-icon mb-2">
                        <i class="bi bi-boxes text-primary fs-2"></i>
                    </div>
                    <h4 class="fw-bold text-primary mb-1">{{ $product->available_stocks }}</h4>
                    <p class="text-muted mb-0 small fw-semibold">Available Stock</p>
                </div>
            </div>
        </div>
        <div class="mt-4 text-center">
            <div class="formula-display">
                <span class="badge bg-light text-dark border fw-semibold">
                    <i class="bi bi-equals me-2"></i>
                    Available Stock = Total Supplied - Total Sold + Total Returned
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Stock Locations Section - Full Width -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0 fw-semibold">
                    <i class="bi bi-geo-alt me-2 text-info"></i>Stock Locations
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info border-0 mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <small>This section shows all locations where this product is currently stored and their respective quantities.</small>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th class="border-0">Location Type</th>
                                <th class="border-0">Location Name</th>
                                <th class="border-0">Quantity</th>
                                <th class="border-0">Status</th>
                                <th class="border-0">Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($product->stockBalances as $stockBalance)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        @if($stockBalance->location_type === 'App\Models\Warehouse')
                                            <i class="bi bi-building text-primary me-2"></i>
                                            <span class="badge bg-primary-subtle text-primary">Warehouse</span>
                                        @elseif($stockBalance->location_type === 'App\Models\Retailer')
                                            <i class="bi bi-shop text-success me-2"></i>
                                            <span class="badge bg-success-subtle text-success">Retailer</span>
                                        @else
                                            <i class="bi bi-geo-alt text-muted me-2"></i>
                                            <span class="badge bg-secondary-subtle text-secondary">Other</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        @if($stockBalance->location)
                                            <span class="fw-semibold text-dark">{{ $stockBalance->location->name }}</span>
                                            <small class="text-muted ms-2">(ID: {{ $stockBalance->location_id }})</small>
                                        @else
                                            @php
                                                // Fallback: Try to find the location manually
                                                try {
                                                    $locationModel = $stockBalance->location_type::find($stockBalance->location_id);
                                                    $locationName = $locationModel ? $locationModel->name : 'Unknown Location';
                                                } catch (\Exception $e) {
                                                    $locationName = 'Unknown Location';
                                                }
                                            @endphp
                                            <span class="fw-semibold text-dark">{{ $locationName }}</span>
                                            <small class="text-muted ms-2">(ID: {{ $stockBalance->location_id }})</small>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-{{ $stockBalance->quantity > 0 ? 'success' : 'secondary' }} fs-6 me-2">
                                            {{ $stockBalance->quantity }}
                                        </span>
                                        <small class="text-muted">units</small>
                                        @if($stockBalance->reserved_quantity > 0)
                                            <small class="text-warning ms-2">({{ $stockBalance->reserved_quantity }} reserved)</small>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if($stockBalance->quantity > 0)
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i>In Stock
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-x-circle me-1"></i>No Stock
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-clock text-muted me-2"></i>
                                        @if($stockBalance->updated_at)
                                            @if(is_string($stockBalance->updated_at))
                                                <span class="text-muted small">{{ \Carbon\Carbon::parse($stockBalance->updated_at)->format('M d, Y H:i') }}</span>
                                            @else
                                                <span class="text-muted small">{{ $stockBalance->updated_at->format('M d, Y H:i') }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted small">Never updated</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox fs-1 mb-3 d-block"></i>
                                        No stock locations found for this product
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($product->stockBalances->count() > 0)
                <div class="mt-3">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-building text-primary me-2"></i>
                                <span class="fw-semibold">Warehouses:</span>
                                <span class="badge bg-primary-subtle text-primary ms-2">
                                    {{ $product->stockBalances->where('location_type', 'App\Models\Warehouse')->count() }}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-shop text-success me-2"></i>
                                <span class="fw-semibold">Retailers:</span>
                                <span class="badge bg-success-subtle text-success ms-2">
                                    {{ $product->stockBalances->where('location_type', 'App\Models\Retailer')->count() }}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-boxes text-info me-2"></i>
                                <span class="fw-semibold">Total Locations:</span>
                                <span class="badge bg-info-subtle text-info ms-2">
                                    {{ $product->stockBalances->count() }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
/* Product Details Page Specific Styles */
.product-hero {
    background: linear-gradient(135deg, var(--light-bg) 0%, #ffffff 100%);
    border-radius: 12px;
    padding: 2rem;
    border: 1px solid var(--border-color);
}

.product-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.stat-card {
    border: none;
    border-radius: 12px;
    background: linear-gradient(135deg, #ffffff 0%, var(--light-bg) 100%);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.stat-icon {
    opacity: 0.8;
}

.detail-item {
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 1rem;
}

.detail-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.detail-value {
    margin-top: 0.25rem;
}

.calculation-card {
    background: linear-gradient(135deg, #ffffff 0%, var(--light-bg) 100%);
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.calculation-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.calculation-icon {
    opacity: 0.8;
}

.formula-display {
    padding: 1rem;
    background: var(--light-bg);
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .product-hero {
        padding: 1.5rem;
    }
    
    .stat-card {
        margin-bottom: 1rem;
    }
    
    .calculation-card {
        margin-bottom: 1rem;
    }
}

@media (max-width: 576px) {
    .product-hero {
        padding: 1rem;
    }
    
    .product-icon {
        width: 50px;
        height: 50px;
    }
    
    .stat-card .card-body {
        padding: 1rem;
    }
    
    .calculation-card {
        padding: 1rem !important;
    }
}

/* Badge enhancements */
.badge {
    font-size: 0.75rem;
    padding: 0.5rem 0.75rem;
}

.bg-success-subtle {
    background-color: rgba(82, 183, 136, 0.1) !important;
}

.bg-primary-subtle {
    background-color: rgba(44, 110, 73, 0.1) !important;
}

.bg-danger-subtle {
    background-color: rgba(231, 111, 81, 0.1) !important;
}

.bg-warning-subtle {
    background-color: rgba(248, 150, 30, 0.1) !important;
}

/* Table enhancements */
.table {
    font-size: 0.9rem;
}

.table td {
    vertical-align: middle;
    padding: 0.75rem;
}

.table th {
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Product Details Section Enhancements */
.detail-section {
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 1.5rem;
}

.detail-section:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.section-title {
    font-size: 0.9rem;
    letter-spacing: 0.5px;
    border-bottom: 2px solid;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}

.section-title.text-primary {
    border-color: var(--primary);
}

.section-title.text-success {
    border-color: var(--success);
}

.section-title.text-info {
    border-color: var(--info);
}

.section-title.text-warning {
    border-color: var(--warning);
}

.detail-item {
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding-bottom: 1rem;
    margin-bottom: 1rem;
}

.detail-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
    margin-bottom: 0;
}

.detail-value {
    margin-top: 0.25rem;
}

.detail-value .badge {
    font-size: 0.75rem;
    padding: 0.5rem 0.75rem;
}

/* Enhanced badge styles */
.bg-info-subtle {
    background-color: rgba(61, 90, 128, 0.1) !important;
}

.bg-light {
    background-color: #f8f9fa !important;
}

/* Stock status indicators */
.stock-status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 0.5rem;
}

.stock-status-indicator.success {
    background-color: var(--success);
}

.stock-status-indicator.warning {
    background-color: var(--warning);
}

.stock-status-indicator.danger {
    background-color: var(--danger);
}

/* Performance metrics styling */
.performance-metric {
    background: linear-gradient(135deg, #ffffff 0%, var(--light-bg) 100%);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    transition: all 0.3s ease;
}

.performance-metric:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

/* Responsive adjustments for product details */
@media (max-width: 768px) {
    .detail-section {
        padding-bottom: 1rem;
    }
    
    .section-title {
        font-size: 0.85rem;
    }
    
    .detail-item {
        padding-bottom: 0.75rem;
        margin-bottom: 0.75rem;
    }
}

@media (max-width: 576px) {
    .detail-section {
        padding-bottom: 0.75rem;
    }
    
    .section-title {
        font-size: 0.8rem;
        margin-bottom: 0.75rem;
    }
    
    .detail-item {
        padding-bottom: 0.5rem;
        margin-bottom: 0.5rem;
    }
    
    .detail-value .badge {
        font-size: 0.7rem;
        padding: 0.4rem 0.6rem;
    }
}
</style>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Add smooth animations
        const cards = document.querySelectorAll('.stat-card, .calculation-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });
</script>
@endsection 
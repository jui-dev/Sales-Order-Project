@extends('layouts.app')

@php
    use Carbon\Carbon;
    use App\Models\Invoice;
    use App\Models\Order;
    use App\Models\Product;
    use App\Models\StockTransaction;
    use App\Models\Customer;
    use App\Models\Vendor;
    
    use App\Models\Payment;
    use App\Models\ProductStock;
    
    // Date ranges for filtering
    $today = Carbon::today();
    $lastWeek = Carbon::today()->subWeek();
    $lastMonth = Carbon::today()->subMonth();
    $lastYear = Carbon::today()->subYear();
    
    // KPI Data
    $totalSalesToday = Invoice::whereDate('created_at', $today)->sum('total');
    $returnsToday = StockTransaction::whereIn('transaction_type', ['customer_return', 'vendor_return', 'retailer_return'])
        ->whereDate('created_at', $today)->count();
    $pendingOrders = Order::where('status', 'pending')->count();
    // Stock valued at its RETAIL price - what it would fetch, not what it cost.
    // TransactionFlowService reports the same inventory at cost, so both are
    // labelled explicitly; two unlabelled figures that disagree read as a bug.
    $currentStockValue = Product::withCurrentPricing()
        ->get(['products.id', 'products.available_stocks'])
        ->sum(fn ($product) => (float) ($product->current_price ?? 0) * (int) ($product->available_stocks ?? 0));
    $outstandingPayments = Invoice::where('payment_status', 'unpaid')->sum('total');
    
    // Sales Trend Data (Last 30 days)
    $salesData = Invoice::selectRaw('DATE(created_at) as date, SUM(total) as total')
        ->whereBetween('created_at', [$lastMonth, $today])
        ->groupBy('date')
        ->orderBy('date')
        ->get();
    
    $chartLabels = $salesData->pluck('date')->map(fn($d) => Carbon::parse($d)->format('M d'))->values();
    $chartTotals = $salesData->pluck('total')->values();
    
    // Top Selling Products (Last 30 days)
    $topProducts = DB::table('invoice_items')
        ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
        ->join('products', 'invoice_items.product_id', '=', 'products.id')
        ->selectRaw('products.name, SUM(invoice_items.quantity) as total_quantity, SUM(invoice_items.total) as total_sales')
        ->whereBetween('invoices.created_at', [$lastMonth, $today])
        ->groupBy('products.id', 'products.name')
        ->orderByDesc('total_quantity')
        ->limit(10)
        ->get();
    
    // Order Status Distribution
    $orderStatuses = Order::selectRaw('status, COUNT(*) as count')
        ->groupBy('status')
        ->get()
        ->pluck('count', 'status')
        ->toArray();
    
    // Stock Movement Data (Last 30 days)
    $stockInflow = StockTransaction::where('direction', 'in')
        ->whereBetween('created_at', [$lastMonth, $today])
        ->selectRaw('DATE(created_at) as date, SUM(quantity) as total')
        ->groupBy('date')
        ->orderBy('date')
        ->get();
    
    $stockOutflow = StockTransaction::where('direction', 'out')
        ->whereBetween('created_at', [$lastMonth, $today])
        ->selectRaw('DATE(created_at) as date, SUM(quantity) as total')
        ->groupBy('date')
        ->orderBy('date')
        ->get();
    
    // Returns Breakdown (Last 30 days)
    $customerReturns = StockTransaction::where('transaction_type', 'customer_return')
        ->whereBetween('created_at', [$lastMonth, $today])
        ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
        ->groupBy('date')
        ->orderBy('date')
        ->get();
    
    $vendorReturns = StockTransaction::where('transaction_type', 'vendor_return')
        ->whereBetween('created_at', [$lastMonth, $today])
        ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
        ->groupBy('date')
        ->orderBy('date')
        ->get();
    
    // Low Stock Products
    $lowStockProducts = Product::where('available_stocks', '<=', 10)
        ->orderBy('available_stocks')
        ->limit(10)
        ->get();
    
    // Recent Activity
    $recentOrders = Order::with('customer')->latest()->limit(5)->get();
    $recentReturns = StockTransaction::whereIn('transaction_type', ['customer_return', 'vendor_return', 'retailer_return'])
        ->with(['product', 'location'])->latest()->limit(5)->get();
    $recentStockMovements = StockTransaction::with(['product', 'location'])
        ->whereNotIn('transaction_type', ['customer_return', 'vendor_return', 'retailer_return'])
        ->latest()->limit(5)->get();
@endphp

@section('page-header')
<div class="row">
    <div class="col-12 mb-4 page-heading">
        <h1 class="h3">Welcome to Sales Order System</h1>
        <p>Manage your business operations efficiently</p>
    </div>
</div>
@endsection

@section('content')
<!-- KPI Cards -->
<div class="row mb-4">
    <div class="col-md-2 col-sm-6 mb-3">
        <div class="card stat-card h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted">Sales Today</h6>
                        <h3 class="text-success">${{ number_format($totalSalesToday, 2) }}</h3>
                    </div>
                    <div class="bg-subtle p-3 rounded-3">
                        <i class="bi bi-currency-dollar fs-4 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 col-sm-6 mb-3">
        <div class="card stat-card h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted">Returns Today</h6>
                        <h3 class="text-warning">{{ $returnsToday }}</h3>
                    </div>
                    <div class="bg-subtle p-3 rounded-3">
                        <i class="bi bi-arrow-return-left fs-4 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 col-sm-6 mb-3">
        <div class="card stat-card h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted">Pending Orders</h6>
                        <h3 class="text-info">{{ $pendingOrders }}</h3>
                    </div>
                    <div class="bg-subtle p-3 rounded-3">
                        <i class="bi bi-clock fs-4 text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 col-sm-6 mb-3">
        <div class="card stat-card h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        {{-- Named for its basis. Stock management reports the same
                             inventory at cost, and two figures both called "Stock
                             Value" that disagree look like a fault rather than
                             two different questions. --}}
                        <h6 class="text-muted">Stock Value <span class="small">(at retail)</span></h6>
                        <h3 class="text-primary">${{ number_format($currentStockValue, 2) }}</h3>
                    </div>
                    <div class="bg-subtle p-3 rounded-3">
                        <i class="bi bi-box-seam fs-4 text-primary-custom"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 col-sm-6 mb-3">
        <div class="card stat-card h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted">Outstanding</h6>
                        <h3 class="text-danger">${{ number_format($outstandingPayments, 2) }}</h3>
                    </div>
                    <div class="bg-subtle p-3 rounded-3">
                        <i class="bi bi-exclamation-triangle fs-4 text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 col-sm-6 mb-3">
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
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <!-- Sales Trend Chart -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-graph-up me-2"></i>Sales Trend (Last 30 Days)
                </div>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary active" data-period="30">30D</button>
                    <button type="button" class="btn btn-outline-primary" data-period="7">7D</button>
                    <button type="button" class="btn btn-outline-primary" data-period="90">90D</button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="salesTrendChart" height="120"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Order Status Donut Chart -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center">
                <i class="bi bi-pie-chart me-2"></i>Order Status Distribution
            </div>
            <div class="card-body">
                <canvas id="orderStatusChart" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Second Charts Row -->
<div class="row mb-4">
    <!-- Top Selling Products -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center">
                <i class="bi bi-bar-chart me-2"></i>Top Selling Products (Last 30 Days)
            </div>
            <div class="card-body">
                <canvas id="topProductsChart" height="120"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Stock Movement -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center">
                <i class="bi bi-arrow-left-right me-2"></i>Stock Movement (Last 30 Days)
            </div>
            <div class="card-body">
                <canvas id="stockMovementChart" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Returns and Activity Row -->
<div class="row mb-4">
    <!-- Returns Breakdown -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center">
                <i class="bi bi-arrow-return-left me-2"></i>Returns Breakdown (Last 30 Days)
            </div>
            <div class="card-body">
                <canvas id="returnsChart" height="120"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center">
                <i class="bi bi-activity me-2"></i>Recent Activity
            </div>
            <div class="card-body p-0">
                <div class="activity-feed">
                    @foreach($recentOrders as $order)
                    <div class="activity-item">
                        <div class="activity-icon bg-primary-custom">
                            <i class="bi bi-cart"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">New Order #{{ $order->formatted_id }}</div>
                            <div class="activity-subtitle">{{ $order->customer->name ?? 'Unknown Customer' }}</div>
                            <div class="activity-time">{{ $order->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                    @endforeach
                    
                    @foreach($recentReturns as $return)
                    <div class="activity-item">
                        <div class="activity-icon bg-warning">
                            <i class="bi bi-arrow-return-left"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">{{ ucfirst(str_replace('_', ' ', $return->transaction_type)) }}</div>
                            <div class="activity-subtitle">{{ $return->product->name ?? 'Unknown Product' }}</div>
                            <div class="activity-time">{{ $return->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Low Stock Alert and Quick Actions -->
<div class="row mb-4">
    <!-- Low Stock Alert -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center">
                <i class="bi bi-exclamation-triangle me-2"></i>Low Stock Alert
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Current Stock</th>
                                <th>Stock Level</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lowStockProducts as $product)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="product-info">
                                            <div class="product-name">{{ $product->name }}</div>
                                            <div class="product-sku text-muted">{{ $product->sku }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $product->available_stocks <= 5 ? 'bg-danger' : 'bg-warning' }}">
                                        {{ $product->available_stocks }}
                                    </span>
                                </td>
                                <td>
                                    <div class="progress" style="height: 8px;">
                                        @php
                                            $percentage = min(100, ($product->available_stocks / 10) * 100);
                                        @endphp
                                        <div class="progress-bar {{ $product->available_stocks <= 5 ? 'bg-danger' : 'bg-warning' }}" 
                                             style="width: {{ $percentage }}%"></div>
                                    </div>
                                </td>
                                <td>
                                    <a href="{{ route('products.edit', $product->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-stack me-2"></i>Quick Actions
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
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
                    <div class="col-12">
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
                    <div class="col-12">
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
                    <div class="col-12">
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
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
/* Activity Feed Styles */
.activity-feed {
    max-height: 400px;
    overflow-y: auto;
}



.activity-item {
    display: flex;
    align-items: flex-start;
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    transition: background-color 0.2s ease;
}

.activity-item:hover {
    background-color: rgba(44, 110, 73, 0.03);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    flex-shrink: 0;
}

.activity-icon i {
    color: white;
    font-size: 0.9rem;
}

.activity-content {
    flex: 1;
    min-width: 0;
}

.activity-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--dark-text);
    margin-bottom: 0.25rem;
}

.activity-subtitle {
    font-size: 0.8rem;
    color: var(--medium-text);
    margin-bottom: 0.25rem;
}

.activity-time {
    font-size: 0.75rem;
    color: var(--medium-text);
}

/* Progress Bar Enhancements */
.progress {
    background-color: rgba(0, 0, 0, 0.1);
    border-radius: 4px;
}

.progress-bar {
    border-radius: 4px;
    transition: width 0.3s ease;
}

/* Product Info Styles */
.product-info {
    display: flex;
    flex-direction: column;
}

.product-name {
    font-weight: 500;
    font-size: 0.9rem;
}

.product-sku {
    font-size: 0.75rem;
}

/* Chart Container Enhancements */
.card-body canvas {
    max-height: 300px;
}

/* Responsive Chart Adjustments */
@media (max-width: 768px) {
    .card-body canvas {
        max-height: 200px;
    }
    
    .activity-feed {
        max-height: 300px;
    }
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Sales Trend Chart
    const salesCtx = document.getElementById('salesTrendChart').getContext('2d');
    const salesChart = new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: @json($chartLabels),
            datasets: [{
                label: 'Sales',
                data: @json($chartTotals),
                fill: true,
                tension: 0.4,
                backgroundColor: 'rgba(44, 110, 73, 0.1)',
                borderColor: 'rgba(44, 110, 73, 1)',
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: 'rgba(44, 110, 73, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(44, 110, 73, 1)',
                    borderWidth: 1,
                    cornerRadius: 6,
                }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                    },
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });

    // Order Status Donut Chart
    const orderCtx = document.getElementById('orderStatusChart').getContext('2d');
    const orderStatusData = @json($orderStatuses);
    const orderLabels = Object.keys(orderStatusData);
    const orderValues = Object.values(orderStatusData);
    
    new Chart(orderCtx, {
        type: 'doughnut',
        data: {
            labels: orderLabels.map(label => label.charAt(0).toUpperCase() + label.slice(1)),
            datasets: [{
                data: orderValues,
                backgroundColor: [
                    'rgba(44, 110, 73, 0.8)',
                    'rgba(42, 157, 143, 0.8)',
                    'rgba(233, 196, 106, 0.8)',
                    'rgba(231, 111, 81, 0.8)',
                    'rgba(61, 90, 128, 0.8)',
                ],
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(44, 110, 73, 1)',
                    borderWidth: 1,
                    cornerRadius: 6,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });

    // Top Products Bar Chart
    const productsCtx = document.getElementById('topProductsChart').getContext('2d');
    const productNames = @json($topProducts->pluck('name'));
    const productQuantities = @json($topProducts->pluck('total_quantity'));
    
    new Chart(productsCtx, {
        type: 'bar',
        data: {
            labels: productNames,
            datasets: [{
                label: 'Quantity Sold',
                data: productQuantities,
                backgroundColor: 'rgba(44, 110, 73, 0.8)',
                borderColor: 'rgba(44, 110, 73, 1)',
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(44, 110, 73, 1)',
                    borderWidth: 1,
                    cornerRadius: 6,
                }
            },
            scales: {
                x: { 
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                    }
                }
            }
        }
    });

    // Stock Movement Area Chart
    const stockCtx = document.getElementById('stockMovementChart').getContext('2d');
    const stockInflowData = @json($stockInflow);
    const stockOutflowData = @json($stockOutflow);
    
    const stockLabels = @json($stockInflow->pluck('date')->map(fn($d) => Carbon::parse($d)->format('M d'))->values());
    const inflowValues = @json($stockInflow->pluck('total')->values());
    const outflowValues = @json($stockOutflow->pluck('total')->values());
    
    new Chart(stockCtx, {
        type: 'line',
        data: {
            labels: stockLabels,
            datasets: [
                {
                    label: 'Stock Inflow',
                    data: inflowValues,
                    fill: true,
                    tension: 0.4,
                    backgroundColor: 'rgba(44, 110, 73, 0.1)',
                    borderColor: 'rgba(44, 110, 73, 1)',
                    pointRadius: 3,
                },
                {
                    label: 'Stock Outflow',
                    data: outflowValues,
                    fill: true,
                    tension: 0.4,
                    backgroundColor: 'rgba(231, 111, 81, 0.1)',
                    borderColor: 'rgba(231, 111, 81, 1)',
                    pointRadius: 3,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(44, 110, 73, 1)',
                    borderWidth: 1,
                    cornerRadius: 6,
                }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });

    // Returns Breakdown Chart
    const returnsCtx = document.getElementById('returnsChart').getContext('2d');
    const customerReturnsData = @json($customerReturns);
    const vendorReturnsData = @json($vendorReturns);
    
    const returnsLabels = @json($customerReturns->pluck('date')->map(fn($d) => Carbon::parse($d)->format('M d'))->values());
    const customerReturnsValues = @json($customerReturns->pluck('count')->values());
    const vendorReturnsValues = @json($vendorReturns->pluck('count')->values());
    
    new Chart(returnsCtx, {
        type: 'bar',
        data: {
            labels: returnsLabels,
            datasets: [
                {
                    label: 'Customer Returns',
                    data: customerReturnsValues,
                    backgroundColor: 'rgba(44, 110, 73, 0.8)',
                    borderColor: 'rgba(44, 110, 73, 1)',
                    borderWidth: 1,
                },
                {
                    label: 'Vendor Returns',
                    data: vendorReturnsValues,
                    backgroundColor: 'rgba(231, 111, 81, 0.8)',
                    borderColor: 'rgba(231, 111, 81, 1)',
                    borderWidth: 1,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(44, 110, 73, 1)',
                    borderWidth: 1,
                    cornerRadius: 6,
                }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                    }
                }
            }
        }
    });

    // Period Filter Buttons
    document.querySelectorAll('[data-period]').forEach(button => {
        button.addEventListener('click', function() {
            const period = this.dataset.period;
            
            // Remove active class from all buttons
            document.querySelectorAll('[data-period]').forEach(btn => btn.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');
            
            // Update all charts with new period data
            updateSalesTrendChart(period);
            updateTopProductsChart(period);
            updateStockMovementChart(period);
            updateReturnsChart(period);
        });
    });

    // Function to update sales trend chart
    function updateSalesTrendChart(days) {
        fetch(`/dashboard/sales-trend?days=${days}`)
            .then(response => response.json())
            .then(data => {
                salesChart.data.labels = data.labels;
                salesChart.data.datasets[0].data = data.data;
                salesChart.update();
            })
            .catch(error => console.error('Error updating sales trend chart:', error));
    }

    // Function to update top products chart
    function updateTopProductsChart(days) {
        fetch(`/dashboard/top-products?days=${days}`)
            .then(response => response.json())
            .then(data => {
                const productNames = data.map(item => item.name);
                const productQuantities = data.map(item => item.total_quantity);
                
                const productsChart = Chart.getChart('topProductsChart');
                if (productsChart) {
                    productsChart.data.labels = productNames;
                    productsChart.data.datasets[0].data = productQuantities;
                    productsChart.update();
                }
            })
            .catch(error => console.error('Error updating top products chart:', error));
    }

    // Function to update stock movement chart
    function updateStockMovementChart(days) {
        fetch(`/dashboard/stock-movement?days=${days}`)
            .then(response => response.json())
            .then(data => {
                const stockChart = Chart.getChart('stockMovementChart');
                if (stockChart) {
                    stockChart.data.labels = data.labels;
                    stockChart.data.datasets[0].data = data.inflow;
                    stockChart.data.datasets[1].data = data.outflow;
                    stockChart.update();
                }
            })
            .catch(error => console.error('Error updating stock movement chart:', error));
    }

    // Function to update returns chart
    function updateReturnsChart(days) {
        fetch(`/dashboard/returns-breakdown?days=${days}`)
            .then(response => response.json())
            .then(data => {
                const returnsChart = Chart.getChart('returnsChart');
                if (returnsChart) {
                    returnsChart.data.labels = data.labels;
                    returnsChart.data.datasets[0].data = data.customerReturns;
                    returnsChart.data.datasets[1].data = data.vendorReturns;
                    returnsChart.update();
                }
            })
            .catch(error => console.error('Error updating returns chart:', error));
    }

    // Auto-refresh dashboard stats every 5 minutes
    setInterval(() => {
        fetch('/dashboard/stats')
            .then(response => response.json())
            .then(data => {
                // Update KPI cards
                document.querySelector('.stat-card .text-success').textContent = '$' + parseFloat(data.totalSalesToday).toLocaleString();
                document.querySelector('.stat-card .text-warning').textContent = data.returnsToday;
                document.querySelector('.stat-card .text-info').textContent = data.pendingOrders;
                document.querySelector('.stat-card .text-primary').textContent = '$' + parseFloat(data.currentStockValue).toLocaleString();
                document.querySelector('.stat-card .text-danger').textContent = '$' + parseFloat(data.outstandingPayments).toLocaleString();
            })
            .catch(error => console.error('Error updating dashboard stats:', error));
    }, 300000); // 5 minutes
});
</script>
@endpush 
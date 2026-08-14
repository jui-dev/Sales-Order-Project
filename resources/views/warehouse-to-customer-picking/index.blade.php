@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-house-arrow-right me-2"></i>Warehouse to Customer Picking</h1>
        <div>
            <a href="{{ route('orders.index') }}" class="btn btn-outline-primary me-2">
                <i class="bi bi-cart me-1"></i> View Orders
            </a>
            <button class="btn btn-info" onclick="loadStatistics()">
                <i class="bi bi-graph-up me-1"></i> Statistics
            </button>
        </div>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    
    <div class="warehouse-customer-picking">
    

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Info Banner -->
<div class="alert alert-info d-flex align-items-center" role="alert">
    <i class="bi bi-info-circle me-2"></i>
    <div>
        <strong>Warehouse to Customer Picking:</strong> This section shows all picking records created when customers place orders with warehouse fulfillment. Orders are automatically processed when completed.
    </div>
</div>

<!-- System Overview Cards -->
<div class="summary-panel mb-4">
    <div class="row g-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm summary-card summary-card--blue">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title text-primary">Active Warehouses</h6>
                        <h3>{{ $warehouses->count() }}</h3>
                    </div>
                    <i class="bi bi-house-fill text-primary summary-card__icon" style="font-size: 2rem;"></i>
                </div>
                <small>
                    <a href="{{ route('stock-locations.index') }}" class="text-primary text-decoration-none">
                        <i class="bi bi-arrow-right me-1"></i>Manage Warehouses
                    </a>
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm summary-card summary-card--green">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title text-success">Customers</h6>
                        <h3>{{ $customers->count() }}</h3>
                    </div>
                    <i class="bi bi-people text-success summary-card__icon" style="font-size: 2rem;"></i>
                </div>
                <small>
                    <a href="{{ route('customers.index') }}" class="text-success text-decoration-none">
                        <i class="bi bi-arrow-right me-1"></i>Manage Customers
                    </a>
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm summary-card summary-card--amber">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title text-warning-emphasis">Recent Orders</h6>
                        <h3>{{ $pickingLists->where('created_at', '>=', now()->subDays(7))->count() }}</h3>
                    </div>
                    <i class="bi bi-cart text-warning-emphasis summary-card__icon" style="font-size: 2rem;"></i>
                </div>
                <small>
                    <a href="{{ route('orders.index') }}" class="text-warning-emphasis text-decoration-none">
                        <i class="bi bi-arrow-right me-1"></i>View All Orders
                    </a>
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm summary-card summary-card--cyan">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title text-info-emphasis">Picking Records</h6>
                        <h3>{{ $pickingLists->count() }}</h3>
                    </div>
                    <i class="bi bi-list-check text-info-emphasis summary-card__icon" style="font-size: 2rem;"></i>
                </div>
                <small class="text-muted">
                    Automatic picking transactions
                </small>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- Statistics Cards (Hidden by default) -->
<div class="row mb-4" id="statistics-cards" style="display: none;">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm summary-card summary-card--blue">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title text-primary">Total Pickings</h6>
                        <h3 id="total-pickings">-</h3>
                    </div>
                    <i class="bi bi-list-check text-primary summary-card__icon" style="font-size: 2rem;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm summary-card summary-card--green">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title text-success">Completed Today</h6>
                        <h3 id="completed-today">-</h3>
                    </div>
                    <i class="bi bi-check-circle text-success summary-card__icon" style="font-size: 2rem;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm summary-card summary-card--amber">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title text-warning-emphasis">Pending</h6>
                        <h3 id="pending-pickings">-</h3>
                    </div>
                    <i class="bi bi-clock text-warning-emphasis summary-card__icon" style="font-size: 2rem;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm summary-card summary-card--cyan">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title text-info-emphasis">Items Shipped</h6>
                        <h3 id="total-items">-</h3>
                    </div>
                    <i class="bi bi-box text-info-emphasis summary-card__icon" style="font-size: 2rem;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

@if($pickingLists->count() > 0)
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-list-check me-2"></i>Warehouse to Customer Picking Records
            </h5>
        </div>
        <div class="card-body" style="padding: 0 !important; background: white !important;">
            <div style="background: white !important; overflow: hidden !important;">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Picking #</th>
                                <th>Order #</th>
                                <th>From Location</th>
                                <th>Status</th>
                                <th>Items</th>
                                <th>Progress</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pickingLists as $pickingList)
                            <tr>
                                <td data-label="Picking #">{{ $pickingList->picking_number }}</td>
                                <td data-label="Order #">{{ $pickingList->reference_id }}</td>
                                <td data-label="From Location">{{ $pickingList->fromLocation->name }}</td>
                                <td data-label="Status">
                                    <span class="badge bg-{{ $pickingList->status === 'completed' ? 'success' : ($pickingList->status === 'processing' ? 'primary' : 'warning') }}">
                                        {{ ucfirst($pickingList->status) }}
                                    </span>
                                </td>
                                <td data-label="Items">
                                    {{ $pickingList->total_items }}
                                </td>
                                <td data-label="Progress">
                                    <div class="progress" style="height: 6px; width: 100px;">
                                        <div class="progress-bar bg-{{ $pickingList->progress_percentage == 100 ? 'success' : 'info' }}" role="progressbar" style="width: {{ $pickingList->progress_percentage }}%;"></div>
                                    </div>
                                    <small>{{ number_format($pickingList->progress_percentage, 0) }}%</small>
                                </td>
                                <td data-label="Created At">{{ $pickingList->created_at->format('M d, Y H:i') }}</td>
                                <td data-label="Actions">
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('customer-picking.show', $pickingList) }}" class="btn btn-sm btn-info">View</a>
                                        @if($pickingList->status !== 'completed')
                                        <form action="{{ route('customer-picking.update-status', $pickingList) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn btn-sm btn-success">Mark Completed</button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center">No picking lists found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@else
    <!-- Enhanced Empty State -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-house-arrow-right" style="font-size: 4rem; color: #6c757d;"></i>
                    <h4 class="mt-3 text-muted">No Warehouse to Customer Pickings Yet</h4>
                    <p class="text-muted mb-4">
                        Picking records will appear here when customers place orders with warehouse fulfillment.<br>
                        When an order is completed, the system automatically creates picking records.
                    </p>
                    
                    @if($warehouses->count() > 0 && $customers->count() > 0)
                        <div class="alert alert-light border">
                            <h6 class="text-success">
                                <i class="bi bi-check-circle me-1"></i>System Ready
                            </h6>
                            <p class="mb-2">You have <strong>{{ $warehouses->count() }}</strong> warehouse(s) and <strong>{{ $customers->count() }}</strong> customer(s) configured.</p>
                            <p class="mb-0">Create an order with warehouse fulfillment to see picking records here.</p>
                        </div>
                    @else
                        <div class="alert alert-warning border">
                            <h6 class="text-warning">
                                <i class="bi bi-exclamation-triangle me-1"></i>Setup Required
                            </h6>
                            @if($warehouses->count() == 0)
                                <p class="mb-2">No warehouses configured. <a href="{{ route('stock-locations.create') }}">Create your first warehouse</a></p>
                            @endif
                            @if($customers->count() == 0)
                                <p class="mb-0">No customers configured. <a href="{{ route('customers.create') }}">Create your first customer</a></p>
                            @endif
                        </div>
                    @endif
                    
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <a href="{{ route('orders.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i> Create New Order
                        </a>
                        @if($warehouses->count() == 0)
                            <a href="{{ route('stock-locations.create') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-house-plus me-1"></i> Add Warehouse
                            </a>
                        @endif
                        @if($customers->count() == 0)
                            <a href="{{ route('customers.create') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-person-plus me-1"></i> Add Customer
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Warehouses List -->
            @if($warehouses->count() > 0)
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-house-fill me-1"></i>Available Warehouses
                        </h6>
                    </div>
                    <div class="card-body">
                        @foreach($warehouses->take(3) as $warehouse)
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-house-fill me-2 text-primary"></i>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <strong>{{ $warehouse->name }}</strong>
                                        <span class="badge bg-primary-subtle text-primary-emphasis ms-2">
                                            <i class="bi bi-box me-1"></i>Active
                                        </span>
                                    </div>
                                    @if($warehouse->is_default)
                                        <span class="badge bg-primary ms-1">Default</span>
                                    @endif
                                    @if($warehouse->address)
                                        <br><small class="text-muted">{{ Str::limit($warehouse->address, 30) }}</small>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        @if($warehouses->count() > 3)
                            <div class="text-center mt-3">
                                <a href="{{ route('stock-locations.index') }}" class="btn btn-sm btn-outline-primary">
                                    View All {{ $warehouses->count() }} Warehouses
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
            
            <!-- Customers List -->
            @if($customers->count() > 0)
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-people me-1"></i>Recent Customers
                        </h6>
                    </div>
                    <div class="card-body">
                        @foreach($customers->take(5) as $customer)
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-person me-2 text-primary"></i>
                                <div class="flex-grow-1">
                                    <strong>{{ $customer->name }}</strong>
                                    @if($customer->phone)
                                        <br><small class="text-muted">{{ $customer->phone }}</small>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        @if($customers->count() > 5)
                            <div class="text-center mt-3">
                                <a href="{{ route('customers.index') }}" class="btn btn-sm btn-outline-primary">
                                    View All {{ $customers->count() }} Customers
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif

</div>
</div>

@endsection

@section('scripts')
<script>
function loadStatistics() {
    const statisticsCards = document.getElementById('statistics-cards');
    
    if (statisticsCards.style.display === 'none') {
        fetch('{{ route("warehouse-to-customer-picking.statistics") }}')
            .then(response => response.json())
            .then(data => {
                document.getElementById('total-pickings').textContent = data.total_pickings || '0';
                document.getElementById('completed-today').textContent = data.completed_today || '0';
                document.getElementById('pending-pickings').textContent = data.pending_pickings || '0';
                document.getElementById('total-items').textContent = data.total_items_shipped || '0';
                
                statisticsCards.style.display = 'block';
            })
            .catch(error => {
                console.error('Error loading statistics:', error);
                alert('Error loading statistics');
            });
    } else {
        statisticsCards.style.display = 'none';
    }
}
</script>
@endsection

@push('styles')
<style>
/* White container holding the summary cards */
.summary-panel {
    background-color: #ffffff;
    border: 1px solid var(--border-color, #e9ecef);
    border-radius: 8px;
    padding: 1.25rem;
    box-shadow: var(--card-shadow, 0 2px 15px rgba(0, 0, 0, 0.04));
}

/* Summary cards - soft gradient treatment */
.summary-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.summary-card:hover {
    transform: translateY(-2px);
}

.summary-card--blue  { background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%); }
.summary-card--green { background: linear-gradient(135deg, #e8f5e9 0%, #ffffff 100%); }
.summary-card--amber { background: linear-gradient(135deg, #fff8e1 0%, #ffffff 100%); }
.summary-card--cyan  { background: linear-gradient(135deg, #e0f7fa 0%, #ffffff 100%); }

.summary-card__icon {
    opacity: 0.45;
}
</style>
@endpush 
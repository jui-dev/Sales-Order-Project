@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <x-breadcrumb :items="[
        ['label' => 'Stock Management', 'url' => '#'],
        ['label' => 'Stock Locations', 'url' => '#']
    ]" />
    
    <style>
    .page-container {
        padding: 1.5rem;
        max-width: 1400px;
        margin: 0 auto;
    }

    .page-header {
        margin-bottom: 2rem;
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .page-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #1f2937;
    }

    .add-location-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        background: var(--primary);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 2px 4px rgba(44, 110, 73, 0.2);
    }

    .add-location-btn:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(44, 110, 73, 0.3);
    }

    .add-location-btn i {
        font-size: 0.875rem;
        transition: transform 0.3s ease;
    }

    .add-location-btn:hover i {
        transform: rotate(90deg);
    }

    .search-bar {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        padding: 0.75rem 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 2rem;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .search-bar i {
        color: #6b7280;
    }

    .search-input {
        flex: 1;
        border: none;
        outline: none;
        font-size: 0.875rem;
        color: #1f2937;
    }

    .search-input::placeholder {
        color: #9ca3af;
    }

    .locations-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .section-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .section-title i {
        color: #6b7280;
    }

    .location-card {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        overflow: hidden;
    }

    .card-header {
        padding: 1rem;
        border-bottom: 1px solid #e5e7eb;
        position: relative;
    }

    .location-name {
        font-size: 1rem;
        font-weight: 500;
        color: #1f2937;
        margin-bottom: 0.5rem;
    }

    .location-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        font-size: 0.75rem;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        background: #f3f4f6;
        color: #4b5563;
    }

    .status-badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .status-active {
        background: #dcfce7;
        color: #15803d;
    }

    .status-inactive {
        background: #fee2e2;
        color: #991b1b;
    }

    .card-body {
        padding: 1rem;
    }

    .info-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .info-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.875rem;
        color: #4b5563;
    }

    .info-item i {
        color: #6b7280;
        width: 1rem;
        text-align: center;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
        padding: 0.75rem;
        background: #f9fafb;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
    }

    .stat-item {
        text-align: center;
        padding: 0.5rem;
    }

    .stat-value {
        font-size: 1.125rem;
        font-weight: 600;
        color: #1f2937;
    }

    .stat-label {
        font-size: 0.75rem;
        color: #6b7280;
        margin-top: 0.25rem;
    }

    .card-actions {
        display: flex;
        gap: 0.75rem;
        padding: 1rem;
        background: #f8fafc;
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
    }

    .action-btn {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.625rem;
        padding: 0.625rem 1rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .action-btn::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 120%;
        height: 120%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, transparent 50%);
        transform: translate(-50%, -50%) scale(0);
        transition: transform 0.5s ease;
        pointer-events: none;
    }

    .action-btn:hover::before {
        transform: translate(-50%, -50%) scale(2);
    }

    .action-btn i {
        font-size: 0.875rem;
        transition: transform 0.2s ease;
    }

    .action-btn:hover i {
        transform: scale(1.1);
    }

    .btn-view {
        background: white;
        color: var(--medium-text);
        border: 1px solid var(--border-color);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .btn-view:hover {
        background: var(--light-bg);
        border-color: var(--border-color);
        color: var(--dark-text);
    }

    .btn-edit {
        background: var(--primary);
        color: white;
        box-shadow: 0 2px 4px rgba(44, 110, 73, 0.2);
    }

    .btn-edit:hover {
        background: var(--primary-dark);
        box-shadow: 0 4px 6px rgba(44, 110, 73, 0.3);
        transform: translateY(-1px);
    }

    .btn-delete {
        background: white;
        color: var(--danger);
        border: 1px solid rgba(231, 111, 81, 0.2);
    }

    .btn-delete:hover {
        background: rgba(231, 111, 81, 0.1);
        border-color: rgba(231, 111, 81, 0.3);
        color: #d63030;
    }

    .btn-delete:focus {
        box-shadow: 0 0 0 3px rgba(231, 111, 81, 0.1);
    }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        background: white;
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
    }

    .empty-state i {
        font-size: 2rem;
        color: #9ca3af;
        margin-bottom: 1rem;
    }

    .empty-state h3 {
        font-size: 1.125rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 0.5rem;
    }

    .empty-state p {
        color: #6b7280;
        margin-bottom: 1.5rem;
        font-size: 0.875rem;
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 1rem;
        }

        .header-content {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }

        .add-location-btn {
            width: 100%;
            justify-content: center;
        }

        .locations-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .card-actions {
            flex-direction: column;
        }

        .action-btn {
            width: 100%;
        }
    }

    @media (hover: hover) {
        .action-btn:hover {
            transform: translateY(-1px);
        }
    }

    .section-nav {
        background: #f8fafc;
        padding: 1rem 1.5rem;
        border-radius: 0.5rem;
        margin-bottom: 2rem;
        border: 1px solid #e5e7eb;
    }

    .warehouse-nav {
        background: #f0fdf4;  /* Light green */
    }

    .retail-nav {
        background: #f0fdf4;  /* Light green */
    }

    .section-nav-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .section-nav-subtitle {
        font-size: 0.875rem;
        color: #6b7280;
        margin-bottom: 1rem;
    }

    .section-nav i {
        color: #6b7280;
    }
</style>

<div class="page-container">
    <div class="page-header">
        <div class="header-content">
            <h1 class="page-title">Stock Locations</h1>
            <a href="{{ route('stock-locations.create') }}" class="add-location-btn">
                <i class="fas fa-plus"></i>
                Add New Location
            </a>
        </div>
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="locationSearch" class="search-input" placeholder="Search locations...">
        </div>
    </div>

    @if($locations->isEmpty())
        <div class="empty-state">
            <i class="fas fa-warehouse"></i>
            <h3>No Stock Locations Yet</h3>
            <p>Create your first location to start managing your inventory across different sites.</p>
            <a href="{{ route('stock-locations.create') }}" class="add-location-btn">
                <i class="fas fa-plus"></i>
                Add Your First Location
            </a>
        </div>
    @else
        <!-- Warehouses Section -->
        <div class="section-nav warehouse-nav">
            <div class="section-nav-title">
                <i class="fas fa-warehouse"></i>
                Warehouses
            </div>
            <div class="section-nav-subtitle">
                Central storage facilities and distribution centers
            </div>
        </div>
        <div class="locations-grid">
            @foreach($locations->where('location_type', 'warehouse') as $location)
                <div class="location-card" data-location-name="{{ strtolower($location->name) }}">
                    <div class="card-header">
                        <div class="location-name">
                            {{ $location->name }}
                            <span class="badge bg-secondary ms-2">ID: {{ $location->id }}</span>
                        </div>
                        <div class="location-badge">
                            <i class="fas fa-warehouse"></i>
                            Warehouse
                        </div>
                        <div class="status-badge {{ $location->status === 'active' ? 'status-active' : 'status-inactive' }}">
                            <i class="fas fa-circle"></i>
                            {{ ucfirst($location->status) }}
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="info-list">
                            <div class="info-item">
                                <i class="fas fa-user"></i>
                                {{ $location->contact_person ?: 'No contact person' }}
                            </div>
                            <div class="info-item">
                                <i class="fas fa-phone"></i>
                                {{ $location->contact_number ?: 'No phone number' }}
                            </div>
                            <div class="info-item">
                                <i class="fas fa-envelope"></i>
                                {{ $location->email ?: 'No email' }}
                            </div>
                        </div>

                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-value">{{ $location->stockBalances ? $location->stockBalances->count() : 0 }}</div>
                                <div class="stat-label">Products</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">{{ $location->stockBalances ? $location->stockBalances->sum('quantity') : 0 }}</div>
                                <div class="stat-label">Stock</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">{{ $location->stockTransactions ? $location->stockTransactions->count() : 0 }}</div>
                                <div class="stat-label">Transactions</div>
                            </div>
                        </div>

                        <div class="card-actions">
                            <a href="{{ route('stock-locations.show', $location) }}" class="action-btn btn-view">
                                <i class="fas fa-eye"></i>
                                View
                            </a>
                            <a href="{{ route('stock-locations.edit', $location) }}" class="action-btn btn-edit">
                                <i class="fas fa-edit"></i>
                                Edit
                            </a>
                            @if(!$location->is_default)
                                <form action="{{ route('stock-locations.destroy', $location) }}" method="POST" style="flex: 1">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="action-btn btn-delete" onclick="return confirm('Are you sure you want to delete this location? This action cannot be undone.')">
                                        <i class="fas fa-trash"></i>
                                        Delete
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Retailers Section -->
        <div class="section-nav retail-nav">
            <div class="section-nav-title">
                <i class="fas fa-store"></i>
                Retail Partners
            </div>
            <div class="section-nav-subtitle">
                Partner stores and retail distribution points
            </div>
        </div>
        <div class="locations-grid">
            @foreach($locations->where('location_type', 'retailer') as $location)
                <div class="location-card" data-location-name="{{ strtolower($location->name) }}">
                    <div class="card-header">
                        <div class="location-name">
                            {{ $location->name }}
                            <span class="badge bg-secondary ms-2">ID: {{ $location->id }}</span>
                        </div>
                        <div class="location-badge">
                            <i class="fas fa-store"></i>
                            Retailer
                        </div>
                        <div class="status-badge {{ $location->status === 'active' ? 'status-active' : 'status-inactive' }}">
                            <i class="fas fa-circle"></i>
                            {{ ucfirst($location->status) }}
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="info-list">
                            <div class="info-item">
                                <i class="fas fa-user"></i>
                                {{ $location->contact_person ?: 'No contact person' }}
                            </div>
                            <div class="info-item">
                                <i class="fas fa-phone"></i>
                                {{ $location->contact_number ?: 'No phone number' }}
                            </div>
                            <div class="info-item">
                                <i class="fas fa-envelope"></i>
                                {{ $location->email ?: 'No email' }}
                            </div>
                        </div>

                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-value">{{ $location->stockBalances ? $location->stockBalances->count() : 0 }}</div>
                                <div class="stat-label">Products</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">{{ $location->stockBalances ? $location->stockBalances->sum('quantity') : 0 }}</div>
                                <div class="stat-label">Stock</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">{{ $location->stockTransactions ? $location->stockTransactions->count() : 0 }}</div>
                                <div class="stat-label">Transactions</div>
                            </div>
                        </div>

                        <div class="card-actions">
                            <a href="{{ route('stock-locations.show', $location) }}" class="action-btn btn-view">
                                <i class="fas fa-eye"></i>
                                View
                            </a>
                            <a href="{{ route('stock-locations.edit', $location) }}" class="action-btn btn-edit">
                                <i class="fas fa-edit"></i>
                                Edit
                            </a>
                            @if(!$location->is_default)
                                <form action="{{ route('stock-locations.destroy', $location) }}" method="POST" style="flex: 1">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="action-btn btn-delete" onclick="return confirm('Are you sure you want to delete this location? This action cannot be undone.')">
                                        <i class="fas fa-trash"></i>
                                        Delete
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('locationSearch');
    const locationCards = document.querySelectorAll('.location-card');

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        locationCards.forEach(card => {
            const locationName = card.dataset.locationName;
            if (locationName.includes(searchTerm)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
});
</script>
@endsection 
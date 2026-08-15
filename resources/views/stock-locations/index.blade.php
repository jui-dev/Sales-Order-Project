@extends('layouts.app')

@section('page-header')
<div class="header-content">
    <h1 class="page-title">Stock Locations</h1>
    <a href="{{ route('stock-locations.create') }}" class="add-location-btn">
        <i class="fas fa-plus"></i>
        Add New Location
    </a>
</div>
@endsection

@section('content')
<div class="container-fluid">
    
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

        .section-nav {
            padding: 1rem 1rem 0.75rem;
        }

        .section-content {
            padding: 0 1rem 1rem;
        }
    }

    .location-section {
        background: var(--light-panel);
        border: 1px solid rgba(44, 110, 73, 0.12);
        border-radius: 0.5rem;
        overflow: hidden;
        margin-bottom: 2rem;
    }

    .section-nav {
        padding: 1.5rem 1.5rem 1rem;
    }

    .section-content {
        padding: 0 1.5rem 1.5rem;
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
    }

    .section-nav i {
        color: var(--primary);
    }
</style>

<div class="page-container">
    <div class="page-header">
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
        @php
            $warehouses = $locations->where('location_type', 'warehouse');
            $retailers = $locations->where('location_type', 'retailer');
        @endphp

        <!-- Warehouses Section -->
        @if($warehouses->isNotEmpty())
        <div class="location-section">
            <div class="section-nav warehouse-nav">
                <div class="section-nav-title">
                    <i class="fas fa-warehouse"></i>
                    Warehouses
                </div>
                <div class="section-nav-subtitle">
                    Central storage facilities and distribution centers
                </div>
            </div>
            <div class="section-content">
                @include('stock-locations.partials.location-table', ['locations' => $warehouses])
            </div>
        </div>
        @endif

        <!-- Retailers Section -->
        @if($retailers->isNotEmpty())
        <div class="location-section">
            <div class="section-nav retail-nav">
                <div class="section-nav-title">
                    <i class="fas fa-store"></i>
                    Retail Partners
                </div>
                <div class="section-nav-subtitle">
                    Partner stores and retail distribution points
                </div>
            </div>
            <div class="section-content">
                @include('stock-locations.partials.location-table', ['locations' => $retailers])
            </div>
        </div>
        @endif
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('locationSearch');
    const locationRows = document.querySelectorAll('.location-row');
    const locationSections = document.querySelectorAll('.location-section');

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();

        locationRows.forEach(row => {
            const locationName = row.dataset.locationName;
            row.style.display = locationName.includes(searchTerm) ? '' : 'none';
        });

        // Hide a whole section once none of its rows match
        locationSections.forEach(section => {
            const hasVisibleRow = Array.from(section.querySelectorAll('.location-row'))
                .some(row => row.style.display !== 'none');
            section.style.display = hasVisibleRow ? '' : 'none';
        });
    });
});
</script>
</div>
@endsection 
@props([
    'searchPlaceholder' => 'Search...',
    'showFilters' => true,
    'showSort' => true,
    'filterOptions' => [],
    'sortOptions' => [],
    'defaultSort' => 'id',
    'defaultDirection' => 'desc'
])

@php
    // Keys this page actually filters on. Derived from $filterOptions so pages with
    // their own fields (warehouse_id, vendor_id, ...) get the same badge / Clear All
    // treatment as the legacy hard-coded ones.
    $filterKeys = [];
    foreach ($filterOptions as $field => $config) {
        if (($config['type'] ?? null) === 'date_range') {
            $filterKeys[] = $field . '_from';
            $filterKeys[] = $field . '_to';
        } else {
            $filterKeys[] = $field;
        }
    }
    $filterKeys = array_values(array_unique(array_merge(
        $filterKeys,
        ['status', 'date_from', 'date_to', 'type', 'category']
    )));

    // Everything that counts as "the list is filtered", including search and ranges
    $activeKeys = array_values(array_unique(array_merge(
        $filterKeys,
        ['search', 'price_min', 'price_max', 'stock_min', 'stock_max']
    )));
@endphp

<div class="unified-search-container card mb-4">
    <div class="card-body">
    <!-- Search and Controls Row -->
    <div class="row align-items-center mb-0">
        <!-- Global Search -->
        <div class="col-md-6 col-lg-4">
            <div class="input-group">
                <span class="input-group-text search-icon">
                    <i class="bi bi-search"></i>
                </span>
                <input 
                    type="text" 
                    id="global-search" 
                    class="form-control" 
                    placeholder="{{ $searchPlaceholder }}"
                    value="{{ request('search') }}"
                    autocomplete="off"
                >
                @if(request('search'))
                    <button class="btn btn-outline-secondary clear-search" type="button" title="Clear search">
                        <i class="bi bi-x"></i>
                    </button>
                @endif
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="col-md-6 col-lg-8">
            <div class="d-flex justify-content-end gap-2">
                @if($showFilters)
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="bi bi-funnel me-1"></i> Filter
                        @if(request()->hasAny($filterKeys))
                            <span class="badge bg-primary ms-1 active-filters-badge">{{ count(array_filter(request()->only($filterKeys))) }}</span>
                        @endif
                    </button>
                @endif

                @if($showSort)
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-sort-down me-1"></i> Sort
                        </button>
                        <ul class="dropdown-menu">
                            @foreach($sortOptions as $value => $label)
                                <li>
                                    <a class="dropdown-item sort-option" href="#" data-sort="{{ $value }}" data-direction="asc">
                                        {{ $label }} (A-Z)
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item sort-option" href="#" data-sort="{{ $value }}" data-direction="desc">
                                        {{ $label }} (Z-A)
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(request()->hasAny(array_merge($activeKeys, ['sort', 'direction'])))
                    <a href="{{ request()->url() }}" class="btn btn-outline-secondary clear-filters">
                        <i class="bi bi-x-circle me-1"></i> Clear All
                    </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Active Filters Display -->
    @if(request()->hasAny($activeKeys))
        <div class="active-filters-display mt-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <small class="text-muted me-2">Active filters:</small>
                
                @if(request('search'))
                    <span class="badge bg-primary">
                        Search: "{{ request('search') }}"
                        <a href="{{ request()->url() }}?{{ http_build_query(request()->except('search')) }}" class="text-white text-decoration-none ms-1">×</a>
                    </span>
                @endif

                @if(request('status'))
                    <span class="badge bg-info">
                        Status: {{ ucfirst(request('status')) }}
                        <a href="{{ request()->url() }}?{{ http_build_query(request()->except('status')) }}" class="text-white text-decoration-none ms-1">×</a>
                    </span>
                @endif

                @if(request('date_from') || request('date_to'))
                    <span class="badge bg-warning text-dark">
                        Date: {{ request('date_from', 'Any') }} to {{ request('date_to', 'Any') }}
                        <a href="{{ request()->url() }}?{{ http_build_query(request()->except(['date_from', 'date_to'])) }}" class="text-dark text-decoration-none ms-1">×</a>
                    </span>
                @endif

                @if(request('type'))
                    <span class="badge bg-success">
                        Type: {{ ucfirst(request('type')) }}
                        <a href="{{ request()->url() }}?{{ http_build_query(request()->except('type')) }}" class="text-white text-decoration-none ms-1">×</a>
                    </span>
                @endif

                @if(request('category'))
                    <span class="badge bg-secondary">
                        Category: {{ ucfirst(request('category')) }}
                        <a href="{{ request()->url() }}?{{ http_build_query(request()->except('category')) }}" class="text-white text-decoration-none ms-1">×</a>
                    </span>
                @endif

                @if(request('price_min') || request('price_max'))
                    <span class="badge bg-info">
                        Price: ${{ request('price_min', '0') }} - ${{ request('price_max', '∞') }}
                        <a href="{{ request()->url() }}?{{ http_build_query(request()->except(['price_min', 'price_max'])) }}" class="text-white text-decoration-none ms-1">×</a>
                    </span>
                @endif

                @if(request('stock_min') || request('stock_max'))
                    <span class="badge bg-warning text-dark">
                        Stock: {{ request('stock_min', '0') }} - {{ request('stock_max', '∞') }}
                        <a href="{{ request()->url() }}?{{ http_build_query(request()->except(['stock_min', 'stock_max'])) }}" class="text-dark text-decoration-none ms-1">×</a>
                    </span>
                @endif

                {{-- Page-specific filters (warehouse, vendor, ...) not covered by the badges above --}}
                @foreach($filterOptions as $field => $config)
                    @continue(in_array($field, ['search', 'status', 'type', 'category', 'date_from', 'date_to', 'price_min', 'price_max', 'stock_min', 'stock_max'], true))
                    @continue(!request()->filled($field))
                    @php
                        $activeValue = request($field);
                        $activeLabel = $config['label'] ?? ucfirst(str_replace('_', ' ', $field));
                        $activeDisplay = ($config['type'] ?? null) === 'select'
                            ? ($config['options'][$activeValue] ?? $activeValue)
                            : $activeValue;
                    @endphp
                    <span class="badge bg-secondary">
                        {{ $activeLabel }}: {{ $activeDisplay }}
                        <a href="{{ request()->url() }}?{{ http_build_query(request()->except($field)) }}" class="text-white text-decoration-none ms-1">×</a>
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Loading Spinner -->
    <div class="loading-spinner text-center py-4" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="text-muted mt-2">Updating results...</p>
    </div>
    </div>
</div>

@if($showFilters)
    <!-- Filter Modal -->
    <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="filterModalLabel">
                        <i class="bi bi-funnel me-2"></i>Filter Options
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="GET" id="filter-form">
                    <div class="modal-body">
                        <!-- Preserve existing search -->
                        @if(request('search'))
                            <input type="hidden" name="search" value="{{ request('search') }}">
                        @endif

                        <!-- Preserve existing sort -->
                        @if(request('sort'))
                            <input type="hidden" name="sort" value="{{ request('sort') }}">
                        @endif
                        @if(request('direction'))
                            <input type="hidden" name="direction" value="{{ request('direction') }}">
                        @endif

                        <!-- Dynamic Filter Fields -->
                        @foreach($filterOptions as $field => $config)
                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ $config['label'] ?? ucfirst($field) }}</label>
                                
                                @if($config['type'] === 'select')
                                    <select name="{{ $field }}" class="form-select">
                                        <option value="">-- Any --</option>
                                        @foreach($config['options'] as $value => $label)
                                            <option value="{{ $value }}" {{ request($field) == $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                @elseif($config['type'] === 'date')
                                    <input type="date" name="{{ $field }}" class="form-control" value="{{ request($field) }}">
                                @elseif($config['type'] === 'date_range')
                                    <div class="row">
                                        <div class="col-6">
                                            <input type="date" name="{{ $field }}_from" class="form-control" 
                                                   placeholder="From" value="{{ request($field . '_from') }}">
                                        </div>
                                        <div class="col-6">
                                            <input type="date" name="{{ $field }}_to" class="form-control" 
                                                   placeholder="To" value="{{ request($field . '_to') }}">
                                        </div>
                                    </div>
                                @else
                                    <input type="text" name="{{ $field }}" class="form-control" 
                                           placeholder="{{ $config['placeholder'] ?? 'Enter ' . ucfirst($field) }}"
                                           value="{{ request($field) }}">
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <a href="{{ request()->url() }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise me-1"></i>Clear All Filters
                        </a>
                        <div>
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">
                                <i class="bi bi-x me-1"></i>Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel me-1"></i>Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<!-- Hidden Sort Form -->
@if($showSort)
    <form method="GET" id="sort-form" style="display: none;">
        @foreach(request()->except(['sort', 'direction', 'page']) as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <input type="hidden" name="sort" value="{{ request('sort', $defaultSort) }}">
        <input type="hidden" name="direction" value="{{ request('direction', $defaultDirection) }}">
    </form>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Clear search button
    const clearSearchBtn = document.querySelector('.clear-search');
    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function() {
            document.getElementById('global-search').value = '';
            window.location.href = '{{ request()->url() }}?{{ http_build_query(request()->except("search")) }}';
        });
    }

    // Sort options
    const sortOptions = document.querySelectorAll('.sort-option');
    sortOptions.forEach(option => {
        option.addEventListener('click', function(e) {
            e.preventDefault();
            const sort = this.dataset.sort;
            const direction = this.dataset.direction;
            
            const sortForm = document.getElementById('sort-form');
            if (sortForm) {
                sortForm.querySelector('input[name="sort"]').value = sort;
                sortForm.querySelector('input[name="direction"]').value = direction;
                sortForm.submit();
            }
        });
    });
});
</script>

<!-- Include products filter script if on products page -->
@if(request()->routeIs('products.*'))
<script src="{{ asset('js/products-filter.js') }}"></script>
@endif
@endpush 
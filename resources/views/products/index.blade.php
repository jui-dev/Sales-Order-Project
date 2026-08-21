@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Products</h1>
        <a href="{{ route('products.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Add New Product
        </a>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    
    

    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle"></i>
        <strong>Product Management:</strong> Products are managed through supplies and orders. Stock levels are automatically updated based on supply and order transactions.
    </div>

    <!-- Unified Search Component -->
    <x-unified-search 
        :searchPlaceholder="'Search products by name, SKU, or description...'"
        :filterOptions="$filterOptions"
        :sortOptions="$sortOptions"
        :defaultSort="'id'"
        :defaultDirection="'desc'"
    />

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Selling Price</th>
                            <th>Available Stocks</th>
                            <th>Purchase Price</th>
                            <th>GP</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            <tr>
                                <td>{{ $product->id }}</td>
                                <td>
                                    <strong>{{ $product->name }}</strong>
                                    @if($product->sku)
                                        <br><small class="text-muted">SKU: {{ $product->sku }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($product->category)
                                        <span class="badge bg-info">{{ $product->category->full_path }}</span>
                                    @else
                                        <span class="text-muted">No Category</span>
                                    @endif
                                </td>
                                <td>
                                    {{-- The price in force on the default sale list. A product with
                                         none is not priced at zero - it has no agreed price, and the
                                         order form derives one from cost until somebody sets it. --}}
                                    @if($product->current_price !== null)
                                        <strong class="text-success">${{ number_format((float) $product->current_price, 2) }}</strong>
                                    @else
                                        <span class="badge bg-warning text-dark">Not priced</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ ($product->available_stocks ?? 0) > 0 ? 'success' : 'danger' }}">
                                        {{ $product->available_stocks ?? 0 }}
                                    </span>
                                    <small class="text-muted d-block mt-1">
                                        From {{ $product->locations_count ?? 0 }} location(s)
                                    </small>
                                </td>
                                <td>
                                    {{-- The weighted average the stock on hand is carried at, not
                                         the last delivery's price. --}}
                                    <small class="text-muted">
                                        {{ $product->current_cost !== null ? '$' . number_format((float) $product->current_cost, 2) : '-' }}
                                    </small>
                                </td>
                                <td>
                                    {{-- Margin is derived from the two figures beside it, so it can no
                                         longer disagree with them the way the stored column did. A
                                         negative one means the product is priced below what the stock
                                         on hand actually cost - worth seeing, not worth hiding. --}}
                                    @if($product->current_price !== null && $product->current_cost !== null)
                                        @php($margin = (float) $product->current_price - (float) $product->current_cost)
                                        @if($margin < 0)
                                            <small class="text-danger fw-semibold" data-bs-toggle="tooltip"
                                                   title="Priced below the cost the stock on hand is carried at.">
                                                <i class="bi bi-exclamation-triangle-fill me-1"></i>${{ number_format($margin, 2) }}
                                            </small>
                                        @else
                                            <small class="text-info">${{ number_format($margin, 2) }}</small>
                                        @endif
                                    @else
                                        <small class="text-muted">-</small>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="{{ route('products.show', $product) }}" class="btn btn-sm btn-info d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-warning d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="Edit Product">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="{{ route('picking.product-transaction-history', $product) }}" class="btn btn-sm btn-secondary d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="Transaction History">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        <a href="{{ route('products.stock-analysis', $product) }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="Stock Analysis">
                                            <i class="bi bi-graph-up"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1"
                                            data-bs-toggle="tooltip" title="Delete Product"
                                            onclick="if(confirm('Are you sure you want to delete this product?')) { 
                                                document.getElementById('delete-product-{{ $product->id }}').submit(); 
                                            }">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <form id="delete-product-{{ $product->id }}" 
                                        action="{{ route('products.destroy', $product) }}" 
                                        method="POST" 
                                        style="display: none;">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox display-1 d-block mb-3"></i>
                                        <h5>No Products Found</h5>
                                        <p class="mb-0">No products match your current search criteria.</p>
                                                                @if(request()->hasAny(['search', 'price_min', 'price_max', 'stock_min', 'stock_max']))
                            <a href="{{ route('products.index') }}" class="btn btn-outline-primary mt-2">
                                <i class="bi bi-arrow-clockwise me-1"></i>Clear Filters
                            </a>
                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$products" />
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
@endsection 
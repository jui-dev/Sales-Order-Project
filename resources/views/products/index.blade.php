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
        :sortDirections="$sortDirections"
        :defaultSort="'id'"
        :defaultDirection="'desc'"
    />

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            {{-- Every column the database can actually order by is
                                 clickable. The two below it are not: margin is derived
                                 per row and MTD profit is looked up for the page after
                                 it has been paginated, so neither exists at the point
                                 the order is decided. A dead sort arrow on them would
                                 promise something the query cannot do. --}}
                            <x-sortable-header field="id" :default-direction="$sortDirections['id']" is-default>ID</x-sortable-header>
                            <x-sortable-header field="name" :default-direction="$sortDirections['name']">Name</x-sortable-header>
                            <x-sortable-header field="category" :default-direction="$sortDirections['category']">Category</x-sortable-header>
                            <x-sortable-header field="selling_price" :default-direction="$sortDirections['selling_price']" class="text-end">Selling Price</x-sortable-header>
                            <x-sortable-header field="available_stocks" :default-direction="$sortDirections['available_stocks']">Available Stocks</x-sortable-header>
                            <x-sortable-header field="purchase_price" :default-direction="$sortDirections['purchase_price']" class="text-end">Purchase Price</x-sortable-header>
                            {{-- Two different questions, so two columns. The first is a
                                 property of the price list; the second is a property of
                                 what actually happened. Calling both "GP" was what made a
                                 returned product look like it was still earning. --}}
                            {{-- Not clickable, but laid out exactly like the headers that
                                 are: right-aligned over their figures, held on one line,
                                 and the hint icon spaced by the same flex gap that carries
                                 a sort arrow. A header row that only lines up on six of its
                                 nine columns reads as a mistake in the table. --}}
                            <th class="text-end text-nowrap" data-bs-toggle="tooltip"
                                title="List price minus the weighted-average cost of the stock on hand. A catalogue figure - it does not move when goods are sold or returned.">
                                <span class="d-inline-flex align-items-center gap-1">
                                    Margin/unit<i class="bi bi-info-circle text-muted small"></i>
                                </span>
                            </th>
                            <th class="text-end text-nowrap" data-bs-toggle="tooltip"
                                title="Profit actually earned this month according to the ledger, net of anything returned.">
                                <span class="d-inline-flex align-items-center gap-1">
                                    GP (MTD)<i class="bi bi-info-circle text-muted small"></i>
                                </span>
                            </th>
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
                                <td class="text-end">
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
                                <td class="text-end">
                                    {{-- The weighted average the stock on hand is carried at, not
                                         the last delivery's price. --}}
                                    <small class="text-muted">
                                        {{ $product->current_cost !== null ? '$' . number_format((float) $product->current_cost, 2) : '-' }}
                                    </small>
                                </td>
                                <td class="text-end">
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
                                <td class="text-end">
                                    {{-- Realised profit, straight off the ledger. A product with
                                         no posted sale this month reads "-" rather than $0.00:
                                         not having sold and having sold at cost are different
                                         facts, and the old page could not tell them apart. --}}
                                    @if($product->realised_profit === null)
                                        <small class="text-muted" data-bs-toggle="tooltip"
                                               title="No posted sales this month.">-</small>
                                    @elseif($product->realised_profit < 0)
                                        <small class="text-danger fw-semibold" data-bs-toggle="tooltip"
                                               title="Sold for less than the goods cost, or more came back than went out.">
                                            <i class="bi bi-arrow-down-right me-1"></i>${{ number_format($product->realised_profit, 2) }}
                                        </small>
                                    @else
                                        <small class="text-success fw-semibold" data-bs-toggle="tooltip"
                                               title="On ${{ number_format((float) $product->realised_revenue, 2) }} of revenue, net of returns.">
                                            ${{ number_format($product->realised_profit, 2) }}
                                        </small>
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
                                <td colspan="9" class="text-center py-4">
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
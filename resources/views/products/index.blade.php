@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Products</h1>
        <a href="{{ route('products.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Add New Product
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="alert alert-info mb-4">
                <i class="bi bi-info-circle"></i>
                <strong>Product Management:</strong> Products are managed through supplies and orders. Stock levels are automatically updated based on supply and order transactions.
            </div>

            <!-- Sorting Controls -->
            <div class="d-flex justify-content-end mb-3">
                <div class="input-group input-group-sm" style="max-width: 320px;">
                    <label class="input-group-text bg-light" for="sort-by">Sort&nbsp;By</label>
                    <select id="sort-by" class="form-select">
                        <option value="0">ID</option>
                        <option value="1">Name</option>
                        <option value="2">Selling Price</option>
                        <option value="3">Available Stocks</option>
                        <option value="4">Purchase Price</option>
                        <option value="5">GP %</option>
                    </select>
                    <button class="btn btn-outline-secondary" id="sort-direction" data-dir="asc" title="Toggle Direction">
                        <i class="bi bi-sort-alpha-down"></i>
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="data-table" class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th style="width: 200px;">Name</th>
                            <th style="width: 100px;">Selling Price</th>
                            <th style="width: 120px;">Available Stocks</th>
                            <th style="width: 80px;">Purchase Price</th>
                            <th style="width: 70px;">GP%</th>
                            <th style="min-width: 320px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            <tr>
                                <td>{{ $product->id }}</td>
                                <td>
                                    <strong>{{ $product->name }}</strong>
                                </td>
                                <td>
                                    <strong class="text-success">${{ number_format($product->selling_price, 2) }}</strong>
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
                                    <small class="text-muted">${{ $product->purchase_price ? number_format($product->purchase_price, 2) : '-' }}</small>
                                </td>
                                <td>
                                    <small class="text-info">{{ $product->gp ? number_format($product->gp, 1) . '%' : '-' }}</small>
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
                                <td colspan="7" class="text-center">No products found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Setup DataTable for sortable columns & search
        const table = $('#data-table').DataTable({
            paging: true,
            ordering: true,
            info: true,
            lengthMenu: [10, 25, 50, 100],
            language: {
                search: "Filter records:",
                lengthMenu: "Show _MENU_ entries per page",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                paginate: {
                    previous: "Prev",
                    next: "Next"
                }
            }
        });

        // Apply custom sort controls
        $('#sort-by').on('change', function() {
            const colIdx = parseInt(this.value, 10);
            const dir = $('#sort-direction').data('dir');
            table.order([colIdx, dir]).draw();
        });

        $('#sort-direction').on('click', function() {
            const current = $(this).data('dir');
            const newDir = current === 'asc' ? 'desc' : 'asc';
            $(this).data('dir', newDir);
            $(this).find('i').toggleClass('bi-sort-alpha-down bi-sort-alpha-up');
            // Trigger change to apply new order
            $('#sort-by').trigger('change');
        });
    });
</script>
@endsection 
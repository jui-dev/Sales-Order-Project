@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <x-breadcrumb :items="[
        ['label' => 'Sales', 'url' => '#'],
        ['label' => 'Orders', 'url' => '#']
    ]" />
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Orders</h1>
            <p class="text-muted mb-0">Manage and track all customer orders</p>
        </div>
        <a href="{{ route('orders.create') }}" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i>Create New Order
        </a>
    </div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <!-- Sorting Controls -->
        <div class="d-flex justify-content-end mb-3">
            <div class="input-group input-group-sm" style="max-width: 320px;">
                <label class="input-group-text bg-light" for="sort-by">Sort&nbsp;By</label>
                <select id="sort-by" class="form-select">
                    <option value="0">ID</option>
                    <option value="1">Customer</option>
                    <option value="3">Total Items</option>
                    <option value="4">Total Amount</option>
                    <option value="5">Order Date</option>
                    <option value="6">Status</option>
                </select>
                <button class="btn btn-outline-secondary" id="sort-direction" data-dir="asc"><i class="bi bi-sort-alpha-down"></i></button>
            </div>
        </div>

        <table id="data-table" class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Products</th>
                    <th>Total Items</th>
                    <th>Total Amount</th>
                    <th>Order Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                <tr>
                    <td>#{{ $order->id }}</td>
                    <td>
                        <div>{{ $order->customer->name }}</div>
                        <small class="text-muted">{{ $order->customer->email }}</small>
                    </td>
                    <td>
                        <ul class="list-unstyled mb-0">
                            @foreach($order->orderItems->take(2) as $item)
                                <li>{{ $item->product->name }} ({{ $item->quantity }})</li>
                            @endforeach
                            @if($order->orderItems->count() > 2)
                                <li class="text-muted">+ {{ $order->orderItems->count() - 2 }} more...</li>
                            @endif
                        </ul>
                    </td>
                    <td>{{ $order->orderItems->sum('quantity') }}</td>
                    <td>${{ number_format($order->total_amount, 2) }}</td>
                    <td>
                        {{ $order->created_at->format('M d, Y') }}
                        <br>
                        <small class="text-muted">{{ $order->created_at->format('h:i A') }}</small>
                    </td>
                    <td>
                        @php
                            $statusColor = match($order->status) {
                                'confirmed' => 'success',
                                'processing' => 'primary',
                                'cancelled' => 'danger',
                                default => 'warning'
                            };
                        @endphp
                        <span class="badge bg-{{ $statusColor }}">
                            {{ ucfirst($order->status) }}
                        </span>
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            <a href="{{ route('orders.show', $order) }}" class="btn btn-sm btn-info d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Details">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('orders.edit', $order) }}" class="btn btn-sm btn-warning d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="Edit Order">
                                <i class="bi bi-pencil"></i>
                            </a>

                            @if($order->status === 'completed' && $order->invoice)
                                <a href="{{ route('invoices.show', $order->invoice) }}" target="_blank" class="btn btn-sm btn-success d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Invoice">
                                    <i class="bi bi-receipt"></i>
                                </a>
                            @endif
                            @if(! in_array($order->status, ['confirmed', 'completed', 'cancelled']))
                            <div class="dropdown d-inline">
                                <button class="btn btn-sm btn-secondary dropdown-toggle d-inline-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" data-bs-toggle="tooltip" title="Update Status">
                                    <i class="bi bi-gear"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    @if($order->status == 'pending')
                                    <li>
                                        <form action="{{ route('orders.update-status', $order) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="processing">
                                            <button type="submit" class="dropdown-item">Mark Processing</button>
                                        </form>
                                    </li>
                                    @endif
                                    <li>
                                        <form action="{{ route('orders.update-status', $order) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="confirmed">
                                            <button type="submit" class="dropdown-item">Mark Confirmed</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                            @endif
                            <button type="button" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1"
                                data-bs-toggle="tooltip" title="Delete Order"
                                onclick="if(confirm('Are you sure you want to delete this order?')) { 
                                    document.getElementById('delete-order-{{ $order->id }}').submit(); 
                                }">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <form id="delete-order-{{ $order->id }}" 
                            action="{{ route('orders.destroy', $order) }}" 
                            method="POST" 
                            style="display: none;">
                            @csrf
                            @method('DELETE')
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center">No orders found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
        <x-pagination :paginator="$orders" />
    </div>
</div>
@endsection

@section('styles')
<style>
    .table > :not(caption) > * > * {
        padding: 1rem 0.75rem;
    }
    .btn-light {
        background-color: #f8f9fa;
        border-color: #f0f0f0;
    }
    .btn-light:hover {
        background-color: #e9ecef;
        border-color: #e9ecef;
    }
    .badge {
        font-weight: 500;
        padding: 0.5em 0.75em;
    }
    .list-unstyled li {
        margin-bottom: 0.25rem;
    }
    .list-unstyled li:last-child {
        margin-bottom: 0;
    }
</style>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const table = $('#data-table').DataTable({
            paging: true,
            ordering: true,
            info: true,
            lengthMenu: [10, 25, 50, 100],
            language: {
                search: 'Filter:',
                lengthMenu: 'Show _MENU_ entries',
                info: 'Showing _START_ to _END_ of _TOTAL_ orders',
            }
        });

        $('#sort-by').on('change', function(){
            table.order([parseInt(this.value,10), $('#sort-direction').data('dir')]).draw();
        });

        $('#sort-direction').on('click', function(){
            const dir = $(this).data('dir') === 'asc' ? 'desc' : 'asc';
            $(this).data('dir', dir);
            $(this).find('i').toggleClass('bi-sort-alpha-down bi-sort-alpha-up');
            $('#sort-by').trigger('change');
        });
    });
</script>
@endsection 
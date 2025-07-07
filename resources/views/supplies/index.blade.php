@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Supplies</h1>
    <a href="{{ route('supplies.create') }}" class="btn btn-success">Record New Supply</a>
</div>

<div class="card">
    <div class="card-body">
        <!-- Sorting Controls -->
        <div class="d-flex justify-content-end mb-3">
            <div class="input-group input-group-sm" style="max-width: 300px;">
                <label class="input-group-text bg-light" for="sort-by">Sort&nbsp;By</label>
                <select id="sort-by" class="form-select">
                    <option value="0">ID</option>
                    <option value="1">Vendor</option>
                    <option value="3">Total Items</option>
                    <option value="4">Total Cost</option>
                    <option value="5">Supply Date</option>
                    <option value="6">Status</option>
                </select>
                <button class="btn btn-outline-secondary" id="sort-direction" data-dir="asc"><i class="bi bi-sort-alpha-down"></i></button>
            </div>
        </div>

        <table id="data-table" class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Vendor</th>
                    <th>Products</th>
                    <th>Total Items</th>
                    <th>Total Cost</th>
                    <th>Supply Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($supplies as $supply)
                <tr>
                    <td>{{ $supply->id }}</td>
                    <td>{{ $supply->vendor->name }}</td>
                    <td>
                        <ul class="list-unstyled mb-0">
                            @foreach($supply->items->take(2) as $item)
                                <li>{{ $item->product->name }} ({{ $item->quantity }})</li>
                            @endforeach
                            @if($supply->items->count() > 2)
                                <li class="text-muted">+ {{ $supply->items->count() - 2 }} more...</li>
                            @endif
                        </ul>
                    </td>
                    <td>{{ $supply->items->sum('quantity') }}</td>
                    <td>${{ number_format($supply->total_cost, 2) }}</td>
                    <td>{{ $supply->supply_date->format('M d, Y') }}</td>
                    <td>
                        <span class="badge bg-{{ match($supply->status) {
                            'pending'    => 'warning',
                            'processing' => 'primary',
                            'confirmed'  => 'info',
                            'completed'  => 'success',
                            default      => 'secondary'
                        } }}">
                            {{ ucfirst($supply->status) }}
                        </span>
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                            <a href="{{ route('supplies.show', $supply) }}" class="btn btn-sm btn-info">View</a>
                            @if($supply->status != 'completed')
                            <div class="dropdown d-inline">
                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    Update Status
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <form action="{{ route('supplies.completed', $supply) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="dropdown-item">Mark Completed</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center">No supply records found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
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
        const table = $('#data-table').DataTable({
            paging: true,
            ordering: true,
            info: true,
            lengthMenu: [10, 25, 50, 100],
            language: {
                search: 'Filter:',
                lengthMenu: 'Show _MENU_ entries',
                info: 'Showing _START_ to _END_ of _TOTAL_ supplies',
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
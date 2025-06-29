@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Customers</h1>
    <a href="{{ route('customers.create') }}" class="btn btn-success">Add New Customer</a>
</div>

<div class="card">
    <div class="card-body">
        <!-- Sorting Controls -->
        <div class="d-flex justify-content-end mb-3">
            <div class="input-group input-group-sm" style="max-width: 260px;">
                <label class="input-group-text bg-light" for="sort-by">Sort&nbsp;By</label>
                <select id="sort-by" class="form-select">
                    <option value="0">ID</option>
                    <option value="1">Name</option>
                    <option value="2">Email</option>
                </select>
                <button class="btn btn-outline-secondary" id="sort-direction" data-dir="asc"><i class="bi bi-sort-alpha-down"></i></button>
            </div>
        </div>

        <div class="table-responsive">
            <table id="data-table" class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                    <tr>
                        <td data-label="ID">{{ $customer->id }}</td>
                        <td data-label="Name">{{ $customer->name }}</td>
                        <td data-label="Email">{{ $customer->email ?? 'N/A' }}</td>
                        <td data-label="Phone">{{ $customer->phone ?? 'N/A' }}</td>
                        <td data-label="Actions">
                            <div class="btn-group" role="group">
                                <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-info">View</a>
                                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm btn-primary">Edit</a>
                                <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this customer?')">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center">No customers found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
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
        const table = $('#data-table').DataTable({
            paging: true,
            ordering: true,
            info: true,
            lengthMenu: [10, 25, 50, 100],
            language: {
                search: 'Filter:',
                lengthMenu: 'Show _MENU_ entries',
                info: 'Showing _START_ to _END_ of _TOTAL_ customers',
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
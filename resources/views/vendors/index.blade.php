@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <x-breadcrumb :items="[
        ['label' => 'Inventory', 'url' => '#'],
        ['label' => 'Vendors', 'url' => '#']
    ]" />
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Vendors</h1>
        <a href="{{ route('vendors.create') }}" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i>Add New Vendor
        </a>
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
                    <option value="2">Contact</option>
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
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vendors as $vendor)
                    <tr>
                        <td data-label="ID">{{ $vendor->id }}</td>
                        <td data-label="Name">{{ $vendor->name }}</td>
                        <td data-label="Contact Person">{{ $vendor->contact_person }}</td>
                        <td data-label="Email">{{ $vendor->email }}</td>
                        <td data-label="Phone">{{ $vendor->phone }}</td>
                        <td data-label="Actions">
                            <div class="d-flex flex-wrap gap-1">
                                <a href="{{ route('vendors.show', $vendor) }}" class="btn btn-sm btn-info d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('vendors.edit', $vendor) }}" class="btn btn-sm btn-warning d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="Edit Vendor">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1"
                                    data-bs-toggle="tooltip" title="Delete Vendor"
                                    onclick="if(confirm('Are you sure you want to delete this vendor?')) { 
                                        document.getElementById('delete-vendor-{{ $vendor->id }}').submit(); 
                                    }">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            <form id="delete-vendor-{{ $vendor->id }}" 
                                action="{{ route('vendors.destroy', $vendor) }}" 
                                method="POST" 
                                style="display: none;">
                                @csrf
                                @method('DELETE')
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center">No vendors found</td>
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
                info: 'Showing _START_ to _END_ of _TOTAL_ vendors',
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
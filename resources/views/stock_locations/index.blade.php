@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Stock Locations</h1>
    <div class="row">
        <div class="col-md-12">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Address</th>
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Code</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($locations as $location)
                    <tr>
                        <td>{{ $location->id }}</td>
                        <td>{{ $location->name }}</td>
                        <td>{{ $location->location_type }}</td>
                        <td>{{ $location->address }}</td>
                        <td>{{ $location->contact_person }}</td>
                        <td>{{ $location->phone }}</td>
                        <td>{{ $location->email }}</td>
                        <td>{{ $location->code }}</td>
                        <td>
                            <a href="{{ route('stock_locations.edit', $location->id) }}" class="btn btn-sm btn-primary">Edit</a>
                            <form action="{{ route('stock_locations.destroy', $location->id) }}" method="POST" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection 
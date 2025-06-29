@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Warehouse Receiving</h4>
                    <div>
                        <a href="{{ route('warehouse.receiving.pending') }}" class="btn btn-warning btn-sm">
                            <i class="fas fa-clock"></i> Pending Tasks
                        </a>
                        <a href="{{ route('warehouse.receiving.completed') }}" class="btn btn-success btn-sm">
                            <i class="fas fa-check"></i> Completed
                        </a>
                        <a href="{{ route('warehouse.receiving.report') }}" class="btn btn-info btn-sm">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    @if($receivingLists->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Picking #</th>
                                    <th>Supply #</th>
                                    <th>Vendor</th>
                                    <th>Items</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($receivingLists as $pickingList)
                                <tr>
                                    <td>
                                        <a href="{{ route('warehouse.receiving.show', $pickingList) }}">
                                            {{ $pickingList->picking_number }}
                                        </a>
                                    </td>
                                    <td>
                                        @if($pickingList->supply)
                                            <a href="{{ route('supplies.show', $pickingList->supply) }}">
                                                #{{ $pickingList->supply->id }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($pickingList->supply && $pickingList->supply->vendor)
                                            {{ $pickingList->supply->vendor->name }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary">
                                            {{ $pickingList->pickingItems->count() }} items
                                        </span>
                                    </td>
                                    <td>
                                        @if($pickingList->toLocation)
                                            {{ $pickingList->toLocation->name }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($pickingList->status === 'pending')
                                            <span class="badge badge-warning">Pending</span>
                                        @elseif($pickingList->status === 'completed')
                                            <span class="badge badge-success">Completed</span>
                                        @elseif($pickingList->status === 'cancelled')
                                            <span class="badge badge-danger">Cancelled</span>
                                        @else
                                            <span class="badge badge-secondary">{{ ucfirst($pickingList->status) }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $pickingList->created_at->format('M d, Y H:i') }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('warehouse.receiving.show', $pickingList) }}" 
                                               class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            @if($pickingList->status === 'pending')
                                                <form action="{{ route('warehouse.receiving.quick-receive', $pickingList) }}" 
                                                      method="POST" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-outline-success btn-sm" 
                                                            onclick="return confirm('Receive all items in full quantity?')">
                                                        <i class="fas fa-check-double"></i>
                                                    </button>
                                                </form>
                                                
                                                <form action="{{ route('warehouse.receiving.cancel', $pickingList) }}" 
                                                      method="POST" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" 
                                                            onclick="return confirm('Cancel this receiving task?')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $receivingLists->links() }}
                    </div>
                    @else
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5>No Receiving Tasks</h5>
                        <p class="text-muted">No vendor supply receiving tasks found.</p>
                        <a href="{{ route('supplies.index') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> View Supplies
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
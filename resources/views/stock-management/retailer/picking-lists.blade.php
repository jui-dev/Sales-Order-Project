@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Retailer to Customer Picking Lists</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Picking Number</th>
                                    <th>Order Number</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Items</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pickingLists as $pickingList)
                                <tr>
                                    <td>{{ $pickingList->picking_number }}</td>
                                    <td>{{ $pickingList->order->order_number }}</td>
                                    <td>{{ $pickingList->order->customer->name }}</td>
                                    <td>{{ $pickingList->picking_date->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <span class="badge bg-{{ $pickingList->status === 'completed' ? 'success' : 'warning' }}">
                                            {{ ucfirst($pickingList->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <ul class="list-unstyled mb-0">
                                            @foreach($pickingList->pickingItems as $item)
                                            <li>
                                                {{ $item->product->name }} - 
                                                {{ $item->quantity_picked }} / {{ $item->quantity_requested }}
                                            </li>
                                            @endforeach
                                        </ul>
                                    </td>
                                    <td>
                                        <a href="{{ route('picking-lists.show', $pickingList) }}" 
                                           class="btn btn-sm btn-info">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $pickingLists->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
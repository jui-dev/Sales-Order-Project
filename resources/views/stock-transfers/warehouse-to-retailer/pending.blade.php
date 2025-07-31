@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Pending Warehouse to Retailer Transfers</h4>
                    <div>
                        <a href="{{ route('stock-transfers.warehouse-to-retailer') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-list"></i> All Transfers
                        </a>
                        <a href="{{ route('stock-transfers.warehouse-to-retailer.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> New Transfer
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    @if($pendingTransfers->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Transfer #</th>
                                    <th>From Warehouse</th>
                                    <th>To Retailer</th>
                                    <th>Items</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingTransfers as $transfer)
                                <tr>
                                    <td>
                                        <a href="{{ route('stock-transfers.warehouse-to-retailer.show', $transfer) }}">
                                            {{ $transfer->picking_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">{{ $transfer->fromLocation->name }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-success">{{ $transfer->toLocation->name }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary">
                                            {{ $transfer->pickingItems->count() }} items
                                        </span>
                                        <small class="text-muted d-block">
                                            {{ $transfer->pickingItems ? $transfer->pickingItems->sum('quantity_requested') : 0 }} total qty
                                        </small>
                                    </td>
                                    <td>{{ $transfer->created_at->format('M d, Y H:i') }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('stock-transfers.warehouse-to-retailer.show', $transfer) }}" 
                                               class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye"></i> Process
                                            </a>
                                            
                                            <form action="{{ route('stock-transfers.warehouse-to-retailer.quick-complete', $transfer) }}" 
                                                  method="POST" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-outline-success btn-sm" 
                                                        onclick="return confirm('Complete transfer with all requested quantities?')">
                                                    <i class="fas fa-check-double"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-4">
                        <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                        <h5>No Pending Transfers</h5>
                        <p class="text-muted">All warehouse to retailer transfers have been processed.</p>
                        <a href="{{ route('stock-transfers.warehouse-to-retailer.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create New Transfer
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-arrow-return-left me-2"></i>Product Returns</h1>
    <div>
        <a href="{{ route('picking.transaction-flow') }}" class="btn btn-outline-info">
            <i class="bi bi-diagram-3 me-1"></i> Transaction Flow
        </a>
        <a href="{{ route('returns.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Create Return
        </a>
    </div>
</div>

@if($returns->count() > 0)
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Return Number</th>
                            <th>Type</th>
                            <th>Customer/Order</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Items</th>
                            <th>Refund Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($returns as $return)
                        <tr>
                            <td>
                                <strong>{{ $return->return_number }}</strong>
                            </td>
                            <td>
                                <span class="badge bg-{{ $return->return_type === 'customer_return' ? 'danger' : ($return->return_type === 'retailer_return' ? 'warning' : 'info') }}">
                                    {{ ucfirst(str_replace('_', ' ', $return->return_type)) }}
                                </span>
                            </td>
                            <td>
                                @if($return->customer)
                                    <strong>{{ $return->customer->name }}</strong>
                                    @if($return->order)
                                        <br><small class="text-muted">Order #{{ $return->order->id }}</small>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($return->fromLocation)
                                    <span class="badge bg-secondary">{{ $return->fromLocation->name }}</span>
                                    <br><small class="text-muted">{{ ucfirst($return->fromLocation->location_type) }}</small>
                                @else
                                    <span class="text-muted">Customer</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $return->toLocation->name }}</span>
                                <br><small class="text-muted">{{ ucfirst($return->toLocation->location_type) }}</small>
                            </td>
                            <td>
                                <strong>{{ $return->total_items }}</strong> items
                                <br><small class="text-muted">${{ number_format($return->total_value, 2) }}</small>
                            </td>
                            <td>
                                <strong>${{ number_format($return->refund_amount, 2) }}</strong>
                            </td>
                            <td>
                                <span class="badge bg-{{ $return->status === 'pending' ? 'warning' : ($return->status === 'processing' ? 'primary' : ($return->status === 'completed' ? 'success' : 'danger')) }}">
                                    {{ ucfirst($return->status) }}
                                </span>
                                @if($return->processed_by)
                                    <br><small class="text-muted">by {{ $return->processed_by }}</small>
                                @endif
                            </td>
                            <td>
                                {{ $return->return_date->format('M d, Y') }}
                                <br><small class="text-muted">{{ $return->return_date->format('H:i') }}</small>
                                @if($return->processed_at)
                                    <br><small class="text-success">Processed: {{ $return->processed_at->format('M d, H:i') }}</small>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('returns.show', $return) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if($return->status !== 'completed' && $return->status !== 'rejected')
                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#statusModal{{ $return->id }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center mt-4">
        {{ $returns->links() }}
    </div>

    <!-- Status Update Modals -->
    @foreach($returns as $return)
        @if($return->status !== 'completed' && $return->status !== 'rejected')
        <div class="modal fade" id="statusModal{{ $return->id }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Return Status - {{ $return->return_number }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="{{ route('returns.update', $return) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="status{{ $return->id }}" class="form-label">Status</label>
                                <select class="form-select" id="status{{ $return->id }}" name="status" required>
                                    <option value="pending" {{ $return->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="processing" {{ $return->status === 'processing' ? 'selected' : '' }}>Processing</option>
                                    <option value="completed" {{ $return->status === 'completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="rejected" {{ $return->status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="processed_by{{ $return->id }}" class="form-label">Processed By</label>
                                <input type="text" class="form-control" id="processed_by{{ $return->id }}" name="processed_by" value="{{ $return->processed_by }}">
                            </div>
                            <div class="mb-3">
                                <label for="notes{{ $return->id }}" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes{{ $return->id }}" name="notes" rows="3">{{ $return->notes }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif
    @endforeach

@else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-arrow-return-left display-1 text-muted mb-3"></i>
            <h3>No Returns Found</h3>
            <p class="text-muted mb-4">No product returns have been created yet.</p>
            <a href="{{ route('returns.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Create First Return
            </a>
        </div>
    </div>
@endif
@endsection 
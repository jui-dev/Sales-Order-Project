@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <x-breadcrumb :items="[
        ['label' => 'Returns', 'url' => '#'],
        ['label' => 'All Returns', 'url' => '#']
    ]" />
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-arrow-return-left me-2"></i>{{ $pageTitle ?? 'Return Management' }}</h1>
        <div>
            <a href="{{ route('returns.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Create Return
            </a>
        </div>
    </div>

    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle"></i>
        <strong>Return Management:</strong> All returns are managed through stock transactions. Customer returns generate credit notes, vendor returns generate debit notes, and retailer returns move stock back to warehouses.
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Customer Returns</h6>
                            <h3>{{ $statistics['customer_returns']['count'] }}</h3>
                            <small>{{ number_format($statistics['customer_returns']['quantity']) }} units</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-arrow-return-left display-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Vendor Returns</h6>
                            <h3>{{ $statistics['vendor_returns']['count'] }}</h3>
                            <small>{{ number_format($statistics['vendor_returns']['quantity']) }} units</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-arrow-return-right display-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Retailer Returns</h6>
                            <h3>{{ $statistics['retailer_returns']['count'] }}</h3>
                            <small>{{ number_format($statistics['retailer_returns']['quantity']) }} units</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-arrow-return-left display-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Value</h6>
                            <h3>${{ number_format($statistics['customer_returns']['value'] + $statistics['vendor_returns']['value'] + $statistics['retailer_returns']['value'], 2) }}</h3>
                            <small>Combined return value</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-currency-dollar display-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Unified Search Component -->
    <x-unified-search 
        :searchPlaceholder="'Search returns by transaction number, product, or reference...'"
        :filterOptions="$filterOptions"
        :sortOptions="$sortOptions"
        :defaultSort="'transaction_date'"
        :defaultDirection="'desc'"
    />

    @if($returns->count() > 0)
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Transaction #</th>
                                <th>Type</th>
                                <th>Product</th>
                                <th>Source</th>
                                <th>Destination</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($returns as $return)
                            <tr>
                                <td>
                                    <strong>{{ $return->formatted_id }}</strong>
                                </td>
                                <td>
                                    @php 
                                        try {
                                            $config = $return->getDisplayConfig();
                                        } catch (Exception $e) {
                                            $config = [
                                                'badge_color' => 'secondary',
                                                'icon' => 'bi-question-circle',
                                                'label' => 'Unknown'
                                            ];
                                        }
                                    @endphp
                                    <span class="badge bg-{{ $config['badge_color'] ?? 'secondary' }}">
                                        <i class="{{ $config['icon'] ?? 'bi-question-circle' }} me-1"></i>
                                        {{ $config['label'] ?? 'Unknown' }}
                                    </span>
                                </td>
                                <td>
                                    @if($return->product)
                                        <strong>{{ $return->product->name ?? 'Unknown Product' }}</strong>
                                        <br><small class="text-muted">{{ $return->product->sku ?? 'No SKU' }}</small>
                                    @else
                                        <span class="text-muted">Product not found</span>
                                    @endif
                                </td>
                                <td>
                                    @php 
                                        try {
                                            $sourceInfo = $return->getReturnSourceInfo();
                                        } catch (Exception $e) {
                                            $sourceInfo = [];
                                        }
                                    @endphp
                                    @if(is_array($sourceInfo) && !empty($sourceInfo) && isset($sourceInfo['source']))
                                        <strong>{{ $sourceInfo['source'] ?? 'Unknown' }}</strong>
                                        <br><small class="text-muted">{{ $sourceInfo['reference'] ?? 'Unknown' }}</small>
                                        <br><small class="text-muted">{{ $sourceInfo['date'] ?? 'Unknown' }}</small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($return->location)
                                        <span class="badge bg-secondary">{{ $return->location->name }}</span>
                                        <br><small class="text-muted">{{ class_basename($return->location) }}</small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ number_format($return->quantity) }}</strong>
                                    <br><small class="text-{{ $return->direction === 'inbound' ? 'success' : 'danger' }}">
                                        @php
                                            try {
                                                echo $return->getEffectDescription();
                                            } catch (Exception $e) {
                                                echo 'Unknown';
                                            }
                                        @endphp
                                    </small>
                                </td>
                                <td>
                                    @php
                                        $statusConfig = [
                                            'pending' => [
                                                'color' => 'warning',
                                                'icon' => 'bi-clock',
                                                'description' => 'Awaiting approval'
                                            ],
                                            'approved' => [
                                                'color' => 'info',
                                                'icon' => 'bi-check-circle',
                                                'description' => 'Approved, ready for completion'
                                            ],
                                            'completed' => [
                                                'color' => 'success',
                                                'icon' => 'bi-check2-all',
                                                'description' => 'Return completed'
                                            ]
                                        ];
                                        $config = $statusConfig[$return->status] ?? ['color' => 'secondary', 'icon' => 'bi-question', 'description' => 'Unknown status'];
                                    @endphp
                                    <span class="badge bg-{{ $config['color'] }} d-flex align-items-center gap-1" 
                                          data-bs-toggle="tooltip" 
                                          data-bs-placement="top" 
                                          title="{{ $config['description'] }}">
                                        <i class="{{ $config['icon'] }}"></i>
                                        {{ ucfirst($return->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if($return->transaction_date)
                                        {{ $return->transaction_date->format('M d, Y') }}
                                    @else
                                        <span class="text-muted">No date</span>
                                    @endif
                                    <br><small class="text-muted">
                                        @if($return->created_at)
                                            {{ $return->created_at->format('H:i') }}
                                        @else
                                            Unknown time
                                        @endif
                                    </small>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="{{ route('returns.show', $return) }}" class="btn btn-sm btn-info d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        
                                        <!-- Status Change Buttons -->
                                        @if($return->status === 'pending')
                                            <form action="{{ route('returns.approve', $return) }}" method="POST" style="display: inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success d-inline-flex align-items-center gap-1" 
                                                        data-bs-toggle="tooltip" title="Approve Return">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                            </form>
                                        @elseif($return->status === 'approved')
                                            <form action="{{ route('returns.complete', $return) }}" method="POST" style="display: inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1" 
                                                        data-bs-toggle="tooltip" title="Mark as Completed">
                                                    <i class="bi bi-check2-all"></i>
                                                </button>
                                            </form>
                                        @elseif($return->status === 'completed')
                                            <button type="button" class="btn btn-sm btn-secondary d-inline-flex align-items-center gap-1" disabled data-bs-toggle="tooltip" title="Return Completed">
                                                <i class="bi bi-check2-circle"></i>
                                            </button>
                                        @endif
                                        
                                        <!-- Delete Button (only for pending returns) -->
                                        @if($return->status === 'pending')
                                            <button type="button" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1"
                                                data-bs-toggle="tooltip" title="Delete Return"
                                                onclick="deleteReturn({{ $return->id }}, '{{ $return->formatted_id }}')">
                                                <i class="bi bi-trash"></i>
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
            {{ $returns->appends(request()->query())->links() }}
        </div>
    @else
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-arrow-return-left display-1 text-muted mb-3"></i>
                <h3>No Returns Found</h3>
                <p class="text-muted mb-4">No return transactions have been created yet.</p>
                <a href="{{ route('returns.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i> Create First Return
                </a>
            </div>
        </div>
    @endif
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete return transaction <strong id="deleteTransactionId"></strong>?</p>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action will:
                    <ul class="mb-0 mt-2">
                        <li>Reverse the stock adjustment</li>
                        <li>Permanently delete the transaction record</li>
                        <li>This action cannot be undone</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Delete Return
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function deleteReturn(id, formattedId) {
    document.getElementById('deleteTransactionId').textContent = formattedId;
    document.getElementById('deleteForm').action = `/returns/${id}`;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}
</script>
@endpush

@push('styles')
<style>
/* Status badge improvements */
.badge {
    font-size: 0.75em;
    font-weight: 600;
    padding: 0.35em 0.65em;
}

/* Action buttons hover effects */
.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

/* Disabled button styling */
.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
@endpush 
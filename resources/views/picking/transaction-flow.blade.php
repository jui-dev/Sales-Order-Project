@extends('layouts.app')
@php use Illuminate\Support\Str; @endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-diagram-3 me-2"></i>Transaction Flow Overview</h1>
</div>

<!-- Stock Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center shadow-sm border-0" style="background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%);">
            <div class="card-body">
                <h5 class="card-title text-primary">Products</h5>
                <h2>{{ $stockSummary['total_products'] }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm border-0" style="background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%);">
            <div class="card-body">
                <h5 class="card-title text-success">Stock Value</h5>
                <h2>${{ number_format($stockSummary['total_stock_value'], 2) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm border-0" style="background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%);">
            <div class="card-body">
                <h5 class="card-title text-warning">Pending Moves</h5>
                <h2>{{ $stockSummary['pending_movements'] }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm border-0" style="background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%);">
            <div class="card-body">
                <h5 class="card-title text-danger">Active Pickings</h5>
                <h2>{{ $stockSummary['active_pickings'] }}</h2>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Flow Diagram -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-flow-chart me-2"></i>Stock Flow Process</h5>
    </div>
    <div class="card-body">
        @include('picking.partials.flow-diagram')
    </div>
</div>

<!-- Recent Movements -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Recent Stock Movements</h5>
    </div>
    <div class="card-body">
        @if($recentMovements->count() > 0)
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th class="text-center">Flow</th>
                            <th class="text-end">Qty</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentMovements->take(10) as $movement)
                        <tr>
                            <td>{{ $movement->transaction_date->format('M d, H:i') }}</td>
                            <td>{{ $movement->product->name }}</td>
                            <td class="text-center">
                                @php
                                    $fromLabel = $movement->fromLocation?->name ?? ($movement->direction === 'inbound' ? 'Inbound' : 'System');
                                    $toLabel   = $movement->toLocation?->name   ?? ($movement->direction === 'outbound' ? 'Outbound' : 'System');
                                @endphp
                                <span class="badge bg-light text-dark">{{ Str::limit($fromLabel, 18) }}</span>
                                <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                <span class="badge bg-light text-dark">{{ Str::limit($toLabel, 18) }}</span>
                            </td>
                            <td class="text-end">
                                <span class="badge bg-light text-dark">
                                    {{ number_format($movement->quantity) }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $badgeColor = match($movement->movement_type) {
                                        'supply_in' => 'success',
                                        'transfer'  => 'primary',
                                        'sale'      => 'danger',
                                        default     => 'secondary',
                                    };
                                @endphp
                                <span class="badge bg-{{ $badgeColor }}">
                                    {{ ucfirst(str_replace('_', ' ', $movement->movement_type)) }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted text-center">No recent movements</p>
        @endif
    </div>
</div>
@endsection

@section('styles')
<style>
.flow-step {
    text-align: center;
}

.flow-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    font-size: 24px;
}

.flow-step h6 {
    font-weight: 600;
    margin-bottom: 0;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

.badge {
    font-weight: 500;
    padding: 0.4em 0.6em;
}
</style>
@endsection 
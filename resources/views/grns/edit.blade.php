@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Edit Goods Receipt Note #{{ $grn->formatted_id ?? $grn->id }}</h1>
        <div>
            <a href="{{ route('grns.show', $grn) }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to GRN
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    
    

    <!-- Information Alert -->
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Note:</strong> GRN details are managed through the associated supply. 
        To make changes to this GRN, please edit the <a href="{{ route('supplies.show', $grn->supply_id) }}" class="alert-link">corresponding supply</a>.
    </div>

    <!-- GRN Meta Information (Read-only) -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong><i class="bi bi-truck me-1"></i> Vendor Information</strong>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ $grn->supply->vendor->name }}</strong></p>
                    <small class="text-muted">{{ $grn->supply->vendor->address }}</small>
                    @if($grn->supply->vendor->contact_person)
                        <br><small class="text-muted">Contact: {{ $grn->supply->vendor->contact_person }}</small>
                    @endif
                    @if($grn->supply->vendor->phone)
                        <br><small class="text-muted">Phone: {{ $grn->supply->vendor->phone }}</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong><i class="bi bi-building me-1"></i> Receiving Location</strong>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ $grn->supply->warehouse->name }}</strong></p>
                    <small class="text-muted">{{ $grn->supply->warehouse->address }}</small>
                    @if($grn->supply->warehouse->contact_person)
                        <br><small class="text-muted">Contact: {{ $grn->supply->warehouse->contact_person }}</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong><i class="bi bi-info-circle me-1"></i> GRN Details</strong>
                </div>
                <div class="card-body">
                    @php
                        $badge = [
                            'draft'  => 'secondary',
                            'posted' => 'success',
                        ][$grn->status] ?? 'secondary';
                    @endphp
                    <p class="mb-1">Status: 
                        <span class="badge bg-{{ $badge }}">{{ ucfirst($grn->status) }}</span>
                    </p>
                    <p class="mb-1">Received Date: {{ optional($grn->received_date)->format('M d, Y') }}</p>
                    <p class="mb-1">Supply Reference: <a href="{{ route('supplies.show', $grn->supply_id) }}">#{{ $grn->supply_id }}</a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="card">
        <div class="card-body text-center">
            <h5 class="card-title">GRN Management</h5>
            <p class="card-text">To modify this GRN, please use the supply management interface.</p>
            
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <a href="{{ route('supplies.show', $grn->supply_id) }}" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit Supply
                </a>
                
                @if($grn->status === 'draft')
                    <form action="{{ route('grns.update-status', $grn) }}" method="POST" class="d-inline">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i> Post GRN
                        </button>
                    </form>
                @endif
                
                <a href="{{ route('grns.show', $grn) }}" class="btn btn-secondary">
                    <i class="bi bi-eye me-1"></i> View GRN
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

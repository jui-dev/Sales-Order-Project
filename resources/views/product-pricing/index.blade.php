@extends('layouts.app')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-1">Product Pricing</h1>
        <p class="text-muted mb-0">What each product sells for, what it costs, and who gets which price.</p>
    </div>
    @can('product-pricing.manage')
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newListModal">
        <i class="bi bi-plus-lg me-1"></i>New Price List
    </button>
    @endcan
</div>
@endsection

@section('content')
<x-breadcrumb :items="[['label' => 'Product Pricing']]" />

{{-- A short explanation of the model, because 'why are there several lists?'
     is the first question this screen invites. --}}
<div class="alert alert-light border d-flex align-items-start" role="alert">
    <i class="bi bi-info-circle me-2 mt-1"></i>
    <div class="small">
        A <strong>sale</strong> list is what you charge; a <strong>purchase</strong> list is what a vendor charges you.
        A list applies to everyone unless it is assigned to a customer, group, channel or vendor - and where several
        apply, the highest priority wins. Changing a price never overwrites the old one: it closes it and starts a new
        one, so past orders keep the price they were placed at.
    </div>
</div>

@foreach(['sale' => 'Sale price lists', 'purchase' => 'Purchase price lists'] as $type => $heading)
    @php($group = $lists->where('type', $type))
    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0">{{ $heading }}</h2>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Applies to</th>
                            <th class="text-end">Priority</th>
                            <th class="text-end">Products priced</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($group as $list)
                        <tr>
                            <td>
                                <a href="{{ route('product-pricing.show', $list->id) }}" class="fw-semibold text-decoration-none">
                                    {{ $list->name }}
                                </a>
                                @if($list->is_default)
                                    <span class="badge bg-primary ms-1">Default</span>
                                @endif
                                <div class="small text-muted">{{ $list->code }}</div>
                            </td>
                            <td>
                                @if($list->assignments->isEmpty())
                                    <span class="text-muted">Everyone</span>
                                @else
                                    @foreach($list->assignments as $assignment)
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                            {{ class_basename($assignment->assignable_type) }}:
                                            {{ $assignment->assignable->name ?? '#'.$assignment->assignable_id }}
                                        </span>
                                    @endforeach
                                @endif
                            </td>
                            <td class="text-end">{{ $list->priority }}</td>
                            <td class="text-end">{{ $list->priced_products_count }}</td>
                            <td>
                                @if(! $list->is_active)
                                    <span class="badge bg-secondary">Inactive</span>
                                @elseif($list->ends_at && $list->ends_at->isPast())
                                    <span class="badge bg-warning text-dark">Expired</span>
                                @elseif($list->starts_at && $list->starts_at->isFuture())
                                    <span class="badge bg-info text-dark">Scheduled</span>
                                @else
                                    <span class="badge bg-success">Active</span>
                                @endif
                                @if($list->starts_at || $list->ends_at)
                                    <div class="small text-muted">
                                        {{ $list->starts_at?->format('d M Y') ?? '—' }} to {{ $list->ends_at?->format('d M Y') ?? 'open' }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('product-pricing.show', $list->id) }}" class="btn btn-sm btn-outline-primary">
                                    Manage prices
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No {{ $type }} price lists yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endforeach

{{-- Not an error, but worth seeing: these fall back to cost plus markup on the
     order form, which is a stopgap rather than a price anyone agreed. --}}
@if($unpriced->isNotEmpty())
<div class="card mb-4 border-warning">
    <div class="card-header bg-warning-subtle">
        <h2 class="h6 mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ $unpriced->count() }} product(s) have no agreed sale price
        </h2>
    </div>
    <div class="card-body">
        <p class="small text-muted">
            These are priced from cost plus markup when an order is taken. Set a price on the default sale list to
            make that deliberate.
        </p>
        <div class="d-flex flex-wrap gap-2">
            @foreach($unpriced->take(30) as $product)
                <a href="{{ route('product-pricing.history', $product->id) }}"
                   class="badge bg-light text-dark border text-decoration-none">
                    {{ $product->name }} <span class="text-muted">({{ $product->sku }})</span>
                </a>
            @endforeach
            @if($unpriced->count() > 30)
                <span class="badge bg-light text-muted border">and {{ $unpriced->count() - 30 }} more</span>
            @endif
        </div>
    </div>
</div>
@endif

@can('product-pricing.manage')
<div class="modal fade" id="newListModal" tabindex="-1" aria-labelledby="newListModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('product-pricing.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="newListModalLabel">New Price List</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" required placeholder="e.g. Wholesale">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror"
                           value="{{ old('code') }}" required placeholder="e.g. wholesale">
                    <div class="form-text">A short unique identifier. Letters, numbers, dashes.</div>
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                    <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required>
                        <option value="sale" {{ old('type') === 'sale' ? 'selected' : '' }}>Sale - what we charge</option>
                        <option value="purchase" {{ old('type') === 'purchase' ? 'selected' : '' }}>Purchase - what we are charged</option>
                    </select>
                    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label for="priority" class="form-label">Priority</label>
                    <input type="number" name="priority" id="priority" class="form-control @error('priority') is-invalid @enderror"
                           value="{{ old('priority', 0) }}" min="0">
                    <div class="form-text">Highest wins where several lists apply. Customer-specific 100, wholesale 50, base retail 0.</div>
                    @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="starts_at" class="form-label">Starts</label>
                        <input type="date" name="starts_at" id="starts_at" class="form-control" value="{{ old('starts_at') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="ends_at" class="form-label">Ends</label>
                        <input type="date" name="ends_at" id="ends_at" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at') }}">
                        <div class="form-text">Leave both blank to run indefinitely.</div>
                        @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create list</button>
            </div>
        </form>
    </div>
</div>
@endcan
@endsection

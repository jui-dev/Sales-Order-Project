@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Edit Supply #{{ $supply->id }}</h1>
    <div>
        <a href="{{ route('supplies.show', $supply) }}" class="btn btn-info">View Supply</a>
        <a href="{{ route('supplies.index') }}" class="btn btn-secondary">Back to Supplies</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-light mb-4">
            <i class="bi bi-exclamation-circle text-danger"></i> Fields marked with <span class="text-danger">*</span> are required and must be filled before submitting the form.
        </div>
        
        <form action="{{ route('supplies.update', $supply) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="vendor_id" class="form-label">Vendor <span class="text-danger">*</span></label>
                    <select name="vendor_id" id="vendor_id" class="form-select @error('vendor_id') is-invalid @enderror" required>
                        <option value="">Select Vendor</option>
                        @foreach($vendors as $vendor)
                            <option value="{{ $vendor->id }}" {{ (old('vendor_id', $supply->vendor_id) == $vendor->id) ? 'selected' : '' }}>
                                {{ $vendor->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('vendor_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="supply_date" class="form-label">Supply Date <span class="text-danger">*</span></label>
                    <input type="date" name="supply_date" id="supply_date" class="form-control @error('supply_date') is-invalid @enderror" 
                        value="{{ old('supply_date', $supply->supply_date->format('Y-m-d')) }}" required>
                    @error('supply_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Note: Products and quantities cannot be modified after a supply is recorded to maintain stock integrity.
            </div>
            
            <div class="table-responsive mb-3">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Unit Cost</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($supply->items as $item)
                        <tr>
                            <td>{{ $item->product->name }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>${{ number_format($item->unit_cost, 2) }}</td>
                            <td>${{ number_format($item->subtotal, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">Status</label>
                    <div>
                        <span class="text-{{ $supply->status == 'pending' ? 'warning' : ($supply->status == 'processing' ? 'primary' : 'success') }}">
                            {{ ucfirst($supply->status) }}
                        </span>
                        <small class="text-muted ms-2">(Status can be changed from the supplies list or detail page)</small>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="notes" class="form-label">Notes</label>
                <textarea name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $supply->notes) }}</textarea>
                @error('notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div>
                    <strong>Total Cost: ${{ number_format($supply->total_cost, 2) }}</strong>
                </div>
                <button type="submit" class="btn btn-primary">Update Supply</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Required field indicator styling - consistent with create page */
    .text-danger {
        color: var(--danger) !important;
    }
    
    .form-label .text-danger {
        font-weight: 600;
        margin-left: 2px;
    }
    
    /* Enhanced focus states for required fields */
    .form-control:required:focus,
    .form-select:required:focus {
        border-color: var(--danger);
        box-shadow: 0 0 0 0.2rem rgba(231, 111, 81, 0.25);
    }
    
    @media (max-width: 768px) {
        /* Ensure required indicators are visible on mobile */
        .form-label .text-danger {
            font-size: 0.9em;
        }
    }
</style>
@endsection 
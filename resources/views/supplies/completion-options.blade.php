@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h4>Complete Supply - Choose Receiving Method</h4>
                    <p class="mb-0 text-muted">Supply #{{ $supply->id }} from {{ $supply->vendor->name }}</p>
                </div>

                <div class="card-body">
                    <!-- Supply Information -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Supply Details</h6>
                            <ul class="list-unstyled">
                                <li><strong>Vendor:</strong> {{ $supply->vendor->name }}</li>
                                <li><strong>Supply Date:</strong> {{ $supply->supply_date->format('M d, Y') }}</li>
                                <li><strong>Total Cost:</strong> ${{ number_format($supply->total_cost, 2) }}</li>
                                <li><strong>Status:</strong> 
                                    <span class="badge badge-warning">{{ ucfirst($supply->status) }}</span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Items ({{ $supply->supplyItems->count() }})</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Qty</th>
                                            <th>Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($supply->supplyItems as $item)
                                        <tr>
                                            <td>{{ $item->product->name }}</td>
                                            <td>{{ $item->quantity }}</td>
                                            <td>${{ number_format($item->unit_cost, 2) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Completion Options Form -->
                    <form action="{{ route('supplies.process-completion', $supply) }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-12">
                                <h5>Choose Receiving Method</h5>
                                <p class="text-muted">Select how you want to process this vendor supply:</p>
                            </div>
                        </div>

                        <!-- Completion Type Selection -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card border-primary">
                                    <div class="card-body">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="completion_type" 
                                                   id="manual_picking" value="manual_picking" checked>
                                            <label class="form-check-label" for="manual_picking">
                                                <h6>Manual Warehouse Receiving</h6>
                                            </label>
                                        </div>
                                        <p class="text-muted small mt-2">
                                            Creates a pending picking list for warehouse staff to process manually. 
                                            Allows for quantity verification and quality control.
                                        </p>
                                        <ul class="small text-muted">
                                            <li>Generate picking list with pending status</li>
                                            <li>Warehouse staff can verify quantities</li>
                                            <li>Stock updated only after manual confirmation</li>
                                            <li>Recommended for quality control</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card border-success">
                                    <div class="card-body">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="completion_type" 
                                                   id="direct_stocking" value="direct_stocking">
                                            <label class="form-check-label" for="direct_stocking">
                                                <h6>Direct Stocking</h6>
                                            </label>
                                        </div>
                                        <p class="text-muted small mt-2">
                                            Automatically updates warehouse stock without manual picking process. 
                                            Suitable for trusted vendors and pre-verified shipments.
                                        </p>
                                        <ul class="small text-muted">
                                            <li>Immediate stock update</li>
                                            <li>Auto-complete picking list</li>
                                            <li>No manual verification required</li>
                                            <li>Faster processing for trusted vendors</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Options -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="receiving_location_id">Receiving Location</label>
                                    <select name="receiving_location_id" id="receiving_location_id" class="form-control">
                                        <option value="">Use Default Warehouse</option>
                                        @foreach($warehouses as $warehouse)
                                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">
                                        Choose specific warehouse or use default location
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="notes">Additional Notes</label>
                                    <textarea name="notes" id="notes" class="form-control" rows="3" 
                                              placeholder="Optional notes for the receiving process..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Configuration Info -->
                        @if($pickingConfig['auto_generate_on_supply_completion'])
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Automatic Picking Enabled</h6>
                            <p class="mb-0">
                                Picking lists will be generated automatically based on your selection above.
                                @if($pickingConfig['require_manual_confirmation'])
                                Manual confirmation is required for stock movements.
                                @endif
                            </p>
                        </div>
                        @endif

                        <!-- Action Buttons -->
                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check"></i> Complete Supply
                                </button>
                                <a href="{{ route('supplies.show', $supply) }}" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Supply
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const manualRadio = document.getElementById('manual_picking');
    const directRadio = document.getElementById('direct_stocking');
    
    function updateCardStyles() {
        const manualCard = manualRadio.closest('.card');
        const directCard = directRadio.closest('.card');
        
        if (manualRadio.checked) {
            manualCard.classList.remove('border-primary');
            manualCard.classList.add('border-primary', 'bg-light');
            directCard.classList.remove('border-success', 'bg-light');
            directCard.classList.add('border-success');
        } else {
            directCard.classList.remove('border-success');
            directCard.classList.add('border-success', 'bg-light');
            manualCard.classList.remove('border-primary', 'bg-light');
            manualCard.classList.add('border-primary');
        }
    }
    
    manualRadio.addEventListener('change', updateCardStyles);
    directRadio.addEventListener('change', updateCardStyles);
    
    // Initial styling
    updateCardStyles();
});
</script>
@endsection
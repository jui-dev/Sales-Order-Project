@extends('layouts.app')
@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-list-ul me-2"></i>All Picking Lists</h1>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    
    

    <!-- Statistics Cards -->
    <div class="summary-panel mb-4">
        <div class="row g-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm summary-card summary-card--blue">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title text-primary">Total Lists</h6>
                            <h3>{{ $statistics['total_lists'] }}</h3>
                        </div>
                        <i class="bi bi-list-ul text-primary summary-card__icon" style="font-size: 2rem;"></i>
                    </div>
                    <small class="text-muted">
                        All picking lists on record
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm summary-card summary-card--amber">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title text-warning-emphasis">Pending</h6>
                            <h3>{{ $statistics['pending_lists'] }}</h3>
                        </div>
                        <i class="bi bi-clock text-warning-emphasis summary-card__icon" style="font-size: 2rem;"></i>
                    </div>
                    <small class="text-muted">
                        Waiting to be picked
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm summary-card summary-card--green">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title text-success">Completed</h6>
                            <h3>{{ $statistics['completed_lists'] }}</h3>
                        </div>
                        <i class="bi bi-check-circle text-success summary-card__icon" style="font-size: 2rem;"></i>
                    </div>
                    <small class="text-muted">
                        Fully picked and closed
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm summary-card summary-card--cyan">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title text-info-emphasis">In Progress</h6>
                            <h3>{{ $statistics['in_progress_lists'] }}</h3>
                        </div>
                        <i class="bi bi-arrow-clockwise text-info-emphasis summary-card__icon" style="font-size: 2rem;"></i>
                    </div>
                    <small class="text-muted">
                        Currently being picked
                    </small>
                </div>
            </div>
        </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Reference</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Picking Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lists as $list)
                        <tr>
                            <td>{{ $list->id }}</td>
                            <td>
                                {{ class_basename($list->reference_type) }} ID: {{ $list->reference_id }}
                            </td>
                            <td>{{ $list->items ? $list->items->sum('quantity') : 0 }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($list->status) }}</span></td>
                            <td>{{ optional($list->picking_date)->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">No picking lists found.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>

            <x-pagination :paginator="$lists" />
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
/* White container holding the summary cards */
.summary-panel {
    background-color: #ffffff;
    border: 1px solid var(--border-color, #e9ecef);
    border-radius: 8px;
    padding: 1.25rem;
    box-shadow: var(--card-shadow, 0 2px 15px rgba(0, 0, 0, 0.04));
}

/* Summary cards - soft gradient treatment */
.summary-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.summary-card:hover {
    transform: translateY(-2px);
}

.summary-card--blue  { background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%); }
.summary-card--green { background: linear-gradient(135deg, #e8f5e9 0%, #ffffff 100%); }
.summary-card--amber { background: linear-gradient(135deg, #fff8e1 0%, #ffffff 100%); }
.summary-card--cyan  { background: linear-gradient(135deg, #e0f7fa 0%, #ffffff 100%); }

.summary-card__icon {
    opacity: 0.45;
}
</style>
@endpush 
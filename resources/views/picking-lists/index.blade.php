@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <x-breadcrumb :items="[
        ['label' => 'Picking & Transfers', 'url' => '#'],
        ['label' => 'All Picking Lists', 'url' => '#']
    ]" />
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-list-ul me-2"></i>All Picking Lists</h1>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title">{{ $statistics['total_lists'] }}</h4>
                            <p class="card-text">Total Lists</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-list-ul fs-1"></i>
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
                            <h4 class="card-title">{{ $statistics['pending_lists'] }}</h4>
                            <p class="card-text">Pending</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-clock fs-1"></i>
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
                            <h4 class="card-title">{{ $statistics['completed_lists'] }}</h4>
                            <p class="card-text">Completed</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-check-circle fs-1"></i>
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
                            <h4 class="card-title">{{ $statistics['in_progress_lists'] }}</h4>
                            <p class="card-text">In Progress</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-arrow-clockwise fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
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
            <x-pagination :paginator="$lists" />
        </div>
    </div>
</div>
@endsection 
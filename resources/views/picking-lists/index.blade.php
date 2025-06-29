@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-list-ul me-2"></i>All Picking Lists</h1>
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
                        <td>{{ $list->items->sum('quantity') }}</td>
                        <td><span class="badge bg-secondary">{{ ucfirst($list->status) }}</span></td>
                        <td>{{ optional($list->picking_date)->format('M d, Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center">No picking lists found.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $lists->links() }}
    </div>
</div>
@endsection 
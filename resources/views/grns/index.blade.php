@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Goods Receipt Notes</h1>
    <a href="{{ route('supplies.index') }}" class="btn btn-secondary">Back to Supplies</a>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Vendor</th>
                    <th>Supply ID</th>
                    <th>Received Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($grns as $grn)
                    <tr>
                        <td><a href="{{ route('grns.show', $grn) }}">#{{ $grn->id }}</a></td>
                        <td><a href="{{ route('grns.show', $grn) }}">{{ $grn->supply->vendor->name ?? '-' }}</a></td>
                        <td><a href="{{ route('supplies.show', $grn->supply_id) }}">#{{ $grn->supply_id }}</a></td>
                        <td>{{ optional($grn->received_date)->format('M d, Y') }}</td>
                        <td>
                            <span class="badge bg-{{ match($grn->status){
                                'draft'    => 'secondary',
                                'verified' => 'warning',
                                'posted'   => 'success',
                                default    => 'secondary'
                            } }}">{{ ucfirst($grn->status) }}</span>
                        </td>
                        <td>
                            @if($grn->status !== 'posted')
                                <form action="{{ route('grns.update-status', $grn) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        Mark {{ $grn->status === 'draft' ? 'Verified' : 'Posted' }}
                                    </button>
                                </form>
                            @else
                                <span class="text-muted">Completed</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No GRNs found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection 
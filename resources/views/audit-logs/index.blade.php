@extends('layouts.app')

@section('page-header')
<h1 class="mb-4">Audit Trail</h1>
@endsection

@section('content')
<div class="container-fluid">

    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-2">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Action</label>
            <select name="action" class="form-select">
                <option value="">-- Any --</option>
                @foreach($filterOptions['action']['options'] as $value => $label)
                    <option value="{{ $value }}" {{ request('action') == $value ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $label)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">User</label>
            <select name="user_id" class="form-select">
                <option value="">-- Any --</option>
                @foreach($filterOptions['user_id']['options'] as $id => $name)
                    <option value="{{ $id }}" {{ request('user_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 align-self-end text-end">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('audit-logs.index') }}" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>Subject</th>
                </tr>
            </thead>
            <tbody>
                @forelse($auditLogs as $log)
                    <tr>
                        <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
                        <td>
                            @if(class_exists('App\\Models\\User'))
                                {{ $log->user?->name ?? '-' }}
                            @else
                                {{ $log->user_id ?: '-' }}
                            @endif
                        </td>
                        <td>{{ ucwords(str_replace('_', ' ', $log->action)) }}</td>
                        <td>{{ $log->description }}</td>
                        <td>
                            @if($log->subject)
                                {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">No audit logs found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$auditLogs" />
</div>
@endsection 
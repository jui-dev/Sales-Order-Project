@extends('layouts.app')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Users</h1>
    @can('users.manage')
        <a href="{{ route('users.create') }}" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i>Add New User
        </a>
    @endcan
</div>
@endsection

@section('content')
<div class="container-fluid">
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Roles</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td>{{ $user->id }}</td>
                                <td>
                                    {{ $user->name }}
                                    @if($user->is(auth()->user()))
                                        <span class="badge bg-secondary ms-1">You</span>
                                    @endif
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @forelse ($user->roles as $role)
                                        <span class="badge bg-primary">{{ $role->label }}</span>
                                    @empty
                                        <span class="text-muted small">No roles assigned</span>
                                    @endforelse
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="{{ route('users.show', $user) }}"
                                           class="btn btn-sm btn-info d-inline-flex align-items-center gap-1"
                                           title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @can('users.manage')
                                            <a href="{{ route('users.edit', $user) }}"
                                               class="btn btn-sm btn-warning d-inline-flex align-items-center gap-1"
                                               title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            @unless($user->is(auth()->user()))
                                                <form action="{{ route('users.destroy', $user) }}"
                                                      method="POST"
                                                      class="d-inline"
                                                      onsubmit="return confirm('Delete user &quot;{{ $user->name }}&quot;? This cannot be undone.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1"
                                                            title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            @endunless
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

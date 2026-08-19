@extends('layouts.app')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>{{ $user->name }}</h1>
    <div class="d-flex gap-2">
        @can('users.manage')
            <a href="{{ route('users.edit', $user) }}" class="btn btn-warning">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
        @endcan
        <a href="{{ route('users.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Users
        </a>
    </div>
</div>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header fw-semibold">Details</div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt class="small text-muted">User ID</dt>
                        <dd>{{ $user->id }}</dd>

                        <dt class="small text-muted">Name</dt>
                        <dd>{{ $user->name }}</dd>

                        <dt class="small text-muted">Email</dt>
                        <dd>{{ $user->email }}</dd>

                        <dt class="small text-muted">Roles</dt>
                        <dd class="mb-0">
                            @forelse ($user->roles as $role)
                                <span class="badge bg-primary">{{ $role->label }}</span>
                            @empty
                                <span class="text-muted">No roles assigned</span>
                            @endforelse
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header fw-semibold">Effective Permissions</div>
                <div class="card-body">
                    @if($user->isAdmin())
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-shield-check me-2"></i>
                            This user is an administrator and bypasses every permission check.
                        </div>
                    @endif

                    @php
                        // Grouped the same way the role form presents them.
                        $granted = $user->roles
                            ->flatMap->permissions
                            ->unique('id')
                            ->groupBy('group')
                            ->sortKeys();
                    @endphp

                    @forelse ($granted as $group => $permissions)
                        <div class="mb-3">
                            <div class="small text-uppercase text-muted fw-semibold mb-1">{{ $group }}</div>
                            @foreach ($permissions as $permission)
                                <span class="badge bg-light text-dark border me-1 mb-1">{{ $permission->label }}</span>
                            @endforeach
                        </div>
                    @empty
                        <p class="text-muted mb-0">
                            This user has no permissions and cannot reach any module.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

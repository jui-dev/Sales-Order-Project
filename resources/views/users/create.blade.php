@extends('layouts.app')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Add New User</h1>
    <a href="{{ route('users.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Users
    </a>
</div>
@endsection

@section('content')
<div class="container-fluid">
    <form action="{{ route('users.store') }}" method="POST">
        @csrf
        @include('users._form', ['isEdit' => false])

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-check-circle me-1"></i>Create User
            </button>
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection

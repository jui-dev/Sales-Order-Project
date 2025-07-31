@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <x-breadcrumb :items="[
        ['label' => 'Accounting', 'url' => '#'],
        ['label' => 'Chart of Accounts', 'url' => route('accounting.chart-of-accounts')],
        ['label' => 'Create Account', 'url' => '#']
    ]" />
    
    <div class="row">
        <div class="col-12 mb-4 d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0">Create Account</h1>
            <a href="{{ route('accounting.chart-of-accounts') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Chart of Accounts
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">Create New Account</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('accounting.chart-of-accounts.store') }}" class="row g-3">
                        @csrf
                        <div class="col-md-2">
                            <label class="form-label" for="code">Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code') }}" required>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="name">Account Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="description">Description</label>
                            <input type="text" class="form-control" id="description" name="description" value="{{ old('description') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="account_type_id">Account Type <span class="text-danger">*</span></label>
                            <select class="form-select @error('account_type_id') is-invalid @enderror" id="account_type_id" name="account_type_id" required>
                                <option value="">Choose...</option>
                                @foreach($types as $id => $typeName)
                                    <option value="{{ $id }}" {{ old('account_type_id') == $id ? 'selected' : '' }}>{{ $typeName }}</option>
                                @endforeach
                            </select>
                            @error('account_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="parent_id">Parent Account</label>
                            <select class="form-select" id="parent_id" name="parent_id">
                                <option value="">None</option>
                                @foreach($accounts as $pAcc)
                                    <option value="{{ $pAcc->id }}" {{ old('parent_id') == $pAcc->id ? 'selected' : '' }}>{{ $pAcc->code }} - {{ $pAcc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <a href="{{ route('accounting.chart-of-accounts') }}" class="btn btn-secondary me-2">Cancel</a>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-plus me-1"></i> Create Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
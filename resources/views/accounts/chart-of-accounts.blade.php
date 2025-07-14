@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12 mb-4 d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">Chart of Accounts</h1>
    </div>
</div>

{{-- Create Account Form --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">Create Account</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('accounting.chart-of-accounts.store') }}" class="row g-3">
                    @csrf
                    <div class="col-md-2">
                        <label class="form-label" for="code">Code</label>
                        <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code') }}" required>
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="name">Account Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="description">Description</label>
                        <input type="text" class="form-control" id="description" name="description" value="{{ old('description') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="account_type_id">Account Type</label>
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
                        <button type="submit" class="btn btn-success"><i class="bi bi-plus me-1"></i> Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Accounts Table --}}
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0" id="accounts-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:110px;">Code</th>
                                <th>Account Name</th>
                                <th>Description</th>
                                <th style="width:180px;">Account Type</th>
                            </tr>
                        </thead>
                        <tbody>
                        @php
                            // Group accounts by parent for easy recursion
                            $accountsByParent = $accounts->groupBy('parent_id');

                            $renderRows = function($parentId, $depth) use (&$renderRows, $accountsByParent) {
                                if(!isset($accountsByParent[$parentId])) return;
                                foreach($accountsByParent[$parentId]->sortBy('code') as $acc) {
                                    $hasChildren = isset($accountsByParent[$acc->id]);
                                    echo '<tr data-parent-id="'.($acc->parent_id ?: 0).'" data-id="'.$acc->id.'"'.($hasChildren ? ' class="has-children"' : '').'>';
                                    echo '<td>'.e($acc->code).'</td>';
                                    echo '<td>'.($depth === 0 ? '<strong>' : '');
                                    echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth).e($acc->name);
                                    echo $depth === 0 ? '</strong>' : '';
                                    echo '</td>';
                                    echo '<td>'.e($acc->description).'</td>';
                                    echo '<td>'.e($acc->accountType?->name).'</td>';
                                    echo '</tr>';
                                    $renderRows($acc->id, $depth + 1);
                                }
                            };

                            $renderRows(null, 0);
                        @endphp
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Collapse / expand hierarchy rows
        const rows = document.querySelectorAll('#accounts-table tbody tr.has-children');

        rows.forEach(row => {
            row.style.cursor = 'pointer';
            row.addEventListener('click', function (e) {
                // Prevent clicking on links etc.
                if(e.target.tagName === 'A' || e.target.closest('a')) return;

                const id = this.dataset.id;
                const isCollapsed = this.classList.toggle('collapsed');

                // Toggle indicator icon (optional)
                // Traverse children recursively
                const toggleChildren = (parentId, hide) => {
                    document.querySelectorAll(`#accounts-table tbody tr[data-parent-id="${parentId}"]`).forEach(child => {
                        if (hide) {
                            child.style.display = 'none';
                        } else {
                            child.style.display = '';
                        }

                        // If hiding, always hide deeper levels too
                        if (hide) {
                            toggleChildren(child.dataset.id, true);
                        }
                    });
                };

                toggleChildren(id, isCollapsed);
            });
        });
    });
</script>
@endpush

@endsection 
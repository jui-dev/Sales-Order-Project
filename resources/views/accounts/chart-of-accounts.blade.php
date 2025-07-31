@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <x-breadcrumb :items="[
        ['label' => 'Accounting', 'url' => '#'],
        ['label' => 'Chart of Accounts', 'url' => '#']
    ]" />
    
    <div class="row">
        <div class="col-12 mb-4 d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0">Chart of Accounts</h1>
            <a href="{{ route('accounting.chart-of-accounts.create') }}" class="btn btn-success">
                <i class="bi bi-plus me-1"></i> Create Account
            </a>
        </div>
    </div>

    @if(isset($error))
        <div class="row">
            <div class="col-12">
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    {{ $error }}
                </div>
            </div>
        </div>
    @endif

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
                        @if($accounts->count() > 0)
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
                        @else
                            <tr>
                                <td colspan="4" class="text-center py-4">
                                    <i class="bi bi-inbox text-muted me-2"></i>
                                    No accounts found
                                </td>
                            </tr>
                        @endif
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
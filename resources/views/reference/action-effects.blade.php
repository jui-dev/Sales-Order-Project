@extends('layouts.app')

@section('page-header')
<h1 class="mb-2">Action Effects</h1>
<p class="text-muted mb-4">
    What each button in the system sets off elsewhere. One action usually writes
    into several modules at once, and the menu items it touches show a count
    badge until you open them.
</p>
@endsection

@section('content')
<div class="container-fluid">
    @foreach($modules as $module => $actions)
        <h2 class="h5 mt-4 mb-3">{{ $module }}</h2>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th style="width: 22%;">Action</th>
                        <th style="width: 22%;">Where it lives</th>
                        <th>What it triggers</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($actions as $action)
                        <tr>
                            <td class="fw-semibold">{{ $action['label'] }}</td>
                            <td class="text-muted">{{ $action['where'] }}</td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    @foreach($action['effects'] as $effect)
                                        <li>
                                            <span class="action-effects__where badge badge-subtle">
                                                {{ \App\Support\Nav\NavCatalog::path($effect['key']) }}
                                            </span>
                                            {{ $effect['what'] }}
                                        </li>
                                    @endforeach
                                </ul>

                                @isset($action['note'])
                                    <div class="action-effects__note mt-2">
                                        <i class="bi bi-info-circle me-1"></i>{{ $action['note'] }}
                                    </div>
                                @endisset
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
@endsection

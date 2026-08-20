@props(['panel'])

{{--
    What the action the reader just performed set off elsewhere.

    The sidebar badges say *where* to look; this says *what* landed there, which
    is the half that is impossible to work out from the screens today. Rows are
    built by App\Support\Nav\NavEffects and are already permission-filtered.
--}}
@php
    $rows = $panel['rows'];
    $shown = array_slice($rows, 0, 8);
    $hidden = count($rows) - count($shown);
@endphp

<div class="triggered-effects alert alert-dismissible fade show" role="status">
    {{-- The success message lives here rather than in its own toast, so one
         action produces one notice instead of two stacked on each other. --}}
    <div class="triggered-effects__title">
        <i class="bi bi-diagram-3 me-2"></i><strong>{{ $panel['message'] ?? $panel['action'] ?? 'That action' }}</strong>
    </div>
    <div class="triggered-effects__lead">This also triggered:</div>

    <ul class="triggered-effects__list">
        @foreach($shown as $row)
            <li>
                @if($row['url'])
                    <a href="{{ $row['url'] }}" class="fw-semibold">{{ $row['title'] }}</a>
                @else
                    <span class="fw-semibold">{{ $row['title'] }}</span>
                @endif
                <span>&mdash; {{ $row['detail'] }}</span>
                <span class="triggered-effects__where">
                    <i class="bi bi-signpost-2 me-1"></i>{{ implode(', ', $row['where']) }}
                </span>
            </li>
        @endforeach
    </ul>

    @if($hidden > 0)
        <div class="triggered-effects__more">and {{ $hidden }} more.</div>
    @endif

    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>
</div>

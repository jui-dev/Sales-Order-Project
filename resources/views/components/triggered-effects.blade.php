@props(['panel'])

{{--
    What the action the reader just performed set off elsewhere.

    The sidebar badges say *where* to look; this says *what* landed there, which
    is the half that is impossible to work out from the screens today. Rows are
    built by App\Support\Nav\NavEffects and are already permission-filtered.

    This used to be a banner across the top of the page. It is now a button that
    sits on the same line as the page's own actions and opens the detail in a
    modal, so the notice never pushes the page down. The layout moves the button
    into the page header at load; until then it renders where it stands, so the
    notice is still reachable if the script never runs.
--}}
@php
    $rows     = $panel['rows'];
    $count    = count($rows);
    $headline = $panel['message'] ?? $panel['action'] ?? 'That action';
@endphp

<div class="triggered-effects-launcher d-print-none" id="triggeredEffectsLauncher">
    <button type="button"
            class="btn triggered-effects-btn d-print-none"
            id="triggeredEffectsBtn"
            data-bs-toggle="modal"
            data-bs-target="#triggeredEffectsModal">
        <i class="bi bi-diagram-3 me-1"></i> This Also Triggered
        <span class="triggered-effects-btn__count">{{ $count }}</span>
    </button>
</div>

<div class="modal fade" id="triggeredEffectsModal" tabindex="-1"
     aria-labelledby="triggeredEffectsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content triggered-effects">
            <div class="modal-header">
                {{-- The action's own success message is the headline, so the
                     modal names what happened before listing the knock-ons. --}}
                <h5 class="modal-title" id="triggeredEffectsModalLabel">
                    <i class="bi bi-diagram-3 me-2"></i>{{ $headline }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="triggered-effects__lead">
                    This also triggered {{ $count }} {{ \Illuminate\Support\Str::plural('change', $count) }}
                    elsewhere in the system:
                </div>

                <ul class="triggered-effects__list">
                    @foreach($rows as $row)
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
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

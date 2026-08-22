@props([
    'field',
    'defaultDirection' => 'asc',
    // Set on the one column the listing falls back to when the URL says
    // nothing. A first visit is sorted by that column just as firmly as a
    // clicked one is, and the header should say so - otherwise the page arrives
    // sorted with no column admitting it, and the first click on that column
    // appears to do nothing because it asks for the order already showing.
    'isDefault' => false,
    'tooltip' => null,
])

@php
    $activeField = request('sort', $isDefault ? $field : null);
    $activeDirection = strtolower((string) request('direction', $defaultDirection)) === 'asc' ? 'asc' : 'desc';
    $isActive = $activeField === $field;

    // Clicking the column you are already on turns it round. Clicking a new one
    // starts it at the direction that column is usually asked about - A first
    // for text, largest first for money, stock and dates.
    $nextDirection = $isActive
        ? ($activeDirection === 'asc' ? 'desc' : 'asc')
        : $defaultDirection;

    // Built from request()->except() rather than fullUrlWithQuery() so that
    // array-valued parameters survive, and so that `page` is dropped: re-sorting
    // while on page 4 of the old order has no business landing on page 4 of the
    // new one.
    $sortUrl = request()->url() . '?' . http_build_query(array_merge(
        request()->except(['sort', 'direction', 'page']),
        ['sort' => $field, 'direction' => $nextDirection],
    ));
@endphp

<th {{ $attributes->class(['sortable-header', 'is-sorted' => $isActive]) }}
    @if($isActive) aria-sort="{{ $activeDirection === 'asc' ? 'ascending' : 'descending' }}" @endif>
    <a href="{{ $sortUrl }}" class="sortable-header__link"
       @if($tooltip) data-bs-toggle="tooltip" title="{{ $tooltip }}" @endif>
        <span>{{ $slot }}</span>
        @if($isActive)
            <i class="bi bi-caret-{{ $activeDirection === 'asc' ? 'up' : 'down' }}-fill sortable-header__arrow"></i>
        @else
            <i class="bi bi-arrow-down-up sortable-header__arrow sortable-header__arrow--idle"></i>
        @endif
    </a>
</th>

@once
    @push('styles')
        <style>
            .sortable-header { white-space: nowrap; }

            .sortable-header__link {
                display: inline-flex;
                align-items: center;
                gap: .35rem;
                color: inherit;
                text-decoration: none;
            }

            .sortable-header__link:hover { color: var(--bs-primary, #0d6efd); }

            /* The idle arrow is a hint that the column can be clicked, not a
               claim about how it is sorted, so it stays faint until hovered. */
            .sortable-header__arrow--idle { opacity: .3; }
            .sortable-header__link:hover .sortable-header__arrow--idle { opacity: .7; }

            .sortable-header.is-sorted .sortable-header__link { color: var(--bs-primary, #0d6efd); }
        </style>
    @endpush
@endonce

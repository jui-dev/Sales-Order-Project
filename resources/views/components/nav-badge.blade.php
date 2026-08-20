@props(['for'])

{{--
    Count of records the action just performed put behind this menu item.

    A Blade component cannot see the layout's data, so the counts are read
    here rather than passed in; App\Support\Nav\NavEffects memoises them on the
    request, so asking once per menu item costs one lookup in total. They are
    already filtered to what the reader may open, so nothing here needs to
    re-check permissions.

    The badge is spent when the reader opens the screen it points at.
--}}
@php($navEffectCount = \App\Support\Nav\NavEffects::counts(request())[$for] ?? 0)

@if($navEffectCount > 0)
    <span class="nav-effect-badge" title="{{ $navEffectCount }} new since you last opened this">
        {{ $navEffectCount > 99 ? '99+' : $navEffectCount }}
    </span>
@endif

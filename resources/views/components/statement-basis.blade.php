@props([
    'basis' => null,
])

@php
    // Financial statements here count posted entries only. A period whose work
    // is still sitting in draft produces a statement that looks wrong rather
    // than unfinished, so the page has to say which of the two it is before the
    // reader draws a conclusion from the figures.
    $basis = is_array($basis) ? $basis : [];

    $posted = (int) ($basis['posted_count'] ?? 0);
    $pending = (int) ($basis['pending_count'] ?? 0);
    $pendingTotal = (float) ($basis['pending_total'] ?? 0);
    $drafts = (int) ($basis['draft_count'] ?? 0);
    $approved = (int) ($basis['approved_count'] ?? 0);

    $hasBacklog = $pending > 0;

    // Name the statuses only when both are present; "3 draft and 1 approved"
    // is worth the words, "3 draft" on its own is not.
    $breakdown = ($drafts > 0 && $approved > 0)
        ? " ({$drafts} draft, {$approved} approved)"
        : '';

    // Built here rather than with directives inline in the sentence: Blade will
    // not compile an @if that sits flush against the preceding word, and the
    // stray @endif then closes the wrong block.
    $isAre = $pending === 1 ? 'is' : 'are';
    $theyAre = $pending === 1 ? 'it is' : 'they are';
@endphp

<div {{ $attributes->merge(['class' => 'statement-basis' . ($hasBacklog ? ' statement-basis--pending' : '')]) }}>
    <i class="bi {{ $hasBacklog ? 'bi-exclamation-triangle' : 'bi-check-circle' }}" aria-hidden="true"></i>

    <span class="statement-basis__text">
        @if($posted === 0 && ! $hasBacklog)
            Nothing has been posted in this period, so there is nothing to report.
        @elseif($hasBacklog)
            Built from <span class="statement-basis__figure">{{ number_format($posted) }}</span>
            posted {{ Str::plural('entry', $posted) }}.
            <strong>{{ number_format($pending) }}</strong>
            further {{ Str::plural('entry', $pending) }} worth
            <span class="statement-basis__figure">${{ number_format($pendingTotal, 2) }}</span>
            {{ $isAre }} not on the ledger yet{{ $breakdown }}, so {{ $theyAre }}
            excluded from these figures.
        @else
            Built from <span class="statement-basis__figure">{{ number_format($posted) }}</span>
            posted {{ Str::plural('entry', $posted) }}. Everything in this period is on the ledger.
        @endif
    </span>

    @if($hasBacklog)
        <a href="{{ route('journal-entries.index', ['status' => 'draft']) }}"
           class="btn btn-sm btn-outline-primary d-print-none">
            Review entries
        </a>
    @endif
</div>

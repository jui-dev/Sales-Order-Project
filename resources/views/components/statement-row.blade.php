@props([
    'label' => '',
    'code' => null,
    'amount' => null,
    'variant' => null,   // deduction | subtotal | result | total
    'note' => null,
    'muted' => false,
])

@php
    // A global composer in AppServiceProvider replaces null view data with an
    // empty Collection, and an object is truthy - so an omitted prop would
    // otherwise render as a stray element or be cast to string and throw.
    // Normalise every optional prop back to null before using it.
    $code = (is_string($code) || is_numeric($code)) && $code !== '' ? $code : null;
    $note = is_string($note) && $note !== '' ? $note : null;
    $variant = is_string($variant) ? $variant : null;
    $amount = is_numeric($amount) ? $amount : null;

    // Accounting sets a negative figure in brackets rather than with a minus
    // sign, because a minus is easy to miss at the end of a column of digits.
    $value = $amount === null ? null : round((float) $amount, 2);
    $isNegative = $value !== null && $value < 0;

    $display = $value === null
        ? '—'
        : ($isNegative
            ? '($' . number_format(abs($value), 2) . ')'
            : '$' . number_format($value, 2));

    $rowClass = match ($variant) {
        'deduction' => 'statement__row statement__row--deduction',
        'subtotal'  => 'statement__row statement__row--subtotal',
        'result'    => 'statement__row statement__row--result',
        'total'     => 'statement__row statement__row--total',
        default     => 'statement__row',
    };

    // Colour is reserved for the lines a reader stops on. Tinting every
    // negative line item would make an ordinary contra account look like a
    // problem.
    $emphasised = in_array($variant, ['result', 'total'], true);

    $amountClass = 'statement__amount';
    if ($emphasised && $isNegative) {
        $amountClass .= ' statement__amount--negative';
    } elseif ($muted || $value === null) {
        $amountClass .= ' statement__amount--muted';
    }
@endphp

<div class="{{ $rowClass }}">
    <span class="statement__label">
        @if($code)
            <span class="statement__code">{{ $code }}</span>
        @endif
        {{ $variant === 'deduction' ? 'less: ' : '' }}{{ $label }}
    </span>
    <span class="{{ $amountClass }}">{{ $display }}</span>
</div>

@if($note)
    <div class="statement__meta">{{ $note }}</div>
@endif

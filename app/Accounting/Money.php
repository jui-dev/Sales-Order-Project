<?php

namespace App\Accounting;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An amount of money, held as whole minor units.
 *
 * The ledger used to add and compare PHP floats and then call round() on the
 * result - including `round($debit, 2) === round($credit, 2)`, which compares
 * two floats for exact identity. That works until it doesn't: 0.1 + 0.2 is not
 * 0.3 in binary, and an entry can fail to balance by a value no report can
 * show. Integers cannot drift, so balance is decidable.
 *
 * Immutable. Every operation returns a new instance.
 */
final class Money implements JsonSerializable, Stringable
{
    /**
     * Minor units per major unit, derived from the configured scale.
     *
     * This was a hardcoded 100 while scaleDigits() read config('accounting.
     * scale'), so the two disagreed the moment anybody used the setting:
     * parse() padded the fraction to the configured width and then multiplied
     * the whole part by 100 regardless, and toDecimal() padded to the
     * configured width while dividing by 100. At any scale but 2 every amount
     * in the system came out wrong, quietly.
     */
    private static function factor(): int
    {
        return 10 ** self::scaleDigits();
    }

    private function __construct(
        public readonly int $minor,
    ) {
    }

    // ------------------------------------------------------------------
    // Construction
    // ------------------------------------------------------------------

    public static function zero(): self
    {
        return new self(0);
    }

    public static function ofMinor(int $minor): self
    {
        return new self($minor);
    }

    /**
     * From a major-unit amount: 12.34, "12.34", or 12.
     *
     * Strings are parsed digit by digit rather than cast, because casting
     * "0.145" to float and multiplying re-introduces exactly the binary
     * error this class exists to avoid. Floats are formatted to the ledger's
     * scale first, which is the only safe way to read one.
     */
    public static function of(int|float|string $amount): self
    {
        if (is_int($amount)) {
            return new self($amount * self::factor());
        }

        if (is_float($amount)) {
            if (! is_finite($amount)) {
                throw new InvalidArgumentException('Cannot make money from a non-finite number.');
            }

            $amount = sprintf('%.' . self::scaleDigits() . 'F', $amount);
        }

        return self::parse(trim($amount));
    }

    /**
     * A quantity times a unit cost, rounded to the ledger's scale.
     *
     * Unit costs are carried at four decimal places in the costing ledger;
     * this is the one place they are allowed to become a journal amount.
     */
    public static function fromUnitCost(int|float $quantity, int|float|string $unitCost): self
    {
        return self::of((float) $quantity * (float) $unitCost);
    }

    private static function parse(string $amount): self
    {
        if (! preg_match('/^(?<sign>[-+])?(?<whole>\d+)(?:\.(?<fraction>\d+))?$/', $amount, $m)) {
            // Database drivers hand back sums in exponent form - a column that
            // nets to nothing can arrive as "-1.4210854715202E-14" - and PHP
            // renders large floats the same way. Numeric input is normalised
            // rather than refused; only genuine nonsense throws.
            if (is_numeric($amount)) {
                return self::parse(sprintf('%.' . self::scaleDigits() . 'F', (float) $amount));
            }

            throw new InvalidArgumentException(sprintf('"%s" is not a valid money amount.', $amount));
        }

        $digits = self::scaleDigits();
        $fraction = $m['fraction'] ?? '';

        // Round half-up on the first discarded digit rather than truncating,
        // so a cost of 0.125 becomes 0.13 and not 0.12.
        $roundUp = strlen($fraction) > $digits && (int) $fraction[$digits] >= 5;

        $fraction = str_pad(substr($fraction, 0, $digits), $digits, '0');

        $minor = (int) $m['whole'] * self::factor() + (int) $fraction;

        if ($roundUp) {
            $minor++;
        }

        return new self(($m['sign'] ?? '') === '-' ? -$minor : $minor);
    }

    private static function scaleDigits(): int
    {
        return (int) config('accounting.scale', 2);
    }

    // ------------------------------------------------------------------
    // Arithmetic
    // ------------------------------------------------------------------

    public function plus(self $other): self
    {
        return new self($this->minor + $other->minor);
    }

    public function minus(self $other): self
    {
        return new self($this->minor - $other->minor);
    }

    public function negated(): self
    {
        return new self(-$this->minor);
    }

    public function absolute(): self
    {
        return new self(abs($this->minor));
    }

    /** @param iterable<self> $amounts */
    public static function sum(iterable $amounts): self
    {
        $total = 0;

        foreach ($amounts as $amount) {
            $total += $amount->minor;
        }

        return new self($total);
    }

    // ------------------------------------------------------------------
    // Comparison
    // ------------------------------------------------------------------

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    // ------------------------------------------------------------------
    // Output
    // ------------------------------------------------------------------

    /** The value for a decimal column, e.g. "12.34". */
    public function toDecimal(): string
    {
        $digits = self::scaleDigits();
        $factor = self::factor();
        $sign = $this->minor < 0 ? '-' : '';
        $minor = abs($this->minor);

        return $sign . intdiv($minor, $factor) . '.' . str_pad((string) ($minor % $factor), $digits, '0', STR_PAD_LEFT);
    }

    /** Only for display and for handing to code that still wants a float. */
    public function toFloat(): float
    {
        return $this->minor / self::factor();
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }

    public function jsonSerialize(): string
    {
        return $this->toDecimal();
    }
}

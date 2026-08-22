<?php

namespace App\Accounting\Reconciliation;

use App\Accounting\Money;

/**
 * One thing that ought to be true about the books, and whether it is.
 */
final class Check
{
    private function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $explanation,
        public readonly Money $ledger,
        public readonly Money $expected,
        public readonly Money $difference,
        public readonly bool $passed,
        /**
         * How far apart the two sides may legitimately be.
         *
         * Zero for anything that is two routes to the same arithmetic. Not
         * zero where the two sides are computed at different scales - stock is
         * valued at a four-decimal average cost and posted at two, so a penny
         * of rounding per line is expected rather than a fault.
         */
        public readonly Money $tolerance,
        /** @var array<int,array{label:string,ledger:Money,expected:Money,difference:Money}> */
        public readonly array $breakdown = [],
    ) {
    }

    /**
     * @param array<int,array{label:string,ledger:Money,expected:Money,difference:Money}> $breakdown
     */
    public static function make(
        string $key,
        string $title,
        string $explanation,
        Money $ledger,
        Money $expected,
        array $breakdown = [],
        ?Money $tolerance = null,
    ): self {
        $difference = $ledger->minus($expected);
        $tolerance ??= Money::zero();

        return new self(
            key: $key,
            title: $title,
            explanation: $explanation,
            ledger: $ledger,
            expected: $expected,
            difference: $difference,
            passed: abs($difference->minor) <= $tolerance->minor,
            tolerance: $tolerance,
            breakdown: $breakdown,
        );
    }

    public function status(): string
    {
        return $this->passed ? 'passed' : 'failed';
    }

    /** Only the rows that disagree - what someone actually has to look at. */
    public function discrepancies(): array
    {
        return array_values(array_filter(
            $this->breakdown,
            fn (array $row) => abs($row['difference']->minor) > $this->tolerance->minor,
        ));
    }
}

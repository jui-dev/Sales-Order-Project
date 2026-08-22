<?php

namespace App\Accounting\Exceptions;

use App\Accounting\Money;
use RuntimeException;

class UnbalancedEntry extends RuntimeException
{
    public static function of(Money $debit, Money $credit, string $context): self
    {
        return new self(sprintf(
            'Journal entry for %s does not balance: debits %s, credits %s, difference %s.',
            $context,
            $debit->toDecimal(),
            $credit->toDecimal(),
            $debit->minus($credit)->toDecimal(),
        ));
    }
}

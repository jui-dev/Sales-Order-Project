<?php

namespace App\Accounting\Exceptions;

use RuntimeException;

/**
 * A line was built without a dimension its account requires.
 *
 * Control accounts are only reconcilable against their subsidiary ledger if
 * every single line carries the party, so a missing one is refused at build
 * time rather than discovered later as a variance nobody can explain.
 */
class MissingDimension extends RuntimeException
{
    public static function party(string $account, string $controlOf): self
    {
        return new self(sprintf(
            'A line against %s must name the %s it belongs to: it is a control account.',
            $account,
            $controlOf,
        ));
    }

    public static function location(string $account): self
    {
        return new self(sprintf(
            'A line against %s must name the stock location it belongs to.',
            $account,
        ));
    }
}

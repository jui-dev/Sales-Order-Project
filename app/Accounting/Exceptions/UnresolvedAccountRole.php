<?php

namespace App\Accounting\Exceptions;

use App\Accounting\AccountRole;
use RuntimeException;

/**
 * A role could not be turned into an account that can be posted to.
 *
 * This is always a configuration fault rather than a data fault, so it says
 * which role failed and why. The old message - "Account not found for line." -
 * named neither the account nor the caller, which made a missing 5100 look
 * like a broken credit note.
 */
class UnresolvedAccountRole extends RuntimeException
{
    public static function undefined(AccountRole $role): self
    {
        return new self(sprintf(
            'Account role "%s" has no definition in config/accounting.php.',
            $role->value,
        ));
    }

    public static function missing(AccountRole $role, string $code): self
    {
        return new self(sprintf(
            'Account role "%s" maps to code "%s", which is not in the chart of accounts. Run `php artisan accounting:sync-chart`.',
            $role->value,
            $code,
        ));
    }

    public static function notPostable(AccountRole $role, string $code): self
    {
        return new self(sprintf(
            'Account role "%s" maps to code "%s", which is a rollup account and cannot be posted to directly.',
            $role->value,
            $code,
        ));
    }
}

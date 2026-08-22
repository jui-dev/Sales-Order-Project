<?php

namespace App\Accounting;

use App\Accounting\Exceptions\UnresolvedAccountRole;
use App\Models\Account;

/**
 * Turns a role into the account it is currently mapped to.
 *
 * Resolution is cached for the life of the request: a posting run touches the
 * same handful of accounts repeatedly, and the chart cannot change underneath
 * it mid-request.
 */
class AccountResolver
{
    /** @var array<string,Account> */
    private array $cache = [];

    public function for(AccountRole $role): Account
    {
        if (isset($this->cache[$role->value])) {
            return $this->cache[$role->value];
        }

        $code = $role->code();

        $account = Account::where('code', $code)->first();

        if (! $account) {
            throw UnresolvedAccountRole::missing($role, $code);
        }

        if (! $account->is_postable) {
            throw UnresolvedAccountRole::notPostable($role, $code);
        }

        return $this->cache[$role->value] = $account;
    }

    public function idFor(AccountRole $role): int
    {
        return $this->for($role)->id;
    }

    /**
     * The role an account plays, or null when it is outside the mapped chart.
     *
     * Reports need to go the other way - from a line to what it means - to
     * classify a cash movement by its counterpart.
     */
    public function roleFor(Account|string $account): ?AccountRole
    {
        $code = $account instanceof Account ? $account->code : $account;

        foreach (AccountRole::cases() as $role) {
            if ($role->code() === $code) {
                return $role;
            }
        }

        return null;
    }

    public function forget(): void
    {
        $this->cache = [];
    }
}

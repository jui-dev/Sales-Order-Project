<?php

namespace App\Accounting;

use App\Models\Account;
use App\Models\AccountType;
use Illuminate\Support\Facades\DB;

/**
 * Brings the accounts table into line with config/accounting.php.
 *
 * The chart used to be defined in two places that disagreed - a seeder and a
 * service - so which accounts existed depended on how the database had been
 * bootstrapped. One of them called 3000 "Owner's Equity" and the other called
 * it "Retained Earnings". There is now one definition, and this reconciles the
 * table to it.
 */
class ChartOfAccounts
{
    /**
     * Create or update every account the configuration defines.
     *
     * Existing accounts are updated in place, so codes already carrying
     * postings keep their identity and their history.
     *
     * @return int the number of accounts created or updated
     */
    public function sync(): int
    {
        $definitions = config('accounting.chart', []);

        return DB::transaction(function () use ($definitions) {
            $touched = 0;

            foreach ($definitions as $role => $definition) {
                $type = AccountType::firstOrCreate(['name' => $definition['type']]);

                Account::updateOrCreate(
                    ['code' => (string) $definition['code']],
                    [
                        'name'              => $definition['name'],
                        'description'       => $definition['description'] ?? null,
                        'account_type_id'   => $type->id,
                        'is_contra'         => (bool) ($definition['contra'] ?? false),
                        'is_postable'       => (bool) ($definition['postable'] ?? true),
                        'control_of'        => $definition['control_of'] ?? null,
                        'requires_location' => (bool) ($definition['requires_location'] ?? false),
                    ],
                );

                $touched++;
            }

            return $touched;
        });
    }

    /**
     * Every role whose account is missing or unpostable.
     *
     * Called at boot and from the health check, so a chart that cannot serve
     * a posting rule is reported before a document fails rather than after.
     *
     * @return array<int,string>
     */
    public function unresolvableRoles(): array
    {
        $codes = Account::query()
            ->whereIn('code', array_map(
                fn (AccountRole $role) => $role->code(),
                AccountRole::cases(),
            ))
            ->where('is_postable', true)
            ->pluck('code')
            ->all();

        $missing = [];

        foreach (AccountRole::cases() as $role) {
            if (! in_array($role->code(), $codes, true)) {
                $missing[] = $role->value;
            }
        }

        return $missing;
    }
}

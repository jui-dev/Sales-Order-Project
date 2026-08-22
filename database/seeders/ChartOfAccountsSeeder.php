<?php

namespace Database\Seeders;

use App\Accounting\ChartOfAccounts;
use Illuminate\Database\Seeder;

/**
 * The chart of accounts is defined in config/accounting.php and nowhere else.
 *
 * This seeder used to carry its own list, which disagreed with the one in
 * ChartOfAccountsService: 3000 was "Owner's Equity" here and "Retained
 * Earnings" there, and sales returns existed at both 4100 and 5200. Which
 * accounts you got depended on how the database had been bootstrapped.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        app(ChartOfAccounts::class)->sync();
    }
}

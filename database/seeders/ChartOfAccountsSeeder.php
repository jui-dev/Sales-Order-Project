<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountType;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define account types
        $types = [
            'Asset',
            'Liability',
            'Equity',
            'Revenue',
            'Expense',
        ];

        $typeIds = [];
        foreach ($types as $typeName) {
            $type = AccountType::firstOrCreate(['name' => $typeName]);
            $typeIds[$typeName] = $type->id;
        }

        // Define basic accounts (code => [name, type])
        $accounts = [
            '1000' => ['Cash', 'Asset'],
            '1100' => ['Accounts Receivable', 'Asset'],
            '1200' => ['Inventory', 'Asset'],
            '2000' => ['Accounts Payable', 'Liability'],
            '3000' => ["Owner's Equity", 'Equity'],
            '4000' => ['Sales Revenue', 'Revenue'],
            '5000' => ['Cost of Goods Sold', 'Expense'],
            '5100' => ['Purchase Expense', 'Expense'],
            '5200' => ['Sales Returns & Allowances', 'Contra Revenue'], // treat as Revenue type but contra flag
        ];

        foreach ($accounts as $code => [$name, $typeName]) {
            $isContra = false;
            $baseTypeName = $typeName;
            if (str_contains($typeName, 'Contra')) {
                $isContra = true;
                $baseTypeName = trim(str_replace('Contra', '', $typeName));
            }

            Account::firstOrCreate([
                'code' => $code,
            ], [
                'name' => $name,
                'account_type_id' => $typeIds[$baseTypeName] ?? null,
                'opening_balance' => 0,
                'is_contra' => $isContra,
            ]);
        }
    }
} 
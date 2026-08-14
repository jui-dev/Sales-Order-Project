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

        // Define basic accounts (code => [name, type, description])
        $accounts = [
            '1000' => ['Cash', 'Asset', 'Physical cash on hand available for immediate use.'],
            '1100' => ['Accounts Receivable', 'Asset', 'Money owed by customers for goods or services delivered on credit.'],
            '1200' => ['Inventory', 'Asset', 'Value of products or raw materials held for sale or production.'],
            '1300' => ['Intercompany Receivable', 'Asset', 'Amounts owed by related companies or subsidiaries.'],
            // 2000 is the only Accounts Payable. A second one at 2100 used to be
            // seeded alongside it, which let supplier bills and vendor returns post
            // to different accounts so a return could never offset its own bill.
            '2000' => ['Accounts Payable', 'Liability', 'Amounts the business owes to suppliers for goods or services received.'],
            '3000' => ["Owner's Equity", 'Equity', 'Owner\'s initial and additional investment in the business.'],
            '3010' => ['Retained Earnings', 'Equity', 'Accumulated net income not distributed as dividends'],
            '4000' => ['Sales Revenue', 'Revenue', 'Income earned from selling goods or services to customers.'],
            '4100' => ['Sales Returns', 'Contra Revenue', 'Reductions in revenue due to product returns from customers.'],
            '5000' => ['Cost of Goods Sold', 'Expense', 'Direct costs attributable to the production or purchase of goods sold.'],
            '5100' => ['Purchase Returns', 'Contra Expense', 'Reductions in expenses due to product returns to vendors.'],
            '5200' => ['Sales Returns & Allowances', 'Contra Revenue', 'Reductions in revenue due to product returns or sales discounts granted.'], // Revenue type but contra flag
        ];

        foreach ($accounts as $code => [$name, $typeName, $description]) {
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
                'description' => $description,
                'account_type_id' => $typeIds[$baseTypeName] ?? null,
                'opening_balance' => 0,
                'is_contra' => $isContra,
            ]);
        }
    }
} 
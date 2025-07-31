<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountType;
use App\Traits\HasErrorHandling;
use Illuminate\Database\Eloquent\Collection;

class ChartOfAccountsService
{
    use HasErrorHandling;

    /**
     * Ensure default chart of accounts exists
     */
    public function ensureDefaultAccounts(): void
    {
        $this->handleServiceOperation(
            function() {
                $defaultAccounts = [
                    ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'description' => 'Cash on hand and in bank'],
                    ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'description' => 'Amounts owed by customers'],
                    ['code' => '1200', 'name' => 'Inventory', 'type' => 'asset', 'description' => 'Product inventory'],
                    ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'description' => 'Amounts owed to suppliers'],
                    ['code' => '3000', 'name' => 'Retained Earnings', 'type' => 'equity', 'description' => 'Accumulated profits'],
                    ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue', 'description' => 'Revenue from sales'],
                    ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'description' => 'Cost of products sold'],
                ];

                foreach ($defaultAccounts as $accountData) {
                    $accountType = AccountType::where('name', $accountData['type'])->first();
                    
                    if (!$accountType) {
                        $accountType = AccountType::create(['name' => $accountData['type']]);
                    }

                    Account::firstOrCreate(
                        ['code' => $accountData['code']],
                        [
                            'name' => $accountData['name'],
                            'account_type_id' => $accountType->id,
                            'description' => $accountData['description'],
                        ]
                    );
                }
            },
            'chart_of_accounts'
        );
    }

    /**
     * Get all accounts with their types
     */
    public function getAllAccounts(): Collection
    {
        return $this->handleServiceOperation(
            function() {
                return Account::with('accountType')->orderBy('code')->get();
            },
            'accounts'
        );
    }

    /**
     * Get account types for dropdown
     */
    public function getAccountTypes(): Collection
    {
        return $this->getCollectionOrEmpty(AccountType::class, 'account_types');
    }

    /**
     * Get account types for dropdown
     */
    public function getAccountTypesForDropdown(): array
    {
        return $this->handleServiceOperation(
            fn() => AccountType::orderBy('name')->pluck('name', 'id')->toArray(),
            'account_types'
        );
    }

    /**
     * Create new account
     */
    public function createAccount(array $data): Account
    {
        return $this->handleServiceOperation(
            fn() => Account::create($data),
            'account'
        );
    }

    /**
     * Get account with details
     */
    public function getAccountWithDetails(int $id): Account
    {
        return $this->handleServiceOperation(
            function() use ($id) {
                $account = Account::with(['accountType', 'journalEntryLines'])->find($id);
                
                if (!$account) {
                    $this->logMissingData('account', $id);
                    throw new \App\Exceptions\DataNotFoundException('account', $id);
                }
                
                return $account;
            },
            'account',
            $id
        );
    }

    /**
     * Get accounts grouped by parent for hierarchical display
     */
    public function getAccountsGroupedByParent(): array
    {
        return $this->handleServiceOperation(
            function() {
                $accounts = Account::with('accountType')->orderBy('code')->get();
                
                $grouped = [];
                foreach ($accounts as $account) {
                    $type = $account->accountType->name;
                    if (!isset($grouped[$type])) {
                        $grouped[$type] = [];
                    }
                    $grouped[$type][] = $account;
                }
                
                return $grouped;
            },
            'accounts_grouped'
        );
    }

    /**
     * Get filter options for accounts
     */
    public function getFilterOptions(): array
    {
        return [
            'account_types' => $this->getAccountTypesForDropdown(),
        ];
    }

    /**
     * Get sort options for accounts
     */
    public function getSortOptions(): array
    {
        return [
            'code' => 'Account Code',
            'name' => 'Account Name',
            'type' => 'Account Type',
            'balance' => 'Balance',
        ];
    }
} 
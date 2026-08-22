<?php

namespace App\Services;

use App\Accounting\ChartOfAccounts;
use App\Models\Account;
use App\Models\AccountType;
use App\Traits\HasErrorHandling;
use Illuminate\Database\Eloquent\Collection;

class ChartOfAccountsService
{
    use HasErrorHandling;

    /**
     * Ensure the chart of accounts matches the configuration.
     *
     * The definitions used to live here as a hardcoded array that disagreed
     * with ChartOfAccountsSeeder, so which accounts existed depended on how
     * the database had been bootstrapped. config/accounting.php is the single
     * definition now and this reconciles the table to it.
     */
    public function ensureDefaultAccounts(): void
    {
        $this->handleServiceOperation(
            fn () => app(ChartOfAccounts::class)->sync(),
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
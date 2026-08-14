<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Warehouse;
use App\Models\Retailer;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportService
{
    /**
     * Generate daily profit report data
     */
    public function generateDailyProfitReport(array $filters = []): array
    {
        // Determine date range – default to current month
        $startDate = Arr::get($filters, 'start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = Arr::get($filters, 'end_date', Carbon::now()->toDateString());

        // Fetch all order items within the date range whose parent orders are not cancelled
        $orderItems = OrderItem::with(['order', 'product', 'location'])
            ->whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->whereDate('order_date', '>=', $startDate)
                  ->whereDate('order_date', '<=', $endDate)
                  // Include every status except explicitly cancelled so that in-flight
                  // orders still appear in the profitability dashboard.
                  ->where('status', '!=', 'cancelled');
            })
            ->get();

        // Early exit – no data
        if ($orderItems->isEmpty()) {
            return [
                'startDate'     => $startDate,
                'endDate'       => $endDate,
                'dailyProfits'  => collect(),
                'dailyTotals'   => collect(),
                'summary'       => $this->getBlankSummary(),
            ];
        }

        // Build per-item profit records
        $dailyProfits = $orderItems->map(function (OrderItem $item) {
            return $this->buildProfitRecord($item);
        });

        // Aggregate daily totals
        $dailyTotals = $dailyProfits->groupBy('order_date')->map(function (Collection $items) {
            return $this->buildDailyTotal($items);
        })->sortBy('date')->values();

        // Overall summary
        $summary = $this->buildSummary($dailyProfits);

        return [
            'startDate'     => $startDate,
            'endDate'       => $endDate,
            'dailyProfits'  => $dailyProfits,
            'dailyTotals'   => $dailyTotals,
            'summary'       => $summary,
        ];
    }

    /**
     * Generate trial balance report
     */
    public function generateTrialBalanceReport(array $filters = []): array
    {
        $asOfDate = Arr::get($filters, 'as_of_date', Carbon::now()->toDateString());
        
        // Get all accounts with their balances
        $balances = \App\Models\Account::with(['accountType'])
            ->orderBy('code')
            ->get()
            ->map(function ($account) use ($asOfDate) {
                $balance = $this->calculateAccountBalance($account, $asOfDate);
                return [
                    'account' => $account,
                    'debit' => $balance > 0 ? $balance : 0,
                    'credit' => $balance < 0 ? abs($balance) : 0,
                ];
            });

        $totalDebit = $balances->sum('debit');
        $totalCredit = $balances->sum('credit');

        return [
            'endDate' => $asOfDate,
            'balances' => $balances,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'isBalanced' => abs($totalDebit - $totalCredit) < 0.01,
        ];
    }

    /**
     * Generate income statement report
     */
    public function generateIncomeStatementReport(array $filters = []): array
    {
        $startDate = Arr::get($filters, 'start_date', Carbon::now()->startOfYear()->toDateString());
        $endDate = Arr::get($filters, 'end_date', Carbon::now()->toDateString());

        // Accounts are picked by their type rather than by code range. Contra
        // accounts do not sit in the band their sign suggests - Sales Returns &
        // Allowances (5200) is contra revenue despite its 5xxx code - so a range
        // read it as an expense and net income came out wrong twice over.
        $revenueAccounts = \App\Models\Account::whereHas('accountType', function ($q) {
            $q->where('name', 'Revenue');
        })->orderBy('code')->get();

        $revenues = $revenueAccounts->map(function ($account) use ($startDate, $endDate) {
            // Revenue is earned as a credit, so the signed balance is negative.
            // Flipping it - rather than taking the absolute value - is what lets a
            // contra account, which carries a debit balance, come out negative and
            // reduce the total instead of inflating it.
            $balance = $this->calculateAccountsBalance(collect([$account]), $startDate, $endDate);

            return [
                'account' => $account,
                'amount' => -$balance,
            ];
        });
        $totalRevenue = $revenues->sum('amount');

        $expenseAccounts = \App\Models\Account::whereHas('accountType', function ($q) {
            $q->where('name', 'Expense');
        })->orderBy('code')->get();

        $expenses = $expenseAccounts->map(function ($account) use ($startDate, $endDate) {
            // Expenses are incurred as a debit, so the signed balance is already
            // positive; a contra expense such as Purchase Returns (5100) carries a
            // credit balance and correctly subtracts.
            $balance = $this->calculateAccountsBalance(collect([$account]), $startDate, $endDate);

            return [
                'account' => $account,
                'amount' => $balance,
            ];
        });
        $totalExpense = $expenses->sum('amount');

        $netIncome = $totalRevenue - $totalExpense;

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'revenues' => $revenues,
            'expenses' => $expenses,
            'totalRevenue' => $totalRevenue,
            'totalExpense' => $totalExpense,
            'netIncome' => $netIncome,
        ];
    }

    /**
     * Generate balance sheet report
     */
    public function generateBalanceSheetReport(array $filters = []): array
    {
        $asOfDate = Arr::get($filters, 'as_of_date', Carbon::now()->toDateString());

        // Assets (1000-1999)
        $assetAccounts = \App\Models\Account::whereBetween('code', [1000, 1999])->get();
        $assets = $assetAccounts->map(function ($account) use ($asOfDate) {
            return [
                'account' => $account,
                'balance' => $this->calculateAccountBalance($account, $asOfDate),
            ];
        });
        $totalAssets = $this->calculateAccountsBalance($assetAccounts, null, $asOfDate);

        // Liabilities (2000-2999)
        $liabilityAccounts = \App\Models\Account::whereBetween('code', [2000, 2999])->get();
        $liabilities = $liabilityAccounts->map(function ($account) use ($asOfDate) {
            return [
                'account' => $account,
                'balance' => $this->calculateAccountBalance($account, $asOfDate),
            ];
        });
        $totalLiabilities = $this->calculateAccountsBalance($liabilityAccounts, null, $asOfDate);

        // Equity (3000-3999)
        $equityAccounts = \App\Models\Account::whereBetween('code', [3000, 3999])->get();
        $equity = $equityAccounts->map(function ($account) use ($asOfDate) {
            return [
                'account' => $account,
                'balance' => $this->calculateAccountBalance($account, $asOfDate),
            ];
        });
        $totalEquity = $this->calculateAccountsBalance($equityAccounts, null, $asOfDate);

        $liabEqTotal = $totalLiabilities + $totalEquity;

        return [
            'endDate' => $asOfDate,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'totalEquity' => $totalEquity,
            'liabEqTotal' => $liabEqTotal,
        ];
    }

    /**
     * Generate cash flow statement report
     */
    public function generateCashFlowStatementReport(array $filters = []): array
    {
        $startDate = Arr::get($filters, 'start_date', Carbon::now()->startOfYear()->toDateString());
        $endDate = Arr::get($filters, 'end_date', Carbon::now()->toDateString());

        // Get operating activities (revenue and expense accounts)
        $operatingAccounts = \App\Models\Account::whereBetween('code', [4000, 5999])->get();
        $operating = $this->getCashFlowActivities($operatingAccounts, $startDate, $endDate);
        $operatingTotal = $operating->sum('amount');

        // Get investing activities (asset accounts for investing)
        $investingAccounts = \App\Models\Account::whereBetween('code', [1000, 1999])
            ->whereIn('code', [1500, 1600, 1700]) // Fixed assets, investments, etc.
            ->get();
        $investing = $this->getCashFlowActivities($investingAccounts, $startDate, $endDate);
        $investingTotal = $investing->sum('amount');

        // Get financing activities (liability and equity accounts)
        $financingAccounts = \App\Models\Account::whereBetween('code', [2000, 3999])->get();
        $financing = $this->getCashFlowActivities($financingAccounts, $startDate, $endDate);
        $financingTotal = $financing->sum('amount');

        $netChange = $operatingTotal + $investingTotal + $financingTotal;

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'operating' => $operating,
            'investing' => $investing,
            'financing' => $financing,
            'operatingTotal' => $operatingTotal,
            'investingTotal' => $investingTotal,
            'financingTotal' => $financingTotal,
            'netChange' => $netChange,
        ];
    }

    /**
     * Build a profit record for an order item
     */
    private function buildProfitRecord(OrderItem $item): array
    {
        $product = $item->product;
        $locationModel = $item->location;

        // Normalise location type to friendly slug
        $locationType = match ($item->location_type) {
            \App\Models\Warehouse::class => 'warehouse',
            \App\Models\Retailer::class  => 'retailer',
            default => 'other',
        };

        // Guard against missing location records
        $locationName = $locationModel?->name ?? 'Unknown';

        $revenue = (float) $item->unit_price * (int) $item->quantity;
        $cost = (float) ($product->purchase_price ?? 0) * (int) $item->quantity;
        $profit = $revenue - $cost;

        return [
            'order_date'    => $item->order->order_date->toDateString(),
            'product_id'    => $item->product_id,
            'product_name'  => $product->name ?? 'Product',
            'location_type' => $locationType,
            'location_name' => $locationName,
            'quantity_sold' => (int) $item->quantity,
            'revenue'       => round($revenue, 2),
            'cost'          => round($cost, 2),
            'profit'        => round($profit, 2),
        ];
    }

    /**
     * Build daily total from profit records
     */
    private function buildDailyTotal(Collection $items): array
    {
        return [
            'date'             => $items->first()['order_date'],
            'products_count'   => $items->sum('quantity_sold'),
            'total_revenue'    => $items->sum('revenue'),
            'total_cost'       => $items->sum('cost'),
            'total_profit'     => $items->sum('profit'),
            'warehouse_profit' => $items->where('location_type', 'warehouse')->sum('profit'),
            'retailer_profit'  => $items->where('location_type', 'retailer')->sum('profit'),
        ];
    }

    /**
     * Build summary from profit records
     */
    private function buildSummary(Collection $items): array
    {
        $totalRevenue = $items->sum('revenue');
        $totalCost = $items->sum('cost');
        $totalProfit = $items->sum('profit');

        // Warehouse breakdown
        $warehouseItems = $items->where('location_type', 'warehouse');
        $warehouseRevenue = $warehouseItems->sum('revenue');
        $warehouseCost = $warehouseItems->sum('cost');
        $warehouseProfit = $warehouseItems->sum('profit');
        $warehouseProductsSold = $warehouseItems->sum('quantity_sold');
        $warehouseMargin = $warehouseRevenue > 0 ? ($warehouseProfit / $warehouseRevenue) * 100 : 0;

        // Retailer breakdown
        $retailerItems = $items->where('location_type', 'retailer');
        $retailerRevenue = $retailerItems->sum('revenue');
        $retailerCost = $retailerItems->sum('cost');
        $retailerProfit = $retailerItems->sum('profit');
        $retailerProductsSold = $retailerItems->sum('quantity_sold');
        $retailerMargin = $retailerRevenue > 0 ? ($retailerProfit / $retailerRevenue) * 100 : 0;

        return [
            'total_revenue'    => round($totalRevenue, 2),
            'total_cost'       => round($totalCost, 2),
            'total_profit'     => round($totalProfit, 2),
            'profit_margin'    => $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0,
            'warehouse_profit' => round($warehouseProfit, 2),
            'retailer_profit'  => round($retailerProfit, 2),
            'total_orders'     => $items->unique('order_date')->count(),
            'total_products'   => $items->sum('quantity_sold'),
            // Additional fields for view compatibility
            'total_products_sold' => $items->sum('quantity_sold'),
            'average_margin' => $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0,
            'warehouse_products_sold' => $warehouseProductsSold,
            'warehouse_revenue' => round($warehouseRevenue, 2),
            'warehouse_margin' => round($warehouseMargin, 2),
            'retailer_products_sold' => $retailerProductsSold,
            'retailer_revenue' => round($retailerRevenue, 2),
            'retailer_margin' => round($retailerMargin, 2),
        ];
    }

    /**
     * Get blank summary for empty data
     */
    private function getBlankSummary(): array
    {
        return [
            'total_revenue'    => 0,
            'total_cost'       => 0,
            'total_profit'     => 0,
            'profit_margin'    => 0,
            'warehouse_profit' => 0,
            'retailer_profit'  => 0,
            'total_orders'     => 0,
            'total_products'   => 0,
            // Additional fields for view compatibility
            'total_products_sold' => 0,
            'average_margin' => 0,
            'warehouse_products_sold' => 0,
            'warehouse_revenue' => 0,
            'warehouse_margin' => 0,
            'retailer_products_sold' => 0,
            'retailer_revenue' => 0,
            'retailer_margin' => 0,
        ];
    }

    /**
     * Calculate account balance as of a specific date
     */
    private function calculateAccountBalance($account, string $asOfDate): float
    {
        return \App\Models\JournalEntryLine::where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('status', 'posted')
                  ->whereDate('entry_date', '<=', $asOfDate);
            })
            ->get()
            ->sum(function ($line) {
                return $line->debit - $line->credit;
            });
    }

    /**
     * Calculate accounts balance for a date range
     */
    private function calculateAccountsBalance(Collection $accounts, ?string $startDate, string $endDate): float
    {
        $query = \App\Models\JournalEntryLine::whereIn('account_id', $accounts->pluck('id'))
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                // Matches AccountingService::trialBalance, so an approved entry
                // cannot appear in one report and be missing from the other.
                $q->whereIn('status', ['posted', 'approved']);
                if ($startDate) {
                    $q->whereDate('entry_date', '>=', $startDate);
                }
                $q->whereDate('entry_date', '<=', $endDate);
            });

        return $query->get()->sum(function ($line) {
            return $line->debit - $line->credit;
        });
    }

    /**
     * Calculate operating cash flow
     */
    private function calculateOperatingCashFlow(string $startDate, string $endDate): float
    {
        // Simplified calculation - in a real system, this would be more complex
        return $this->calculateAccountsBalance(
            \App\Models\Account::whereBetween('code', [4000, 5999])->get(),
            $startDate,
            $endDate
        );
    }

    /**
     * Calculate investing cash flow
     */
    private function calculateInvestingCashFlow(string $startDate, string $endDate): float
    {
        // Simplified calculation
        return 0.0;
    }

    /**
     * Calculate financing cash flow
     */
    private function calculateFinancingCashFlow(string $startDate, string $endDate): float
    {
        // Simplified calculation
        return 0.0;
    }

    /**
     * Get cash flow activities for a collection of accounts
     */
    private function getCashFlowActivities(Collection $accounts, string $startDate, string $endDate): Collection
    {
        if ($accounts->isEmpty()) {
            return collect();
        }

        return \App\Models\JournalEntryLine::with(['journalEntry', 'account'])
            ->whereIn('account_id', $accounts->pluck('id'))
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                $q->where('status', 'posted')
                  ->whereDate('entry_date', '>=', $startDate)
                  ->whereDate('entry_date', '<=', $endDate);
            })
            ->get()
            ->map(function ($line) {
                return [
                    'entry' => $line->journalEntry,
                    'account' => $line->account,
                    'amount' => $line->debit - $line->credit,
                    'description' => $line->journalEntry->description ?? 'Journal Entry #' . $line->journalEntry->id,
                ];
            })
            ->filter(function ($item) {
                return abs($item['amount']) > 0.01; // Only include non-zero amounts
            });
    }
} 
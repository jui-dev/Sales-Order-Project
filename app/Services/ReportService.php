<?php

namespace App\Services;

use App\Accounting\AccountRole;
use App\Accounting\LedgerService;
use App\Models\OrderItem;
use App\Models\Warehouse;
use App\Models\Retailer;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportService
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {
    }

    /**
     * Accounts that make up cost of sales rather than operating expense.
     *
     * 5000 is cost of goods sold and 5100 is its contra (goods sent back to a
     * vendor), so they belong above the gross profit line. Everything else in
     * the expense range is an operating cost below it.
     */
    private const COST_OF_SALES_CODES = ['5000', '5100'];

    /** The account cash actually moves through. */
    private const CASH_CODE = '1000';

    /**
     * Describe what a statement was built from.
     *
     * Every statement counts posted entries only. Entries that are still draft
     * or approved carry no financial effect yet, so a period with a large
     * backlog produces a statement that looks empty rather than incomplete.
     * Reporting the backlog alongside the figures is what tells those two
     * situations apart.
     */
    public function statementBasis(?string $startDate = null, ?string $endDate = null): array
    {
        $inPeriod = function ($query) use ($startDate, $endDate) {
            if ($startDate) {
                $query->whereDate('entry_date', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('entry_date', '<=', $endDate);
            }

            return $query;
        };

        $postedCount = $inPeriod(
            \App\Models\JournalEntry::where('status', \App\Models\JournalEntry::STATUS_POSTED)
        )->count();

        $pending = $inPeriod(
            \App\Models\JournalEntry::whereIn('status', [
                \App\Models\JournalEntry::STATUS_DRAFT,
                \App\Models\JournalEntry::STATUS_APPROVED,
            ])
        )->withSum('lines as total_debit', 'debit')->get();

        $draftCount = $pending->where('status', \App\Models\JournalEntry::STATUS_DRAFT)->count();
        $approvedCount = $pending->where('status', \App\Models\JournalEntry::STATUS_APPROVED)->count();

        return [
            'posted_count'   => $postedCount,
            'pending_count'  => $pending->count(),
            'pending_total'  => round((float) $pending->sum('total_debit'), 2),
            'draft_count'    => $draftCount,
            'approved_count' => $approvedCount,
            'is_complete'    => $pending->isEmpty(),
        ];
    }

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
        $allAccounts = \App\Models\Account::with(['accountType'])
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

        // An account with no balance contributes nothing to either column, and
        // the seeded chart leaves enough
        // of them to bury the accounts that are actually carrying value. The
        // count is reported so the omission is stated rather than silent.
        $balances = $allAccounts
            ->reject(fn($row) => $row['debit'] < 0.005 && $row['credit'] < 0.005)
            ->values();

        $totalDebit = round($balances->sum('debit'), 2);
        $totalCredit = round($balances->sum('credit'), 2);

        return [
            'endDate' => $asOfDate,
            'balances' => $balances,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'isBalanced' => abs($totalDebit - $totalCredit) < 0.01,
            'difference' => round($totalDebit - $totalCredit, 2),
            'emptyAccountCount' => $allAccounts->count() - $balances->count(),
            'accountCount' => $allAccounts->count(),
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

        // ------------------------------------------------------------------
        // Group the same figures into the shape an income statement is read in.
        // A flat list of revenue accounts and expense accounts hides the line
        // the reader actually came for: gross profit. Splitting cost of sales
        // out of operating expense is what makes that line available, and
        // separating contra accounts lets them read as deductions from the
        // section they reduce rather than as negative members of it.
        // ------------------------------------------------------------------
        // An account with no movement in the period is not a line of the story,
        // and the seeded chart carries several that rarely move. Dropping them
        // matches the balance sheet and trial balance. Totals are taken from the
        // unfiltered set above, so nothing removed here can change a total.
        $withActivity = fn($rows) => $rows->reject(fn($row) => abs($row['amount']) < 0.005)->values();

        $grossRevenue = $withActivity($revenues->reject(fn($row) => $row['account']->is_contra));
        $revenueDeductions = $withActivity($revenues->filter(fn($row) => $row['account']->is_contra));
        $netRevenue = $totalRevenue;

        $costOfSales = $withActivity(
            $expenses->filter(fn($row) => in_array($row['account']->code, self::COST_OF_SALES_CODES, true))
        );
        $operatingExpenses = $withActivity(
            $expenses->reject(fn($row) => in_array($row['account']->code, self::COST_OF_SALES_CODES, true))
        );

        $totalCostOfSales = $costOfSales->sum('amount');
        $totalOperatingExpenses = $operatingExpenses->sum('amount');
        $grossProfit = $netRevenue - $totalCostOfSales;

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            // Retained so the PDF view keeps working unchanged.
            'revenues' => $revenues,
            'expenses' => $expenses,
            'totalRevenue' => $totalRevenue,
            'totalExpense' => $totalExpense,
            'netIncome' => $netIncome,
            // Statement sections.
            'grossRevenue' => $grossRevenue,
            'revenueDeductions' => $revenueDeductions,
            'netRevenue' => $netRevenue,
            'costOfSales' => $costOfSales,
            'totalCostOfSales' => $totalCostOfSales,
            'grossProfit' => $grossProfit,
            'operatingExpenses' => $operatingExpenses,
            'totalOperatingExpenses' => $totalOperatingExpenses,
            // A margin against zero or negative revenue is not a number anyone
            // can act on, so it is withheld rather than shown as 0%.
            'grossMargin' => $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 1) : null,
            'netMargin' => $netRevenue > 0 ? round(($netIncome / $netRevenue) * 100, 1) : null,
        ];
    }

    /**
     * Generate balance sheet report
     */
    public function generateBalanceSheetReport(array $filters = []): array
    {
        $asOfDate = Arr::get($filters, 'as_of_date', Carbon::now()->toDateString());

        // ------------------------------------------------------------------
        // Balances are stored signed as debit minus credit. Assets are read in
        // that form directly, but liabilities and equity are credit-balance
        // accounts, so presenting the raw figure showed every one of them as a
        // negative. They are flipped here to the side they are actually read
        // on, which is also what makes the equation below legible.
        // ------------------------------------------------------------------
        $section = function (string $type, bool $flipSign) use ($asOfDate) {
            // Selected by account type rather than by code range. A range is a
            // string comparison over the code column, so it depended on the
            // numbering happening to line up with the classification - and it
            // disagreed with the income statement, which has always gone by
            // type.
            $accounts = \App\Models\Account::whereHas('accountType', fn ($q) => $q->where('name', $type))
                ->orderBy('code')
                ->get();

            $rows = $accounts->map(function ($account) use ($asOfDate, $flipSign) {
                $balance = $this->calculateAccountBalance($account, $asOfDate);

                return [
                    'account' => $account,
                    'balance' => $flipSign ? -$balance : $balance,
                ];
            });

            // A chart of accounts carries accounts that may never have been
            // posted to. Listing them at zero buries the accounts that matter.
            return $rows->reject(fn($row) => abs($row['balance']) < 0.005)->values();
        };

        $assets = $section('Asset', false);
        $liabilities = $section('Liability', true);
        $equity = $section('Equity', true);

        $totalAssets = round($assets->sum('balance'), 2);
        $totalLiabilities = round($liabilities->sum('balance'), 2);
        $postedEquity = round($equity->sum('balance'), 2);

        // ------------------------------------------------------------------
        // Profit earned since the last close belongs to the owners but has not
        // reached an equity account yet, so it is shown as its own line.
        //
        // Closing entries are counted here, unlike on the income statement:
        // a closed period has already moved its result into retained earnings,
        // so its revenue and expense net to nil and only the open period is
        // left. This used to cover all of history, because nothing ever posted
        // a closing entry.
        // ------------------------------------------------------------------
        $revenueAccounts = \App\Models\Account::whereHas('accountType', fn($q) => $q->where('name', 'Revenue'))->get();
        $expenseAccounts = \App\Models\Account::whereHas('accountType', fn($q) => $q->where('name', 'Expense'))->get();

        $revenueToDate = -$this->ledger->balanceOf($revenueAccounts, $asOfDate)->toFloat();
        $expenseToDate = $this->ledger->balanceOf($expenseAccounts, $asOfDate)->toFloat();
        $currentPeriodEarnings = round($revenueToDate - $expenseToDate, 2);

        $totalEquity = round($postedEquity + $currentPeriodEarnings, 2);
        $liabEqTotal = round($totalLiabilities + $totalEquity, 2);

        return [
            'endDate' => $asOfDate,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'postedEquity' => $postedEquity,
            'currentPeriodEarnings' => $currentPeriodEarnings,
            'totalEquity' => $totalEquity,
            'liabEqTotal' => $liabEqTotal,
            'isBalanced' => abs($totalAssets - $liabEqTotal) < 0.01,
            'difference' => round($totalAssets - $liabEqTotal, 2),
        ];
    }

    /**
     * Generate cash flow statement report
     */
    public function generateCashFlowStatementReport(array $filters = []): array
    {
        $startDate = Arr::get($filters, 'start_date', Carbon::now()->startOfYear()->toDateString());
        $endDate = Arr::get($filters, 'end_date', Carbon::now()->toDateString());

        $empty = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'operating' => collect(),
            'investing' => collect(),
            'financing' => collect(),
            'operatingTotal' => 0.0,
            'investingTotal' => 0.0,
            'financingTotal' => 0.0,
            'netChange' => 0.0,
            'openingCash' => 0.0,
            'closingCash' => 0.0,
            'reconciles' => true,
        ];

        $cash = \App\Models\Account::where('code', self::CASH_CODE)->first();

        if (! $cash) {
            return $empty;
        }

        // ------------------------------------------------------------------
        // Cash flow is read off the cash account itself. The previous version
        // summed the revenue and expense accounts, which measures profit, not
        // cash - a credit sale moved that total without any money arriving.
        // Every posted line against 1000 is a real movement of money, and the
        // other side of the same entry says what the money was for.
        // ------------------------------------------------------------------
        $lines = \App\Models\JournalEntryLine::with(['journalEntry.lines.account'])
            ->where('account_id', $cash->id)
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                $q->where('status', \App\Models\JournalEntry::STATUS_POSTED)
                  ->whereDate('entry_date', '>=', $startDate)
                  ->whereDate('entry_date', '<=', $endDate);
            })
            ->get();

        $buckets = ['operating' => [], 'investing' => [], 'financing' => []];

        foreach ($lines as $line) {
            $entry = $line->journalEntry;
            $amount = round((float) $line->debit - (float) $line->credit, 2);

            if ($amount == 0.0) {
                continue;
            }

            $counterparts = $entry->lines
                ->reject(fn($l) => $l->account_id === $cash->id)
                ->map(fn($l) => $l->account?->code)
                ->filter()
                ->values();

            $buckets[$this->classifyCashFlow($counterparts)][] = [
                'entry' => $entry,
                'date' => $entry->entry_date,
                'reference' => $entry->formatted_id,
                'description' => $entry->description ?: 'Journal entry ' . $entry->formatted_id,
                'amount' => $amount,
            ];
        }

        $operating = collect($buckets['operating']);
        $investing = collect($buckets['investing']);
        $financing = collect($buckets['financing']);

        $operatingTotal = round($operating->sum('amount'), 2);
        $investingTotal = round($investing->sum('amount'), 2);
        $financingTotal = round($financing->sum('amount'), 2);
        $netChange = round($operatingTotal + $investingTotal + $financingTotal, 2);

        // Cash on hand before the period opened, and where it should land. The
        // two are shown on the statement so the movement can be checked against
        // the balance rather than taken on trust.
        $openingCash = round(
            $this->calculateAccountsBalance(collect([$cash]), null, Carbon::parse($startDate)->subDay()->toDateString()),
            2
        );
        $closingCash = round($this->calculateAccountBalance($cash, $endDate), 2);

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
            'openingCash' => $openingCash,
            'closingCash' => $closingCash,
            'reconciles' => abs(($openingCash + $netChange) - $closingCash) < 0.01,
        ];
    }

    /**
     * Decide which activity a cash movement belongs to, from the accounts on
     * the other side of the entry.
     *
     * Buying or selling long-lived assets is investing; owner capital and
     * borrowing are financing; everything else is the trading the business
     * exists to do.
     */
    private function classifyCashFlow(Collection $counterpartCodes): string
    {
        foreach ($counterpartCodes as $code) {
            $numeric = (int) $code;

            if ($numeric >= 1500 && $numeric <= 1799) {
                return 'investing';
            }

            if (($numeric >= 2500 && $numeric <= 2999) || ($numeric >= 3000 && $numeric <= 3999)) {
                return 'financing';
            }
        }

        return 'operating';
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

        // Cost comes off the line as captured at the time of sale. Reading the
        // product's current purchase_price here meant every posted goods receipt
        // retroactively rewrote the profit on every order already reported on.
        $revenue = (float) $item->unit_price * (int) $item->quantity;
        $cost = (float) $item->unit_cost * (int) $item->quantity;
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
        return $this->ledger->balance($account, $asOfDate)->toFloat();
    }

    /**
     * Movement across a set of accounts within a period.
     *
     * Closing entries are excluded: they are dated on the last day of the
     * period they close, so a reader that counted them would report nil
     * revenue and nil expense for every period that has been closed.
     */
    private function calculateAccountsBalance(Collection $accounts, ?string $startDate, string $endDate): float
    {
        return $this->ledger
            ->excludingClosingEntries()
            ->balanceOf($accounts, $endDate, $startDate)
            ->toFloat();
    }
} 
<?php

namespace App\Services;

use App\Accounting\AccountRole;
use App\Accounting\LedgerService;
use App\Accounting\Money;
use App\Models\Product;
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

    /**
     * The same two, as roles, for readers that go by role rather than by code.
     *
     * Cost of goods sold and its contra - goods sent back to a vendor - are
     * what sits above the gross profit line.
     */
    private const COST_OF_SALES_ROLES = [
        AccountRole::CostOfGoodsSold,
        AccountRole::PurchaseReturns,
    ];

    /**
     * Everything that makes up net revenue: gross sales less the two contra
     * accounts that reduce it. Summed signed, so returns and discounts net
     * themselves off without anything having to know which is which.
     */
    private const REVENUE_ROLES = [
        AccountRole::SalesRevenue,
        AccountRole::SalesDiscount,
        AccountRole::SalesReturns,
    ];

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
     * Profit for each day in a range, read off the ledger.
     *
     * This used to sum order_items directly, filtered only to orders that were
     * not cancelled. A sale that had been returned and credited therefore went
     * on reporting its full revenue and profit for ever - the credit note lives
     * on the invoice, not the order, so nothing here could ever see it - and a
     * pending order that had shipped nothing booked profit on the day it was
     * typed. The page contradicted the income statement by construction.
     *
     * It is now derived the way every other statement is: by summing the lines
     * of posted entries. Returns arrive for free, because the posting rules
     * already write them to contra revenue and back out of cost of sales.
     *
     * Two consequences, both stated on the page:
     *
     *  - Only posted documents appear. An order that has not been invoiced is
     *    not yet revenue, so this reports what was earned rather than what is
     *    hoped for. statementBasis() carries the unposted backlog so that a
     *    quiet month and an unposted one do not look alike.
     *
     *  - Revenue is dated to the invoice and cost to the shipment, so one day
     *    can show either without the other. The period total is unaffected.
     */
    public function generateDailyProfitReport(array $filters = []): array
    {
        $startDate = Arr::get($filters, 'start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = Arr::get($filters, 'end_date', Carbon::now()->toDateString());

        $ledger = $this->ledger->excludingClosingEntries();

        // Balances are signed debit minus credit throughout. Revenue is earned
        // as a credit, so it reads negative and is flipped here; cost is a
        // debit and is read as it stands. A contra account carries the opposite
        // sign of its type, which is what lets returns and discounts reduce the
        // total by being summed with it rather than special-cased.
        $daily = fn (array $roles) => $ledger->dailyMovement($roles, $startDate, $endDate);

        $grossRevenueByDay = $daily([AccountRole::SalesRevenue]);
        $returnsByDay      = $daily([AccountRole::SalesReturns]);
        $discountsByDay    = $daily([AccountRole::SalesDiscount]);
        $costByDay         = $daily(self::COST_OF_SALES_ROLES);

        $dates = collect()
            ->merge($grossRevenueByDay->keys())
            ->merge($returnsByDay->keys())
            ->merge($discountsByDay->keys())
            ->merge($costByDay->keys())
            ->unique()
            ->sort()
            ->values();

        $detail = $this->buildProfitDetail($ledger, $startDate, $endDate);

        $dailyTotals = $dates->map(function (string $date) use (
            $grossRevenueByDay, $returnsByDay, $discountsByDay, $costByDay, $detail
        ) {
            $gross     = -$this->amountOn($grossRevenueByDay, $date);
            $returns   = $this->amountOn($returnsByDay, $date);
            $discounts = $this->amountOn($discountsByDay, $date);
            $cost      = $this->amountOn($costByDay, $date);

            $revenue = $gross - $returns - $discounts;
            $rows = $detail->where('date', $date);

            return [
                'date'             => $date,
                'products_count'   => $rows->pluck('product_id')->unique()->count(),
                'gross_revenue'    => round($gross, 2),
                'total_returns'    => round($returns, 2),
                'total_discounts'  => round($discounts, 2),
                'total_revenue'    => round($revenue, 2),
                'total_cost'       => round($cost, 2),
                'total_profit'     => round($revenue - $cost, 2),
                'warehouse_profit' => round($rows->where('location_type', 'warehouse')->sum('profit'), 2),
                'retailer_profit'  => round($rows->where('location_type', 'retailer')->sum('profit'), 2),
            ];
        });

        return [
            'startDate'    => $startDate,
            'endDate'      => $endDate,
            'dailyProfits' => $detail,
            'dailyTotals'  => $dailyTotals,
            'summary'      => $dailyTotals->isEmpty()
                ? $this->getBlankSummary()
                : $this->buildSummary($dailyTotals, $detail),
            // What the figures were built from, so an unposted backlog is
            // stated rather than read as a quiet month.
            'basis'        => $this->statementBasis($startDate, $endDate),
        ];
    }

    /**
     * One row per product, location and day.
     *
     * Revenue, returns and cost of sales all carry the product and the location
     * they belong to, so profit per product is a grouping of the same accounts
     * the totals come from rather than a second calculation that has to be kept
     * in step with the first.
     *
     * Lines with no product - an invoice posted without a line breakdown - are
     * not in here, which is why the summary takes its totals from the accounts
     * themselves and reports the difference as unattributed rather than letting
     * the detail quietly disagree with the headline.
     */
    private function buildProfitDetail(LedgerService $ledger, string $startDate, string $endDate): Collection
    {
        $revenue = $ledger->movementByProductAndLocation(self::REVENUE_ROLES, $startDate, $endDate);
        $cost = $ledger->movementByProductAndLocation(self::COST_OF_SALES_ROLES, $startDate, $endDate);

        $key = fn (array $row) => $row['date'] . '|' . $row['product_id'] . '|'
            . $row['location_type'] . ':' . $row['location_id'];

        $rows = [];

        foreach ($revenue as $row) {
            $k = $key($row);
            $rows[$k] = ($rows[$k] ?? $row + ['revenue' => 0.0, 'cost' => 0.0]);
            $rows[$k]['revenue'] += -$row['amount']->toFloat();
        }

        foreach ($cost as $row) {
            $k = $key($row);
            $rows[$k] = ($rows[$k] ?? $row + ['revenue' => 0.0, 'cost' => 0.0]);
            $rows[$k]['cost'] += $row['amount']->toFloat();
        }

        $products = Product::whereIn('id', array_column($rows, 'product_id'))->pluck('name', 'id');
        $locations = $this->locationNames($rows);

        return collect($rows)
            ->map(function (array $row) use ($products, $locations) {
                $revenue = round($row['revenue'], 2);
                $cost = round($row['cost'], 2);

                return [
                    // order_date is kept as an alias so the print view and any
                    // caller that grouped on it keep working.
                    'order_date'    => $row['date'],
                    'date'          => $row['date'],
                    'product_id'    => $row['product_id'],
                    'product_name'  => $products[$row['product_id']] ?? ('#' . $row['product_id']),
                    'location_type' => $this->locationSlug($row['location_type']),
                    'location_name' => $locations[$row['location_type'] . ':' . $row['location_id']] ?? 'Unknown',
                    'revenue'       => $revenue,
                    'cost'          => $cost,
                    'profit'        => round($revenue - $cost, 2),
                ];
            })
            ->sortBy([['date', 'asc'], ['product_name', 'asc']])
            ->values();
    }

    /**
     * Resolve every location the detail refers to, one query per type.
     *
     * @param  array<string,array<string,mixed>> $rows
     * @return array<string,string>
     */
    private function locationNames(array $rows): array
    {
        $names = [];

        foreach (collect($rows)->groupBy('location_type') as $type => $group) {
            if (! $type || ! class_exists($type)) {
                continue;
            }

            $ids = $group->pluck('location_id')->filter()->unique()->all();

            foreach ($type::whereIn('id', $ids)->pluck('name', 'id') as $id => $name) {
                $names[$type . ':' . $id] = $name;
            }
        }

        return $names;
    }

    /**
     * Normalise a location class to the slug the view splits on.
     */
    private function locationSlug(?string $type): string
    {
        return match ($type) {
            Warehouse::class => 'warehouse',
            Retailer::class  => 'retailer',
            default          => 'other',
        };
    }

    /**
     * @param  Collection<string,Money> $movement
     */
    private function amountOn(Collection $movement, string $date): float
    {
        return isset($movement[$date]) ? $movement[$date]->toFloat() : 0.0;
    }

    /**
     * Realised profit per product over a period.
     *
     * The same accounts and the same sign convention the daily profit report
     * uses, exposed so the product listing can show what a product has actually
     * earned without defining gross profit for a fourth time. Returns are
     * already in it: they are posted to contra revenue and back out of cost of
     * sales against the same product.
     *
     * A product with no posted sales in the period is absent rather than zero -
     * "nothing sold" and "sold at cost" are different facts, and the caller is
     * left to say so.
     *
     * @param  array<int,int>|null $productIds  restrict to these, for one page of a listing
     * @return Collection<int,array{revenue:float,cost:float,profit:float}>
     */
    public function realisedProfitByProduct(
        ?array $productIds = null,
        ?string $startDate = null,
        ?string $endDate = null,
    ): Collection {
        $ledger = $this->ledger->excludingClosingEntries();

        $revenue = $ledger->movementByProduct(self::REVENUE_ROLES, $startDate, $endDate, $productIds);
        $cost = $ledger->movementByProduct(self::COST_OF_SALES_ROLES, $startDate, $endDate, $productIds);

        return collect($revenue->keys())
            ->merge($cost->keys())
            ->unique()
            ->mapWithKeys(function (int $productId) use ($revenue, $cost) {
                // Revenue is a credit balance, so it reads negative; cost is a
                // debit and reads as it stands.
                $earned = isset($revenue[$productId]) ? -$revenue[$productId]->toFloat() : 0.0;
                $spent = isset($cost[$productId]) ? $cost[$productId]->toFloat() : 0.0;

                return [$productId => [
                    'revenue' => round($earned, 2),
                    'cost'    => round($spent, 2),
                    'profit'  => round($earned - $spent, 2),
                ]];
            });
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
     * Build the overall summary from the daily rows.
     *
     * Totals come from the accounts themselves rather than from the detail, so
     * revenue posted without a line breakdown still counts. The gap between the
     * two is reported as unattributed instead of being quietly absorbed into
     * whichever product happened to come first.
     */
    private function buildSummary(Collection $dailyTotals, Collection $detail): array
    {
        $totalRevenue = round($dailyTotals->sum('total_revenue'), 2);
        $totalCost = round($dailyTotals->sum('total_cost'), 2);
        $totalProfit = round($totalRevenue - $totalCost, 2);

        $bucket = function (string $slug) use ($detail) {
            $rows = $detail->where('location_type', $slug);
            $revenue = round($rows->sum('revenue'), 2);
            $profit = round($rows->sum('profit'), 2);

            return [
                'revenue'  => $revenue,
                'profit'   => $profit,
                'products' => $rows->pluck('product_id')->unique()->count(),
                'margin'   => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0,
            ];
        };

        $warehouse = $bucket('warehouse');
        $retailer = $bucket('retailer');
        $margin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0;

        return [
            'gross_revenue'      => round($dailyTotals->sum('gross_revenue'), 2),
            'total_returns'      => round($dailyTotals->sum('total_returns'), 2),
            'total_discounts'    => round($dailyTotals->sum('total_discounts'), 2),
            'total_revenue'      => $totalRevenue,
            'total_cost'         => $totalCost,
            'total_profit'       => $totalProfit,
            'profit_margin'      => $margin,
            'average_margin'     => $margin,
            'products_count'     => $detail->pluck('product_id')->unique()->count(),
            'days_count'         => $dailyTotals->count(),
            // Revenue and cost carrying no product, so they are in the totals
            // above but in none of the rows below.
            'unattributed'       => round($totalProfit - $detail->sum('profit'), 2),
            'warehouse_profit'   => $warehouse['profit'],
            'warehouse_revenue'  => $warehouse['revenue'],
            'warehouse_products' => $warehouse['products'],
            'warehouse_margin'   => $warehouse['margin'],
            'retailer_profit'    => $retailer['profit'],
            'retailer_revenue'   => $retailer['revenue'],
            'retailer_products'  => $retailer['products'],
            'retailer_margin'    => $retailer['margin'],
        ];
    }

    /**
     * Get blank summary for empty data
     */
    private function getBlankSummary(): array
    {
        return [
            'gross_revenue'      => 0,
            'total_returns'      => 0,
            'total_discounts'    => 0,
            'total_revenue'      => 0,
            'total_cost'         => 0,
            'total_profit'       => 0,
            'profit_margin'      => 0,
            'average_margin'     => 0,
            'products_count'     => 0,
            'days_count'         => 0,
            'unattributed'       => 0,
            'warehouse_profit'   => 0,
            'warehouse_revenue'  => 0,
            'warehouse_products' => 0,
            'warehouse_margin'   => 0,
            'retailer_profit'    => 0,
            'retailer_revenue'   => 0,
            'retailer_products'  => 0,
            'retailer_margin'    => 0,
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
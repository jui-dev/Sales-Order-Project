<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Warehouse;
use App\Models\Retailer;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    /**
     * Show Daily Profit Report page with calculated data.
     */
    public function dailyProfit(Request $request)
    {
        // Validate date input – allow empty which falls back to defaults
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        // Determine date range – default to current month
        $startDate = Arr::get($validated, 'start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate   = Arr::get($validated, 'end_date', Carbon::now()->toDateString());

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
            return view('reports.daily-profit', [
                'startDate'     => $startDate,
                'endDate'       => $endDate,
                'dailyProfits'  => collect(),
                'dailyTotals'   => collect(),
                'summary'       => $this->blankSummary(),
            ]);
        }

        // Build per-item profit records
        $dailyProfits = $orderItems->map(function (OrderItem $item) {
            $product        = $item->product;
            $locationModel  = $item->location;

            // Normalise location type to friendly slug
            $locationType = match ($item->location_type) {
                \App\Models\Warehouse::class => 'warehouse',
                \App\Models\Retailer::class  => 'retailer',
                default                        => 'other',
            };

            // Guard against missing location records
            $locationName = $locationModel?->name ?? 'Unknown';

            $revenue = (float) $item->unit_price * (int) $item->quantity;
            $cost    = (float) ($product->purchase_price ?? 0) * (int) $item->quantity;
            $profit  = $revenue - $cost;

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
        });

        // Aggregate daily totals
        $dailyTotals = $dailyProfits->groupBy('order_date')->map(function (Collection $items) {
            return [
                'date'             => $items->first()['order_date'],
                'products_count'   => $items->sum('quantity_sold'),
                'total_revenue'    => $items->sum('revenue'),
                'total_cost'       => $items->sum('cost'),
                'total_profit'     => $items->sum('profit'),
                'warehouse_profit' => $items->where('location_type', 'warehouse')->sum('profit'),
                'retailer_profit'  => $items->where('location_type', 'retailer')->sum('profit'),
            ];
        })->sortBy('date')->values();

        // Overall summary
        $summary = $this->buildSummary($dailyProfits);

        return view('reports.daily-profit', compact('startDate', 'endDate', 'dailyProfits', 'dailyTotals', 'summary'));
    }

    /**
     * Display the Trial Balance report.
     */
    public function trialBalance(Request $request)
    {
        $validated = $request->validate([
            'end_date' => ['nullable', 'date'],
        ]);

        $endDate = $validated['end_date'] ?? null;

        /** @var \App\Services\AccountingService $acct */
        $acct = app(\App\Services\AccountingService::class);

        $balances = $acct->trialBalance($endDate ? \Illuminate\Support\Carbon::parse($endDate) : null)
            ->sortBy(fn($row) => $row['account']->code);

        $totalDebit  = $balances->sum('debit');
        $totalCredit = $balances->sum('credit');

        $export = $request->query('export');
        if ($export === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="trial-balance-'.($endDate ?? date('Y-m-d')).'.csv"',
            ];
            $callback = function() use ($balances) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Account Code', 'Account Name', 'Debit', 'Credit']);
                foreach ($balances as $row) {
                    fputcsv($out, [
                        $row['account']->code,
                        $row['account']->name,
                        number_format($row['debit'], 2, '.', ''),
                        number_format($row['credit'], 2, '.', ''),
                    ]);
                }
                fclose($out);
            };
            return response()->stream($callback, 200, $headers);
        } elseif ($export === 'pdf') {
            $pdf = Pdf::loadView('reports.trial-balance-pdf', [
                'balances'    => $balances,
                'endDate'     => $endDate,
                'totalDebit'  => $totalDebit,
                'totalCredit' => $totalCredit,
            ]);
            return $pdf->download('trial-balance-'.($endDate ?? date('Y-m-d')).'.pdf');
        }

        return view('reports.trial-balance', [
            'balances'    => $balances,
            'endDate'     => $endDate,
            'totalDebit'  => $totalDebit,
            'totalCredit' => $totalCredit,
        ]);
    }

    /**
     * Display the Income Statement report.
     */
    public function incomeStatement(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = $validated['start_date'] ?? null;
        $endDate   = $validated['end_date']   ?? null;

        // Build aggregated journal lines for the period scoped to Revenue & Expense accounts only
        $query = \App\Models\JournalEntryLine::query()
            ->select('account_id', \Illuminate\Support\Facades\DB::raw('SUM(debit) as debit'), \Illuminate\Support\Facades\DB::raw('SUM(credit) as credit'))
            ->groupBy('account_id')
            ->with(['account.accountType'])
            ->whereHas('account.accountType', function ($q) {
                $q->whereIn('name', ['Revenue', 'Expense']);
            });

        if ($startDate) {
            $query->whereHas('journalEntry', fn($q) => $q->whereDate('entry_date', '>=', $startDate));
        }

        if ($endDate) {
            $query->whereHas('journalEntry', fn($q) => $q->whereDate('entry_date', '<=', $endDate));
        }

        // Only approved entries
        $query->whereHas('journalEntry', fn($q) => $q->whereIn('status', ['posted','approved']));

        $rows = $query->get();

        $revenues = collect();
        $expenses = collect();

        // Separate rows into revenues and expenses with proper sign handling
        foreach ($rows as $row) {
            $account = $row->account;
            $type    = $account->accountType?->name ?? '';

            // Calculate net amount for the account (positive values only for presentation)
            if ($type === 'Revenue') {
                $amount = ($row->credit - $row->debit);
                // Adjust for contra accounts (e.g., Sales Returns) which reduce revenue
                if ($account->is_contra) {
                    $amount *= -1;
                }
                $revenues->push([
                    'account' => $account,
                    'amount'  => round($amount, 2),
                ]);
            } elseif ($type === 'Expense') {
                $amount = ($row->debit - $row->credit);
                $expenses->push([
                    'account' => $account,
                    'amount'  => round($amount, 2),
                ]);
            }
        }

        // Totals
        $totalRevenue = $revenues->sum('amount');
        $totalExpense = $expenses->sum('amount');
        $netIncome    = $totalRevenue - $totalExpense;

        $export = $request->query('export');
        if ($export === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="income-statement-'.($endDate ?? date('Y-m-d')).'.csv"',
            ];
            $callback = function() use ($revenues, $expenses) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Section', 'Account Code', 'Account Name', 'Amount']);
                foreach ($revenues as $row) {
                    fputcsv($out, ['Revenue', $row['account']->code, $row['account']->name, number_format($row['amount'], 2, '.', '')]);
                }
                foreach ($expenses as $row) {
                    fputcsv($out, ['Expense', $row['account']->code, $row['account']->name, number_format($row['amount'], 2, '.', '')]);
                }
                fclose($out);
            };
            return response()->stream($callback, 200, $headers);
        } elseif ($export === 'pdf') {
            $pdf = Pdf::loadView('reports.income-statement-pdf', [
                'startDate'     => $startDate,
                'endDate'       => $endDate,
                'revenues'      => $revenues,
                'expenses'      => $expenses,
                'totalRevenue'  => $totalRevenue,
                'totalExpense'  => $totalExpense,
                'netIncome'     => $netIncome,
            ]);
            return $pdf->download('income-statement-'.($endDate ?? date('Y-m-d')).'.pdf');
        }

        return view('reports.income-statement', [
            'startDate'     => $startDate,
            'endDate'       => $endDate,
            'revenues'      => $revenues,
            'expenses'      => $expenses,
            'totalRevenue'  => $totalRevenue,
            'totalExpense'  => $totalExpense,
            'netIncome'     => $netIncome,
        ]);
    }

    /**
     * Display the Balance Sheet report (Statement of Financial Position).
     */
    public function balanceSheet(Request $request)
    {
        $validated = $request->validate([
            'end_date' => ['nullable', 'date'],
        ]);

        $endDate = $validated['end_date'] ?? null;

        // Aggregate journal lines up to end date, grouped by account
        $query = \App\Models\JournalEntryLine::query()
            ->select('account_id', \Illuminate\Support\Facades\DB::raw('SUM(debit) as debit'), \Illuminate\Support\Facades\DB::raw('SUM(credit) as credit'))
            ->groupBy('account_id')
            ->with(['account.accountType']);

        if ($endDate) {
            $query->whereHas('journalEntry', fn($q) => $q->whereDate('entry_date', '<=', $endDate));
        }

        // Only posted or approved
        $query->whereHas('journalEntry', fn($q) => $q->whereIn('status', ['posted','approved']));

        // Map rows keyed by account id for quick lookup
        $journalTotals = $query->get()->keyBy('account_id');

        // Pull all relevant accounts (Assets, Liabilities, Equity)
        $accounts = \App\Models\Account::with('accountType')->whereHas('accountType', function ($q) {
            $q->whereIn('name', ['Asset', 'Liability', 'Equity']);
        })->get();

        $assets      = collect();
        $liabilities = collect();
        $equity      = collect();

        foreach ($accounts as $account) {
            $typeName = $account->accountType?->name ?? '';

            $totals = $journalTotals->get($account->id);
            $debit  = $totals?->debit  ?? 0;
            $credit = $totals?->credit ?? 0;

            $opening = $account->opening_balance ?? 0;

            // Determine balance sign based on account type (Assets normally debit, Liability/Equity credit)
            $balance = 0;
            if ($typeName === 'Asset') {
                $balance = $opening + ($debit - $credit);
            } else { // Liability or Equity
                $balance = $opening + ($credit - $debit);
            }

            // Adjust for contra accounts (invert)
            if ($account->is_contra) {
                $balance *= -1;
            }

            $row = [
                'account' => $account,
                'balance' => round($balance, 2),
            ];

            match ($typeName) {
                'Asset'     => $assets->push($row),
                'Liability' => $liabilities->push($row),
                'Equity'    => $equity->push($row),
                default     => null,
            };
        }

        /* ------------------------------------------------------------------
         | Retained Earnings (Net Income/Loss)
         |------------------------------------------------------------------*/
        // Compute net income for the period up to the selected end date (or all time)
        $revExpQuery = \App\Models\JournalEntryLine::query()
            ->select('account_id', \Illuminate\Support\Facades\DB::raw('SUM(debit) as debit'), \Illuminate\Support\Facades\DB::raw('SUM(credit) as credit'))
            ->groupBy('account_id')
            ->with(['account.accountType'])
            ->whereHas('account.accountType', function ($q) {
                $q->whereIn('name', ['Revenue', 'Expense']);
            });

        if ($endDate) {
            $revExpQuery->whereHas('journalEntry', fn($q) => $q->whereDate('entry_date', '<=', $endDate));
        }

        $revExpQuery->whereHas('journalEntry', fn($q) => $q->whereIn('status', ['posted','approved']));

        $revExpRows = $revExpQuery->get();

        $totalRevenue = 0.0;
        $totalExpense = 0.0;

        foreach ($revExpRows as $row) {
            $acct = $row->account;
            $typeName = $acct->accountType?->name ?? '';

            if ($typeName === 'Revenue') {
                $amount = ($row->credit - $row->debit);
                if ($acct->is_contra) {
                    $amount *= -1; // contra revenue reduces revenue
                }
                $totalRevenue += $amount;
            } elseif ($typeName === 'Expense') {
                $amount = ($row->debit - $row->credit);
                $totalExpense += $amount;
            }
        }

        $netIncome = round($totalRevenue - $totalExpense, 2); // could be negative (loss)

        // If there's any net income (or loss), add/accumulate it under Retained Earnings
        if (abs($netIncome) > 0.01) {
            $existingKey = $equity->search(function ($row) {
                return isset($row['account']->name) && strcasecmp($row['account']->name, 'Retained Earnings') === 0;
            });

            if ($existingKey !== false) {
                // Accumulate with existing retained earnings balance (collections are immutable by reference)
                $existingRow = $equity->get($existingKey);
                $existingRow['balance'] = round(($existingRow['balance'] ?? 0) + $netIncome, 2);
                $equity->put($existingKey, $existingRow);
            } else {
                // Create a lightweight stub account object for presentation only
                $stubAccount = (object) [
                    'code' => 'RE',
                    'name' => 'Retained Earnings',
                ];

                $equity->push([
                    'account' => $stubAccount,
                    'balance' => $netIncome,
                ]);
            }
        }

        $totalAssets      = $assets->sum('balance');
        $totalLiabilities = $liabilities->sum('balance');
        $totalEquity      = $equity->sum('balance');
        $liabEqTotal      = $totalLiabilities + $totalEquity;

        $export = $request->query('export');
        if ($export === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="balance-sheet-'.($endDate ?? date('Y-m-d')).'.csv"',
            ];
            $callback = function() use ($assets, $liabilities, $equity) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Section', 'Account Code', 'Account Name', 'Balance']);
                foreach ($assets as $row) {
                    fputcsv($out, ['Asset', $row['account']->code, $row['account']->name, number_format($row['balance'], 2, '.', '')]);
                }
                foreach ($liabilities as $row) {
                    fputcsv($out, ['Liability', $row['account']->code, $row['account']->name, number_format($row['balance'], 2, '.', '')]);
                }
                foreach ($equity as $row) {
                    fputcsv($out, ['Equity', $row['account']->code, $row['account']->name, number_format($row['balance'], 2, '.', '')]);
                }
                fclose($out);
            };
            return response()->stream($callback, 200, $headers);
        } elseif ($export === 'pdf') {
            $pdf = Pdf::loadView('reports.balance-sheet-pdf', [
                'endDate'          => $endDate,
                'assets'           => $assets,
                'liabilities'      => $liabilities,
                'equity'           => $equity,
                'totalAssets'      => $totalAssets,
                'totalLiabilities' => $totalLiabilities,
                'totalEquity'      => $totalEquity,
                'liabEqTotal'      => $liabEqTotal,
            ]);
            return $pdf->download('balance-sheet-'.($endDate ?? date('Y-m-d')).'.pdf');
        }

        return view('reports.balance-sheet', [
            'endDate'          => $endDate,
            'assets'           => $assets,
            'liabilities'      => $liabilities,
            'equity'           => $equity,
            'totalAssets'      => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'totalEquity'      => $totalEquity,
            'liabEqTotal'      => $liabEqTotal,
        ]);
    }

    /**
     * Display the Cash Flow Statement report.
     */
    public function cashFlowStatement(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = $validated['start_date'] ?? null;
        $endDate   = $validated['end_date']   ?? null;

        // Codes
        $CASH_CODE       = '1000';
        $AR_CODE         = '1100';
        $INV_CODE        = '1200';
        $AP_CODE         = '2000';

        // Fetch journal entries involving cash account within period
        $entries = \App\Models\JournalEntry::with(['lines.account.accountType'])
            ->when($startDate, fn($q) => $q->whereDate('entry_date', '>=', $startDate))
            ->when($endDate,   fn($q) => $q->whereDate('entry_date', '<=', $endDate))
            ->whereHas('lines.account', fn($q) => $q->where('code', $CASH_CODE))
            ->whereIn('status', ['posted','approved'])
            ->get();

        $operatingTotal = 0.0;
        $investingTotal = 0.0;
        $financingTotal = 0.0;

        $operating = collect();
        $investing = collect();
        $financing = collect();

        foreach ($entries as $entry) {
            // Determine net cash change for this entry
            $cashChange = $entry->lines->where('account.code', $CASH_CODE)
                ->sum(fn($l) => ($l->debit - $l->credit));

            if (abs($cashChange) < 0.01) {
                continue; // no net cash movement
            }

            // Analyse non-cash lines to categorise the entry
            $category = 'operating';
            foreach ($entry->lines as $line) {
                if ($line->account->code === $CASH_CODE) {
                    continue;
                }

                $typeName = $line->account->accountType?->name ?? '';
                $code     = $line->account->code;

                if (in_array($typeName, ['Revenue', 'Expense'])) {
                    $category = 'operating';
                } elseif ($typeName === 'Asset') {
                    $category = in_array($code, [$AR_CODE, $INV_CODE]) ? 'operating' : 'investing';
                } elseif ($typeName === 'Liability') {
                    $category = ($code === $AP_CODE) ? 'operating' : 'financing';
                } elseif ($typeName === 'Equity') {
                    $category = 'financing';
                }

                // Once we pick a non-operating category, break
                if ($category !== 'operating') {
                    break;
                }
            }

            $row = [
                'entry'       => $entry,
                'description' => $entry->description,
                'date'        => $entry->entry_date,
                'amount'      => round($cashChange, 2),
            ];

            match ($category) {
                'operating'  => $operating->push($row)  && ($operatingTotal  += $cashChange),
                'investing'  => $investing->push($row)  && ($investingTotal  += $cashChange),
                'financing'  => $financing->push($row)  && ($financingTotal  += $cashChange),
            };
        }

        $netChange = $operatingTotal + $investingTotal + $financingTotal;

        $export = $request->query('export');
        if ($export === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="cash-flow-'.($endDate ?? date('Y-m-d')).'.csv"',
            ];
            $callback = function() use ($operating, $investing, $financing) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Category', 'Description', 'Amount']);
                foreach ($operating as $row) {
                    fputcsv($out, ['Operating', $row['description'] ?? 'Entry #'.$row['entry']->id, number_format($row['amount'], 2, '.', '')]);
                }
                foreach ($investing as $row) {
                    fputcsv($out, ['Investing', $row['description'] ?? 'Entry #'.$row['entry']->id, number_format($row['amount'], 2, '.', '')]);
                }
                foreach ($financing as $row) {
                    fputcsv($out, ['Financing', $row['description'] ?? 'Entry #'.$row['entry']->id, number_format($row['amount'], 2, '.', '')]);
                }
                fclose($out);
            };
            return response()->stream($callback, 200, $headers);
        } elseif ($export === 'pdf') {
            $pdf = Pdf::loadView('reports.cash-flow-pdf', [
                'startDate'       => $startDate,
                'endDate'         => $endDate,
                'operating'       => $operating,
                'investing'       => $investing,
                'financing'       => $financing,
                'operatingTotal'  => $operatingTotal,
                'investingTotal'  => $investingTotal,
                'financingTotal'  => $financingTotal,
                'netChange'       => $netChange,
            ]);
            return $pdf->download('cash-flow-'.($endDate ?? date('Y-m-d')).'.pdf');
        }

        return view('reports.cash-flow', [
            'startDate'       => $startDate,
            'endDate'         => $endDate,
            'operating'       => $operating,
            'investing'       => $investing,
            'financing'       => $financing,
            'operatingTotal'  => $operatingTotal,
            'investingTotal'  => $investingTotal,
            'financingTotal'  => $financingTotal,
            'netChange'       => $netChange,
        ]);
    }

    private function blankSummary(): array
    {
        return [
            'total_products_sold'   => 0,
            'total_revenue'         => 0,
            'total_profit'          => 0,
            'average_margin'        => 0,
            'warehouse_products_sold' => 0,
            'warehouse_revenue'     => 0,
            'warehouse_profit'      => 0,
            'warehouse_margin'      => 0,
            'retailer_products_sold' => 0,
            'retailer_revenue'      => 0,
            'retailer_profit'       => 0,
            'retailer_margin'       => 0,
        ];
    }

    private function buildSummary(Collection $items): array
    {
        if ($items->isEmpty()) {
            return $this->blankSummary();
        }

        $totalRevenue = $items->sum('revenue');
        $totalProfit  = $items->sum('profit');
        $warehouseItems = $items->where('location_type', 'warehouse');
        $retailerItems  = $items->where('location_type', 'retailer');

        $warehouseRevenue = $warehouseItems->sum('revenue');
        $warehouseProfit  = $warehouseItems->sum('profit');
        $retailerRevenue  = $retailerItems->sum('revenue');
        $retailerProfit   = $retailerItems->sum('profit');

        return [
            'total_products_sold'     => $items->sum('quantity_sold'),
            'total_revenue'           => $totalRevenue,
            'total_profit'            => $totalProfit,
            'average_margin'          => $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : 0,
            'warehouse_products_sold' => $warehouseItems->sum('quantity_sold'),
            'warehouse_revenue'       => $warehouseRevenue,
            'warehouse_profit'        => $warehouseProfit,
            'warehouse_margin'        => $warehouseRevenue > 0 ? round(($warehouseProfit / $warehouseRevenue) * 100, 1) : 0,
            'retailer_products_sold'  => $retailerItems->sum('quantity_sold'),
            'retailer_revenue'        => $retailerRevenue,
            'retailer_profit'         => $retailerProfit,
            'retailer_margin'         => $retailerRevenue > 0 ? round(($retailerProfit / $retailerRevenue) * 100, 1) : 0,
        ];
    }
} 
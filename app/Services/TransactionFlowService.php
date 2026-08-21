<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PickingList;
use App\Models\StockTransaction;
use App\Models\Warehouse;
use App\Models\Retailer;
use Illuminate\Database\Eloquent\Collection;

class TransactionFlowService
{
    /**
     * Get stock summary statistics
     */
    public function getStockSummary(): array
    {
        // Total products
        $totalProducts = Product::count();

        // Total stock value (on-hand across all locations) AT COST - what the
        // goods are carried at, not what they would sell for. The dashboard
        // reports the same inventory at retail, and both are labelled so the
        // two figures are not read as contradicting each other.
        //
        // Cost lives in the dated product_costs ledger rather than in a column
        // on products, so the join is to each product's most recent row.
        // A correlated sub-select rather than a join: two receipts booked on the
        // same day share an effective_at, so joining on the maximum date alone
        // matches both rows and doubles that product's value. The ORDER BY here
        // is the same tie-break ProductCost::scopeInForceAt applies.
        //
        // A product with no recorded cost yields NULL, which SUM skips - stock
        // of unknown cost contributes nothing rather than counting as free.
        $totalStockValue = ProductStock::query()
            ->selectRaw(
                'SUM(product_stocks.quantity * (
                    SELECT pc.unit_cost FROM product_costs pc
                    WHERE pc.product_id = product_stocks.product_id
                      AND pc.effective_at <= ?
                    ORDER BY pc.effective_at DESC, pc.id DESC
                    LIMIT 1
                )) as total_value',
                [now()]
            )
            ->value('total_value') ?? 0;

        // Pending movements (picking lists status pending)
        $pendingMovements = PickingList::where('status', 'pending')->count();

        // Active pickings (status pending OR open) for any picking list
        $activePickings = PickingList::whereIn('status', ['pending', 'open'])->count();

        return [
            'total_products' => $totalProducts,
            'total_stock_value' => round($totalStockValue, 2),
            'pending_movements' => $pendingMovements,
            'active_pickings' => $activePickings,
        ];
    }

    /**
     * Get recent stock movements
     *
     * Eager loads `location`, not the `stockLocation` alias: that alias is
     * declared as morphTo('location'), so Laravel matches the eager results
     * back onto the `location` relation while leaving `stockLocation` set to
     * null. Loading it by its real name is what makes the location resolve.
     */
    public function getRecentMovements(): Collection
    {
        return StockTransaction::with(['product', 'location'])
            ->latest('transaction_date')
            ->limit(20)
            ->get();
    }

    /**
     * Get all warehouses
     */
    public function getWarehouses(): Collection
    {
        return Warehouse::all();
    }

    /**
     * Get all retailers
     */
    public function getRetailers(): Collection
    {
        return Retailer::all();
    }

    /**
     * Get transaction flow statistics
     */
    public function getTransactionFlowStatistics(): array
    {
        $totalTransactions = StockTransaction::count();
        $todayTransactions = StockTransaction::whereDate('transaction_date', today())->count();
        $thisWeekTransactions = StockTransaction::whereBetween('transaction_date', [
            now()->startOfWeek(), 
            now()->endOfWeek()
        ])->count();

        return [
            'total_transactions' => $totalTransactions,
            'today_transactions' => $todayTransactions,
            'this_week_transactions' => $thisWeekTransactions,
        ];
    }
} 
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

        // Total stock value (on-hand across all locations) = sum(quantity * purchase_price)
        $totalStockValue = ProductStock::query()
            ->join('products', 'product_stocks.product_id', '=', 'products.id')
            ->selectRaw('SUM(product_stocks.quantity * products.purchase_price) as total_value')
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
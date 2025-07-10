<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Supply;
use App\Models\Order;
use App\Models\StockTransaction;
use App\Observers\SupplyObserver;
use App\Observers\OrderObserver;
use App\Observers\StockTransactionObserver;
use Illuminate\Support\Facades\View;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Product;
use App\Models\SupplyItem;
use App\Observers\ProductObserver;
use App\Observers\SupplyItemObserver;
// Disabled: use App\Models\WarehouseStock;
// Disabled: use App\Observers\WarehouseStockObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers only if classes exist (prevents migration issues during scaffolding)
        if (class_exists(Supply::class) && class_exists(SupplyObserver::class)) {
            Supply::observe(SupplyObserver::class);
        }

        if (class_exists(Order::class) && class_exists(OrderObserver::class)) {
            Order::observe(OrderObserver::class);
        }

        if (class_exists(StockTransaction::class) && class_exists(StockTransactionObserver::class)) {
            StockTransaction::observe(StockTransactionObserver::class);
        }

        // Register new observers for dynamic pricing and purchasing logic
        if (class_exists(Product::class) && class_exists(ProductObserver::class)) {
            Product::observe(ProductObserver::class);
        }
        if (class_exists(SupplyItem::class) && class_exists(SupplyItemObserver::class)) {
            SupplyItem::observe(SupplyItemObserver::class);
        }
        if (class_exists(\App\Models\ProductStock::class) && class_exists(\App\Observers\ProductStockObserver::class)) {
            \App\Models\ProductStock::observe(\App\Observers\ProductStockObserver::class);
        }
        // Disabled: WarehouseStock::observe(WarehouseStockObserver::class);

        if (class_exists(\App\Models\Grn::class) && class_exists(\App\Observers\GrnObserver::class)) {
            \App\Models\Grn::observe(\App\Observers\GrnObserver::class);
        }

        if (class_exists(\App\Models\Invoice::class) && class_exists(\App\Observers\InvoiceObserver::class)) {
            \App\Models\Invoice::observe(\App\Observers\InvoiceObserver::class);
        }

        if (class_exists(\App\Models\ReturnRecord::class) && class_exists(\App\Observers\ReturnRecordObserver::class)) {
            \App\Models\ReturnRecord::observe(\App\Observers\ReturnRecordObserver::class);
        }

        if (class_exists(\App\Models\StockTransfer::class) && class_exists(\App\Observers\StockTransferObserver::class)) {
            \App\Models\StockTransfer::observe(\App\Observers\StockTransferObserver::class);
        }

        // Observe picking lists completion to finalise stock deduction
        if (class_exists(\App\Models\PickingList::class) && class_exists(\App\Observers\PickingListObserver::class)) {
            \App\Models\PickingList::observe(\App\Observers\PickingListObserver::class);
        }

        // Provide default empty collections / counters to all Blade views so that
        // missing backend data does not trigger "Undefined variable" errors
        // in this UI-only prototype. This keeps the templates functional until
        // real controllers and database queries are implemented.
        View::share([
            // Collections
            'warehouses'        => collect(),
            'vendors'           => collect(),
            'customers'         => collect(),
            'products'          => collect(),
            'orders'            => collect(),
            'supplies'          => collect(),
            'returns'           => new LengthAwarePaginator([], 0, 15, 1, ['path' => url()->current()]),
            'retailers'         => collect(),
            'transfers'         => collect(),
            'pickingLists'      => collect(),
            'recentSupplies'    => collect(),
            'recentOrders'      => collect(),
            'stockTransactions' => new LengthAwarePaginator([], 0, 15, 1, ['path' => url()->current()]),

            // Counters / aggregates
            'totalVendors'      => 0,
            'totalWarehouses'   => 0,
            'totalRetailers'    => 0,
            'totalTransfers'    => 0,

            // Generic statistics placeholder for pages expecting an array structure
            'statistics' => [
                'total_picking_lists'      => 0,
                'completed_picking_lists'  => 0,
                'pending_picking_lists'    => 0,
                'in_progress_picking_lists'=> 0,
            ],

            // Defaults for product transaction history view
            'product'            => new class {
                public int $id = 0;
                public string $name = 'Placeholder';
                public function __toString(): string
                {
                    return (string) $this->id;
                }
            },
            'totalSupplied'      => 0,
            'totalSold'          => 0,
            'totalTransferred'   => 0,
            'currentTotalStock'  => 0,
            'currentAvailableStock' => 0,
            'currentReservedStock'  => 0,
            'stockBalances'      => collect(),
            'movements'          => collect(),

            // stock locations list placeholder
            'stockLocations'     => collect(),
            'locations'          => collect(),

            // Default date range for reports
            'startDate'         => date('Y-m-01'),
            'endDate'           => date('Y-m-d'),

            // Reports placeholders
            'dailyProfits'      => collect(),
            'dailyTotals'       => collect(),
            'summary'           => [
                'total_products_sold' => 0,
                'total_revenue'       => 0,
                'total_profit'        => 0,
                'average_margin'      => 0,
                'warehouse_products_sold' => 0,
                'warehouse_revenue'   => 0,
                'warehouse_profit'    => 0,
                'warehouse_margin'    => 0,
                'retailer_products_sold' => 0,
                'retailer_revenue'    => 0,
                'retailer_profit'     => 0,
                'retailer_margin'     => 0,
            ],
            'stockData' => [
                'product'           => null,
                'current_stock'     => 0,
                'total_supplied'    => 0,
                'total_ordered'     => 0,
                'projected_stock'   => 0,
                'pending_supplies'  => 0,
                'pending_orders'    => 0,
                'stock_by_location' => collect(),
                'supplies'          => collect(),
                'orders'            => collect(),
            ],
            'stockSummary' => [
                'total_products'     => 0,
                'total_stock_value'  => 0,
                'pending_movements'  => 0,
                'active_pickings'    => 0,
            ],
            'recentMovements' => collect(),
        ]);

        // Global composer: replace any null variable passed to views with an empty collection
        \Illuminate\Support\Facades\View::composer('*', function ($view) {
            $data = $view->getData();
            foreach ($data as $key => $value) {
                if ($value === null) {
                    $view->with($key, collect());
                }
            }
        });
    }
}
